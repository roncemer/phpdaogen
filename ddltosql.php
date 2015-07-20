<?php
// ddltosql.php
// Copyright (c) 2011-2014 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

include dirname(__FILE__).'/AbstractINIMultiDatabaseConnectionFactory.class.php';
include dirname(__FILE__).'/DDL.class.php';
include dirname(__FILE__).'/MySQLiConnection.class.php';
include dirname(__FILE__).'/PostgreSQLConnection.class.php';

// If $db is provided, it will be used to suppress insert statements which are not needed,
// and may also be used to transform insert statements into update statements for rows which
// exist but have non-key columns whose values don't match the original insert values (for
// inserts with key columns and updateIfExists set to true).
function process($ddl, $dialect, $action, $allowedTableNames, $db = null, $dbmap = null) {
	switch ($action) {
	case 'outputSQL':
		$serializer = new SQLDDLSerializer();
		$sqlStatements = $serializer->serialize($ddl, $dialect, $dbmap);
		if (!empty($sqlStatements)) {
			if ($dialect == 'mysql') fputs(STDOUT, "set foreign_key_checks = 0;\n");
			foreach ($sqlStatements as $stmt) {
				fprintf(STDOUT, "%s;\n", $stmt);
			}
			if ($dialect == 'mysql') fputs(STDOUT, "set foreign_key_checks = 1;\n");
		}
		break;
	case 'listTables':
		foreach ($ddl->topLevelEntities as &$tle) {
			switch (get_class($tle)) {
			case 'DDLTable':
				fprintf(STDOUT, "%s\n", $tle->tableName);
				break;
			}
		}
		unset($tle);	// release reference to last element
		break;
	case 'insertsWithKeyCols':
		$serializer = new SQLDDLSerializer();
		foreach ($ddl->topLevelEntities as &$tle) {
			switch (get_class($tle)) {
			case 'DDLInsert':
				if (!empty($tle->keyColumnNames)) {
					$sqlStatements = $serializer->serializeInsert($tle, $dialect, $db);
					if (!empty($sqlStatements)) {
						if ($dialect == 'mysql') fputs(STDOUT, "set foreign_key_checks = 0;\n");
						foreach ($sqlStatements as $stmt) {
							fprintf(STDOUT, "%s;\n", $stmt);
						}
						if ($dialect == 'mysql') fputs(STDOUT, "set foreign_key_checks = 1;\n");
					}
				}
				break;
			}
		}
		unset($tle);	// release reference to last element
		break;
	}
}

function usage() {
	global $argv;

	fputs(
		STDERR,
		"Usage: php ".basename($argv[0])." [options] <database-ini-file> <ddl-dir> [<connection-name>] [<table-name> [<table-name> ...]]\n".
		"Generate SQL statements to initialize a database.\n".
		"    database-ini-file - The path to the database.ini file which contains connection configurations.\n".
		"    ddl-dir           - The path to the ddl directory where all of the YAML DDL files exist.\n".
		"    connection-name   - The connection name, or an empty string (or omit) to use the default connection.\n".
		"    table-name        - Optional table names which, if specified, will be the only tables processed.\n".
		"                        If omitted, all tables will be processed.\n".
		"    -list-tables-only  - Only list the table names which are defined in the DDL.\n".
		"    -inserts-with-keycols-only - Only output inserts with key columns, and their\n".
		"                         corresponding (optional) updates. These are inserts\n".
		"                         which specify key columns (keyColumnNames in YAML),\n".
		"                         enabling generation of conditional inserts which only\n".
		"                         insert rows which don't already exist, and optionally\n".
		"                         generate updates for rows which do exist (controlled by\n".
		"                         updateIfExists in YAML).\n"
	);
	exit(1);
} // usage()

$databaseIniFile = '';
$ddlDir = '';
$connectionName = '';
$allowedTableNames = array();
$action = 'outputSQL';

