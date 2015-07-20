<?php
// AbstractINIConnectionFactory.class.php
// Copyright (c) 2010-2014 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('Connection', false)) include(dirname(__FILE__).'/Connection.class.php');
if (!class_exists('PreparedStatement', false)) include(dirname(__FILE__).'/PreparedStatement.class.php');

abstract class AbstractINIConnectionFactory {
	// This must be set in the implementing class.
	public static $INI_FILE;

	public static function getConnection() {
		if (@file_exists(self::$INI_FILE)) {
			$cfg = parse_ini_file(self::$INI_FILE);
			if (($cfg === false) || (!is_array($cfg))) $cfg = array();
		} else {
			$cfg = array();
		}

		$connectionClass = isset($cfg['connectionClass']) ? (string)$cfg['connectionClass'] : 'MySQLiConnection';
		$server = isset($cfg['server']) ? (string)$cfg['server'] : 'localhost';
		$username = isset($cfg['username']) ? (string)$cfg['username'] : 'root';
		$password = isset($cfg['password']) ? (string)$cfg['password'] : '';
		$database = isset($cfg['database']) ? (string)$cfg['database'] : '';

		if (!class_exists($connectionClass, false)) {
			include dirname(__FILE__).'/'.$connectionClass.'.class.php';
		}

		return new $connectionClass($server, $username, $password, $database);
	}
}
