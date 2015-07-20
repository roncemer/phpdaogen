<?php
// AbstractINIMultiDatabaseConnectionFactory.class.php
// Copyright (c) 2010-2015 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('Connection', false)) include(dirname(__FILE__).'/Connection.class.php');
if (!class_exists('PreparedStatement', false)) include(dirname(__FILE__).'/PreparedStatement.class.php');

abstract class AbstractINIMultiDatabaseConnectionFactory {
	// This must be set in the implementing class.
	public static $INI_FILE = '';

	protected static $connectionParamsByName = null;

	// If $connectionName !== null, get the specified connection.
	//   Pass an empty string ('') to get the default connection.
	//   If $connectionName === null, get the connection which matches the vhost and/or request URI.
	// $vhost can be a virtual host, or null (or an empty string) to get it from the current request
	//   (won't work when running from CLI).
	//   Ignored when $connectionName !== null.
	// $uri can be a request URI, or null (or an empty string) to get it from the current request
	//   (won't work when running from CLI).
	//   Ignored when $connectionName !== null.
	// $connectionParamsByName can be null to use the default, lazily loaded database.ini,
	//   or can be the result of a call to loadDatabaseIniFile() with a different ini file.
	public static function getConnection($connectionName = null, $vhost = null, $uri = null, $connectionParamsByName = null) {
		$params = self::getConnectionParams($connectionName, $vhost, $uri, $connectionParamsByName);
		$connectionClass = $params['connectionClass'];
		if (!class_exists($connectionClass, false)) {
			include dirname(__FILE__).'/'.$connectionClass.'.class.php';
		}
		return new $connectionClass($params['server'], $params['username'], $params['password'], $params['database']);
	} // getConnection()

	// If $connectionName !== null, get the parameters for the specified connection.
	//   Pass an empty string ('') to get the parameters for the default connection.
	//   If $connectionName === null, get the parameters for the connection which matches the vhost and/or request URI.
	// $vhost can be a virtual host, or null (or an empty string) to get it from the current request
	//   (won't work when running from CLI).
	//   Ignored when $connectionName !== null.
	// $uri can be a request URI, or null (or an empty string) to get it from the current request
	//   (won't work when running from CLI).
	//   Ignored when $connectionName !== null.
	// $connectionParamsByName can be null to use the default, lazily loaded database.ini,
	//   or can be the result of a call to loadDatabaseIniFile() with a different ini file.
	public static function getConnectionParams($connectionName = null, $vhost = null, $uri = null, $connectionParamsByName = null) {
		// If a custom connection-parameters-by-name map is not supplied,
		// lazily load and cache the default map from the config file.
		if ($connectionParamsByName === null) {
			$connectionParamsByName = self::getConnectionParamsByName();
		}

		// If no connection name is supplied, try to select one based on the HTTP request or the passed in $vhost and $uri.
		if ($connectionName === null) {
			// Fall back to the default connection if we can't match any other.
			$connectionName = '';

			// If $vhost and/or $uri were not passed in, try to figure them out from the request.
			if (isset($_SERVER) && isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
				if (($vhost === null) || ($vhost == '')) $vhost = strtolower($_SERVER['HTTP_HOST']);
				if (($uri === null) || ($uri == '')) $uri = $_SERVER['REQUEST_URI'];
			} else {
				if ($vhost === null) $vhost = '';
				if ($uri === null) $uri = '';
			}

			foreach ($connectionParamsByName as $name=>$params) {
				// No need to check the default connection; we fall back to that anyway.
				if ($name == '') continue;

				$isQualified = false;

				// Check the vhost.  If the configured vhost is missing, empty, or *, it matches anything;
				// otherwise, the vhost must either match the configured vhost or be a subdomain of it.
				if (isset($params['vhost']) && ($params['vhost'] != '') && ($params['vhost'] != '*')) {
					$isQualified = true;
					$testVhost = strtolower($params['vhost']);
					$testVhostSub = (($testVhost != '') && ($testVhost[0] != '.')) ? '.'.$testVhost : $testVhost;
					if (($vhost != '') &&
						($vhost != $testVhost) &&
						(substr_compare($vhost, $testVhostSub, -strlen($testVhostSub), strlen($testVhostSub)) != 0)) {
						// The vhost doesn't match, and is not a subdomain of the configured vhost.
						continue;
					}
				}

				// Check the uriPrefix.  If the configured uriPrefix is missing, empty or *, it matches anything;
				// otherwise, the URI must match uriPrefix or begin with it.
				if (isset($params['uriPrefix']) && ($params['uriPrefix'] != '') && ($params['uriPrefix'] != '*')) {
					$isQualified = true;
					$testURIPrefix = strtolower($params['uriPrefix']);
					$testURIPrefixSub = (($testURIPrefix != '') && ($testURIPrefix[strlen($testURIPrefix)-1] != '/')) ?
						$testURIPrefix.'/' : $testURIPrefix;
					if (($uri != '') &&
						($uri != $testURIPrefix) &&
						(substr_compare($uri, $testURIPrefixSub, 0, strlen($testURIPrefixSub)) != 0)) {
						// The uri doesn't match, and it does not begin with the configured uriPrefix.
						continue;
					}
				}

				// Named connections MUST be qualified by vhost or uriPrefix, or both; otherwise, they are ignored.
				if (!$isQualified) continue;

				return $params;
			}
		}

		return isset($connectionParamsByName[$connectionName]) ?
			$connectionParamsByName[$connectionName] : $connectionParamsByName[''];
	} // getConnectionParams()

