<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// MySQLConnection.class.php
// Copyright (c) 2010-2014 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('Connection', false)) include(dirname(__FILE__).'/Connection.class.php');

class MySQLConnection extends Connection {
	private $server, $username, $password, $database;

	private $conn = false;
	private $transactionDepth = 0;
	private $transactionRolledBack = false;
	private $updatedRowCount = 0;

	public function MySQLConnection($server, $username, $password, $database) {
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
	} // MySQLConnection()

	public function open() {
		if ($this->conn !== false) {
			throw new Exception('A connection is already open');
		}

		$this->transactionDepth = 0;
		$this->transactionRolledBack = false;
		$this->updatedRowCount = 0;

		// client_flags must be set to 128 to enable "load data local" functionality.
		if (($this->conn = mysql_connect($this->server, $this->username, $this->password, true, 128)) === false) {
			throw new Exception('Database connection failed');
		}
		mysql_select_db($this->database, $this->conn);
	} // open()

	public function close() {
		$this->transactionDepth = 0;
		$this->transactionRolledBack = false;

		if ($this->conn !== false) {
			$cn = $this->conn;
			$this->conn = false;
			mysql_close($cn);
		}
	} // close()

	public function isOpen() {
		return ($this->conn !== false) ? true : false;
	} // isOpen()

	public function getDialect() {
		return 'mysql';
	} // getDialect()

	public function encode($val, $encodeAsBinary = false) {
		if ($val === null) return 'NULL';
		if ($encodeAsBinary) {
			if ($val == '') {
				return "''";
			} else {
				$arrData = unpack("H*hex", $val);
				return '0x'.$arrData['hex'];
			}
		}
		if (is_bool($val)) return $val ? '1' : '0';
		if (is_string($val)) {
			$this->lazyOpen();
			return "'".mysql_real_escape_string($val, $this->conn)."'";
		}
		return (string)$val;
	} // encode()

	public function executeUpdate($preparedStatement) {
		$this->lazyOpen();
		$this->updatedRowCount = 0;
		$sql = $preparedStatement->toSQL($this);
		$result = mysql_unbuffered_query($sql, $this->conn);
		if ( ($this->throwExceptionOnFailedQuery) && ($result === false) ) {
			throw new Exception(
				'MySQL Error '.mysql_errno($this->conn).' '.mysql_error($this->conn).
				($this->showSQLInExceptions ? (': '.$sql) : '').
				(isset($_SERVER['REQUEST_URI']) ? ('   page: '.$_SERVER['REQUEST_URI']) : '')
			);
		}
		if ( ($result === true) || ($result === false) ) {
			$this->updatedRowCount = mysql_affected_rows($this->conn);
			return $result;
		}
		// This looks like a query.  Better free the result set.
		mysql_free_result($result, $this->conn);
		$this->updatedRowCount = mysql_affected_rows($this->conn);
		return true;
	} // executeUpdate()

	public function getUpdatedRowCount() {
		return $this->updatedRowCount;
	} // getUpdatedRowCount()

	public function executeQuery($preparedStatement) {
		$this->lazyOpen();
		$sql = $preparedStatement->toSQL($this);
		$result = mysql_unbuffered_query($sql, $this->conn);
		if ( ($this->throwExceptionOnFailedQuery) && ($result === false) ) {
			throw new Exception(
				'MySQL Error '.mysql_errno($this->conn).' '.mysql_error($this->conn).
				($this->showSQLInExceptions ? (': '.$sql) : '').
				(isset($_SERVER['REQUEST_URI']) ? ('   page: '.$_SERVER['REQUEST_URI']) : '')
			);
		}
		if ($result === false) return $result;
		// This looks like an update.
		// Better return 0 so callers expecting a result set don't blow up.
		if ($result === true) return 0;
		return $result;
	} // executeQuery()

	public function fetchArray($resultSetIdentifier, $freeResultBeforeReturn = false) {
		$result = mysql_fetch_assoc($resultSetIdentifier);
		if ($freeResultBeforeReturn) $this->freeResult($resultSetIdentifier);
		return $result;
	} // fetchArray()

	public function fetchObject($resultSetIdentifier, $freeResultBeforeReturn = false) {
		$result = mysql_fetch_object($resultSetIdentifier);
		if ($freeResultBeforeReturn) $this->freeResult($resultSetIdentifier);
		return $result;
	} // fetchObject()

	public function freeResult($resultSetIdentifier) {
		if ($resultSetIdentifier === false) {
			throw new Exception(
				'Attempt to free invalid result set identifier: (boolean) false'.
				(isset($_SERVER['REQUEST_URI']) ? (' page: '.$_SERVER['REQUEST_URI']) : '')
			);
			return false;
		}
		$result = mysql_free_result($resultSetIdentifier);
		if ( ($this->throwExceptionOnFailedFreeResult) && ($result === false) ) {
			throw new Exception(
				'Attempt to free invalid result set identifier: '.$resultSetIdentifier.
				(isset($_SERVER['REQUEST_URI']) ? (' page: '.$_SERVER['REQUEST_URI']) : '')
			);
		}
		return $result;
	} // freeResult()

	public function getLastInsertId() {
		$result = ($this->conn !== false) ? mysql_insert_id($this->conn) : 0;
		if ( ($result === false) || ($result == 0) ) return false;
		return $result;
	} // getLastInsertId()

	public function beginTransaction() {
		$this->lazyOpen();
		$this->transactionDepth++;
		if ($this->transactionDepth == 1) {
			$this->transactionRolledBack = false;
			$result = mysql_unbuffered_query('start transaction', $this->conn);
			if ($result !== false) $result = true;
		} else {
			$result = true;
		}
		return $result;
	} // beginTransaction()

	public function commitTransaction() {
		if ($this->transactionDepth > 0) {
			$result = true;
			$this->transactionDepth--;
			if ($this->transactionDepth == 0) {
				if ($this->transactionRolledBack) {
					$result = mysql_unbuffered_query('rollback', $this->conn);
				} else {
					$result = mysql_unbuffered_query('commit', $this->conn);
				}
				if ($result !== false) $result = true;
			}
		} else {
			$result = false;
		}
		return $result;
	} // commitTransaction()

	public function rollbackTransaction() {
		if ($this->transactionDepth > 0) {
			$this->transactionRolledBack = true;
			$result = true;
			$this->transactionDepth--;
			if ($this->transactionDepth == 0) {
				$result = mysql_unbuffered_query('rollback', $this->conn);
				if ($result !== false) $result = true;
			}
		} else {
			$result = false;
		}
		return $result;
	} // rollbackTransaction()

	public function getSelectLimitClause($sql, $selectOffset, $selectLimit) {
		if (($selectOffset > 0) || ($selectLimit > 0)) {
			$selectOffset = max(0, (int)$selectOffset);
			$selectLimit = max(0, (int)$selectLimit);
			if ((strlen($sql) >= 7) && (strncasecmp($sql, 'select', 6) == 0) && (ctype_space($sql[6]))) {
				if ($selectLimit > 0) return sprintf(' limit %d,%d', $selectOffset, $selectLimit);
				return sprintf(' limit %d,18446744073709551615', $selectOffset);
			} else {
				throw new Exception('selectOffset and selectLimit cannot be applied to the specified SQL statement');
			}
		}
		return '';
	} // getSelectLimitClause()
}