$argState = 0;
for ($ai = 1; $ai < $argc; $ai++) {
	$arg = $argv[$ai];
	if ( (strlen($arg) > 0) && ($arg[0] == '-') ) {
		switch ($arg) {
		case '-list-tables-only':
			$action = 'listTables';
			break;
		case '-inserts-with-keycols-only':
			$action = 'insertsWithKeyCols';
			break;
		case '-help':
		case '--help':
		case '-?':
			usage();
		default:
			fprintf(STDERR, "Unrecognized command line switch: %s.\n", $arg);
			usage();
		}
		continue;
	}	// if ( (strlen($arg) > 0) && ($arg[0] == '-') )
	switch ($argState) {
	case 0: $databaseIniFile = $arg; $argState++; break;
	case 1: $ddlDir = $arg; $argState++; break;
	case 2: $connectionName = $arg; $argState++; break;
	case 3: $allowedTableNames[] = $arg;		// remain in this state.
	}
}

if ($databaseIniFile == '') {
	fprintf(STDERR, "No database.ini file specified.\n");
	usage();
}
if ($ddlDir == '') {
	fprintf(STDERR, "No DDL directory specified.\n");
	usage();
}

$databaseAllowedTableNames = array_slice($allowedTableNames, 0);

// Build a connection factory which works witht the specifed database.ini file.
class MyConnectionFactory extends AbstractINIMultiDatabaseConnectionFactory {
}
MyConnectionFactory::$INI_FILE = $databaseIniFile;

$connectionParamsByName = MyConnectionFactory::getConnectionParamsByName();
if (!isset($connectionParamsByName[$connectionName])) {
	fprintf(STDERR, "Invalid connection name: %s\n", $connectionName);
	exit(30);
}
$connectionParams = MyConnectionFactory::getConnectionParams($connectionName);
$dbmap = (isset($connectionParams['tableToDatabaseMap']) && ($connectionParams['tableToDatabaseMap'] != '')) ?
	new DDLTableToDatabaseMap($connectionParams['tableToDatabaseMap']) : null;
if (!isset($connectionParams['connectionClass'])) {
	fprintf(STDERR, "Missing connectionClass parameter for %s connection.\n", $connectionName);
	exit(31);
}

$db = MyConnectionFactory::getConnection($connectionName);
$dialect = $db->getDialect();
$db->close();

// Get list of table names for this connection which are mapped from other connections;
// merge with allowed table names to get final table name list.
$allCurrentDDL = new DDL();
if (($res = YAMLDDLParser::loadAllDDLFiles($ddlDir, $allCurrentDDL)) != 0) exit($res);
$mapTargetTableNames = MyConnectionFactory::getMapTargetTableNames($connectionName, $connectionParamsByName, $allCurrentDDL);
unset($allCurrentDDL, $res);
if (!empty($mapTargetTableNames)) {
	if (!empty($allowedTableNames)) {
		$allowedTableNames = array_intersect($allowedTableNames, $mapTargetTableNames);
	} else {
		$allowedTableNames = $mapTargetTableNames;
	}
}

// Load the current DDL from the DDL files.
$currentDDL = new DDL(array());
if (($res = YAMLDDLParser::loadAllDDLFiles($ddlDir, $currentDDL, $allowedTableNames, $dbmap)) != 0) exit($res);

// If we have map-target tables, remove all foreign keys from the current DDL which
// reference tables which are not map targets (because those tables should not exist
// in this database).
if (!empty($mapTargetTableNames)) {
	$anyDeleted = false;
	for ($i = 0, $n = count($currentDDL->topLevelEntities); $i < $n; $i++) {
		if (($currentDDL->topLevelEntities[$i] instanceof DDLForeignKey) &&
			(!in_array($currentDDL->topLevelEntities[$i]->foreignTableName, $mapTargetTableNames))) {
			unset($currentDDL->topLevelEntities[$i]);
			$anyDeleted = true;
		}
	}
	if ($anyDeleted) $currentDDL->topLevelEntities = array_slice($currentDDL->topLevelEntities, 0);
	unset($i, $n, $anyDeleted);
}

$db = ($action == 'insertsWithKeyCols') ? MyConnectionFactory::getConnection($connectionName) : '';
process(
	$currentDDL,
	$dialect,
	$action,
	$allowedTableNames,
	$db,
	$dbmap
);
if ($db) $db->close();

exit(0);
