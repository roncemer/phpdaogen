<?php
// daogen.php
// Copyright (c) 2010-2014 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('MySQLiConnection', false)) include dirname(__FILE__).'/MySQLiConnection.class.php';
if (!class_exists('PostgreSQLConnection', false)) include dirname(__FILE__).'/PostgreSQLConnection.class.php';
if (!class_exists('PreparedStatement', false)) include dirname(__FILE__).'/PreparedStatement.class.php';
if (!class_exists('DDL', false)) include dirname(__FILE__).'/DDL.class.php';

function usage() {
	global $argv;

	fputs(
		STDERR,
		"Usage: php ".basename($argv[0])." [options] [<tableName> [<tableName> ...]\n".
		"    tableName          - Optional table name(s) to process.\n".
		"                         If specified, only these tables will be processed;\n".
		"                         otherwise, all tables in the database will be processed.\n".
		"Options:\n".
		"    -o <outputDir>     - The local directory where the phpdaogen base classes, data\n".
		"                         object classes and DAO class will be saved.\n".
		"    -dialect <dialect> - Select database dialect. Default dialect is mysql.\n".
		"                         Only used when -db option is present.  Ignored otherwise.\n".
		"                         Supported dialects: ".
					implode(', ', DDL::$SUPPORTED_DIALECTS).".\n".
		"    -ddl-dir <ddl-dir> - The path to the ddl directory where all of the YAML DDL files exist.\n".
		"    -db <params>       - The next four command-line parameters will be the\n".
		"                         host (server), username, password and database name.\n".
		"                         This is useful when you want to suppress inserts and\n".
		"                         updates for rows specified in YAML DDL insters which\n".
		"                         already exist and contain the correct values.\n".
		"    -ncbc      - Do not copy phpdaogen base classes to outputDir.\n".
		"    -noabstract - Don't generate abstract data object and DAO classes.\n"
	);
	exit(1);
}

$outputDir = '';
$allowedTableNames = array();

$ddlDir = '';
$dialect = 'mysql';
$haveDB = false;
$dbServer = '';
$dbUsername = '';
$dbPassword = '';
$dbDatabase = '';

$copyBaseClasses = true;
$createAbstractClasses = true;

