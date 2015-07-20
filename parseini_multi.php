<?php
include dirname(dirname(__FILE__)).'/phpdaogen/AbstractINIMultiDatabaseConnectionFactory.class.php';
include dirname(dirname(__FILE__)).'/phpdaogen/DDL.class.php';

if (($argc < 3) || ($argc > 4)) {
	fprintf(STDERR, "Please specify a single INI filename, the path to the ddl directory where the YAML schema files exist, and an optional secondary database name.\n");
	exit(1);
}

$connectionParamsByName = AbstractINIMultiDatabaseConnectionFactory::loadDatabaseIniFile($argv[1]);
$ddldir = $argv[2];
$connectionName = ($argc >= 4) ? trim($argv[3]) : '';

$aggregateDDL = new DDL();
if (($res = YAMLDDLParser::loadAllDDLFiles(realpath($ddldir), $aggregateDDL)) != 0) {
	return $res;
}

$errorMsgs = AbstractINIMultiDatabaseConnectionFactory::validateDatabaseIniConfiguration($connectionParamsByName, $aggregateDDL);
if (!empty($errorMsgs)) {
	foreach ($errorMsgs as $errorMsg) {
		fputs(STDERR, $errorMsg);
		fputs(STDERR, "\n");
	}
	exit(20);
}

$params = AbstractINIMultiDatabaseConnectionFactory::getConnectionParams($connectionName, null, null, $connectionParamsByName);
foreach ($params as $key=>$val) {
	echo "ini_${key}=".escapeshellarg($val)."\n";
}

exit(0);