	// Return an array, indexed by connection name ('' for default connection),
	// where each value is an associative array of connection parameters for
	// that connection.
	public static function getConnectionParamsByName() {
		// Lazily load the database.ini file.
		// Once loaded, cache it for the remainder of the request.
		if (self::$connectionParamsByName === null) {
			self::$connectionParamsByName = self::loadDatabaseIniFile(self::$INI_FILE);
		}
		return self::$connectionParamsByName;
	} // getConnectionParamsByName()

	public static function loadDatabaseIniFile($iniFilename) {
		if (@file_exists($iniFilename)) {
			$cfg = parse_ini_file($iniFilename, true);
			if (($cfg === false) || (!is_array($cfg))) $cfg = array();
		} else {
			$cfg = array();
		}

		$connectionParamsByName = array(
			''=>array(
				'connectionClass'=>'MySQLiConnection',
				'server'=>'localhost',
				'username'=>'root',
				'password'=>'',
				'database'=>'',
				'description'=>'(unknown)',
				'vhost'=>'*',
				'uriPrefix'=>'*',
				'tableToDatabaseMap'=>'',
			)
		);

		$secondaryConnectionNames = array();
		foreach ($cfg as $key=>$val) {
			if ($key == '') continue;
			if (is_array($val)) {
				$secondaryConnectionNames[] = $key;
			} else {
				$connectionParamsByName[''][$key] = (string)$val;
			}
		}
		$connectionParamsByName['']['secondaryConnectionNames'] = implode(',', $secondaryConnectionNames);

		foreach ($cfg as $key=>$val) {
			if ($key == '') continue;
			if (is_array($val)) {
				if (!isset($connectionParamsByName[$key])) {
					$connectionParamsByName[$key] = $connectionParamsByName[''];
					foreach ($val as $key2=>$val2) {
						$connectionParamsByName[$key][$key2] = (string)$val2;
					}
				}
			}
		}

		return $connectionParamsByName;
	} // loadDatabaseIniFile()