for ($ai = 1; $ai < $argc; $ai++) {
	$arg = $argv[$ai];
	if ( (strlen($arg) > 0) && ($arg[0] == '-') ) {
		switch ($arg) {
		case '-o':
			$ai++;
			if ($ai >= $argc) {
				fprintf(STDERR, "Missing output directory for -o option.\n");
				usage();
				exit(1);
			}
			$outputDir = $argv[$ai];
			break;
		case '-dialect':
			$ai++;
			if ($ai >= $argc) {
				fprintf(STDERR, "Missing database dialect for -dialect option.\n");
				usage();
				exit(1);
			}
			$arg = $argv[$ai];
			if (!in_array($arg, DDL::$SUPPORTED_DIALECTS)) {
				fprintf(STDERR, "Unsupported SQL dialect.\n");
				usage();
				exit(1);
			}
			$dialect = $arg;
			break;
		case '-ddl-dir':
			$ai++;
			if ($ai >= $argc) {
				fprintf(STDERR, "Missing DDL director for -ddl-di option.\n");
				usage();
				exit(1);
			}
			$arg = $argv[$ai];
			$ddlDir = $arg;
			break;
		case '-db':
			$ai++;
			if (($ai+4) > $argc) {
				fprintf(STDERR, "Missing some or all database connection parameters for -db option.\n");
				usage();
				exit(1);
			}
			$haveDB = true;
			$dbServer = $argv[$ai++];
			$dbUsername = $argv[$ai++];
			$dbPassword = $argv[$ai++];
			$dbDatabase = $argv[$ai];
			break;
		case '-ncbc':
			$copyBaseClasses = false;
			break;
		case '-noabstract':
			$createAbstractClasses = false;
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

	$allowedTableNames[] = $arg;
}

if ($haveDB) {
	if ($ddlDir != '') {
		fprintf(STDERR, "Cannot specify both -db and -ddl-dir.\n");
		usage();
	}
} else {
	if ($ddlDir == '') {
		fprintf(STDERR, "Either -db or -ddl-dir is required.\n");
		usage();
	}
}

if ($outputDir == '') {
	fprintf(STDERR, "Missing -o <outputDir>.\n");
	usage();
}

///echo "haveDB=$haveDB\n";
///echo "dbServer=$dbServer\n";
///echo "dbUsername=$dbUsername\n";
///echo "dbPassword=$dbPassword\n";
///echo "dbDatabase=$dbDatabase\n";
///echo "outputDir=$outputDir\n";
///echo "allowedTableNames=".implode(',', $allowedTableNames)."\n";
///echo "copyBaseClasses=$copyBaseClasses\n";

$scriptDir = dirname($argv[0]);

@mkdir($outputDir, 0755, true);

if ($copyBaseClasses) {
	echo "Copying base classes...\n";
	foreach (glob($scriptDir.'/*.interface.php') as $srcFile) {
		copy($scriptDir.'/'.basename($srcFile), $outputDir.'/'.basename($srcFile));
	}
	foreach (glob($scriptDir.'/*.class.php') as $srcFile) {
		copy($scriptDir.'/'.basename($srcFile), $outputDir.'/'.basename($srcFile));
	}
	echo "Copying base classes done.\n";
}

//echo "includeDirnameCount=$includeDirnameCount\n";
//echo "includeClassPathPrefix=$includeClassPathPrefix\n";

if ($haveDB) {
	switch ($dialect) {
	case 'mysql':
		$db = new MySQLiConnection($dbServer, $dbUsername, $dbPassword, $dbDatabase);
		break;
	case 'pgsql':
		$db = new PostgreSQLConnection($dbServer, $dbUsername, $dbPassword, $dbDatabase);
		break;
	default:
		fprintf(STDERR, "Unsupported dialect: %s\n", $dialect);
		exit(1);
	}
	$loader = new ConnectionDDLLoader();
	$ddl = $loader->loadDDL($db, false, $allowedTableNames);
	$db->close();
	unset($db);
} else {
	// Load new DDL from DDL file(s).
	$aggregateDDL = new DDL();
	$res = YAMLDDLParser::loadAllDDLFiles($ddlDir, $aggregateDDL, $allowedTableNames);
	if ($res != 0) exit($res);
	$ddl = $aggregateDDL;
	unset($aggregateDDL);
}
///print_r($ddl);

$generator = new DAOClassGenerator();

function file_put_contents_if_changed($filename, $contents) {
	if ((!file_exists($filename)) || (file_get_contents($filename) != $contents)) {
		return file_put_contents($filename, $contents);
	}
	return strlen($contents);
}

foreach ($ddl->getAllTableNames() as $tableName) {
	echo "Processing $tableName...\n";

	$concreteTableClassName = ucfirst($tableName);

	if ($createAbstractClasses) {
		@mkdir($outputDir.'/abstract', 0777, true);
		file_put_contents_if_changed(
			"$outputDir/abstract/{$concreteTableClassName}Abstract.class.php",
			$generator->generateDataClass($ddl, $tableName, true)
		);
		$fn = "$outputDir/{$concreteTableClassName}.class.php";
		if (!file_exists($fn)) {
			file_put_contents_if_changed($fn, $generator->generateStubDataClass($ddl, $tableName));
		}

		file_put_contents_if_changed(
			"$outputDir/abstract/{$concreteTableClassName}DAOAbstract.class.php",
			$generator->generateDAOClass($ddl, $tableName, true)
		);
		$fn = "$outputDir/{$concreteTableClassName}DAO.class.php";
		if (!file_exists($fn)) {
			file_put_contents_if_changed($fn, $generator->generateStubDAOClass($ddl, $tableName));
		}
	} else {
		@mkdir($outputDir, 0777, true);
		file_put_contents_if_changed(
			"$outputDir/$concreteTableClassName.class.php",
			$generator->generateDataClass($ddl, $tableName)
		);
		file_put_contents_if_changed(
			"$outputDir/{$concreteTableClassName}DAO.class.php",
			$generator->generateDAOClass($ddl, $tableName)
		);
	}
}

exit(0);