	public static function validateDatabaseIniConfiguration($connectionParamsByName, $aggregateDDL) {
		$errorMsgs = array();

		// For each connection, validate that all required parameters are there,
		// validate that paameters which are supposed to match the same parameters
		// in all other connections acually do, validate that no two connections
		// are for the same database.  Build a map of database-name-to-connection-parameters.
		$connectionParamsByDatabaseName = array();
		$requiredParamNames = array('connectionClass', 'server', 'username', 'password', 'database');
		$allMustMatchParamNames = array('connectionClass', 'server', 'username', 'password');
		$prevParams = null;
		foreach ($connectionParamsByName as $name=>$params) {
			$dispname = ($name == '') ? '(default)' : $name;
			foreach ($requiredParamNames as $pn) {
				if ((!isset($params[$pn])) || ($params[$pn] == '')) {
					$errorMsgs[] = sprintf("Missing or empty %s parameter on connection: %s.", $pn, $dispname);
				}
			}
			if ($prevParams !== null) {
				foreach ($allMustMatchParamNames as $pn) {
					if ((!isset($params[$pn])) || ($params[$pn] != $prevParams[$pn])) {
						$errorMsgs[] = sprintf("%s parameter in connection %s does not match other connections.", $pn, $dispname);
					}
				}
			}
			if (isset($connectionParamsByDatabaseName[$params['database']])) {
				$errorMsgs[] = sprintf("Duplicate database parameter in connection: %s.", $dispname);
			} else {
				$connectionParamsByDatabaseName[$params['database']] = $params;
			}

			$prevParams = $params;
		}
		if (!empty($errorMsgs)) return $errorMsgs;

		// For each connection which has tables mapped to a different database,
		// confirm that the target database exists in the connection list, and
		// that the corresponding connection has showInList set to false.
		// Build a list of DDLTableToDatabaseMap instances, mapped by database name.
		$mappingTargetTablesByDatabaseName = array();
		foreach ($connectionParamsByName as $name=>$params) {
			$dispname = ($name == '') ? '(default)' : $name;
			if ((!array_key_exists('tableToDatabaseMap', $params)) || (trim($params['tableToDatabaseMap']) == '')) continue;
			$map = new DDLTableToDatabaseMap(trim($params['tableToDatabaseMap']));
			$mappingTargetTablesByDatabaseName[$params['database']] = $map;

			$nonexistentReferencedDatabaseNames = array();
			$showInListReferencedDatabaseNames = array();
			for ($i = 0, $n = count($aggregateDDL->topLevelEntities); $i < $n; $i++) {
				if ($aggregateDDL->topLevelEntities[$i] instanceof DDLTable) {
					$tbl = $aggregateDDL->topLevelEntities[$i];
					if (($dbname = $map->getDatabase($tbl->group, $tbl->tableName)) !== null) {
						if (!isset($connectionParamsByDatabaseName[$params['database']])) {
							if (!in_array($dbname, $nonexistentReferencedDatabaseNames)) {
								$nonexistentReferencedDatabaseNames[] = $dbname;
								$errorMsgs[] = sprintf("Table mapping in %s connection references nonexistent database: %s.", $dispname, $dbname);
							}
						} else {
							$targetparams = $connectionParamsByDatabaseName[$dbname];
							if (isset($targetparams['showInList']) && (((int)$targetparams['showInList']) != 0)) {
								if (!in_array($dbname, $showInListReferencedDatabaseNames)) {
									$showInListReferencedDatabaseNames[] = $dbname;
									$errorMsgs[] = sprintf("Table mapping in %s connection references database %s; this referenced database cannot have showInList=Yes in its connection parameters.", $dispname, $dbname);
								}
							}
						}
					}
				}
			}
		}

		return $errorMsgs;
	} // validateDatabaseIniConfiguration()

	// Given a connection name, the parsed contents of the database INI configuration file,
	// and the aggregated DDL from all YAML DDL files, get the list of all tables which
	// are mapped from other connections to the specified connection.
	public static function getMapTargetTableNames($connectionName, $connectionParamsByName, $aggregateDDL) {
		$mapTargetTableNames = array();
		if (!isset($connectionParamsByName[$connectionName])) {
			return $mapTargetTableNames;
		}
		$connectionParams = $connectionParamsByName[$connectionName];
		if (!isset($connectionParams['database'])) {
			return $mapTargetTableNames;
		}
		foreach ($connectionParamsByName as $name=>$params) {
			if ($name == $connectionName) continue;
			if (($name == $connectionName) ||
				(!array_key_exists('tableToDatabaseMap', $params)) ||
				(trim($params['tableToDatabaseMap']) == '')) {
				continue;
			}
			$map = new DDLTableToDatabaseMap(trim($params['tableToDatabaseMap']));
			for ($i = 0, $n = count($aggregateDDL->topLevelEntities); $i < $n; $i++) {
				if ($aggregateDDL->topLevelEntities[$i] instanceof DDLTable) {
					$tbl = $aggregateDDL->topLevelEntities[$i];
					$dbname = $map->getDatabase($tbl->group, $tbl->tableName);
					if (($dbname !== null) && ($dbname === $connectionParams['database'])) {
						if (!in_array($tbl->tableName, $mapTargetTableNames)) {
							$mapTargetTableNames[] = $tbl->tableName;
						}
					}
				}
			}
		}
		sort($mapTargetTableNames, SORT_STRING);
		return $mapTargetTableNames;
	} // getMapTargetTableNames()
}
