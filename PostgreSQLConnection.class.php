<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// PostgreSQLConnection.class.php
// Copyright (c) 2010-2014 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('Connection', false)) include(dirname(__FILE__).'/Connection.class.php');

class PostgreSQLConnection extends Connection {
	private $server, $username, $password, $database;

	private $conn = false;
	private $transactionDepth = 0;
	private $transactionRolledBack = false;
	private $updatedRowCount = 0;

	public function __construct($server, $username, $password, $database) {
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;

		$this->likeOperator = 'ilike';
	} // PostgreSQLConnection()

	public function open() {
		if ($this->conn !== false) {
			throw new Exception('A connection is already open');
		}

		$this->transactionDepth = 0;
		$this->transactionRolledBack = false;
		$this->updatedRowCount = 0;

		if (($colonIdx = strpos($this->server, ':')) !== false) {
			$port = trim(substr($this->server, $colonIdx+1));
			$host = trim(substr($this->server, 0, $colonIdx));
		} else {
			$host = $this->server;
			$port = '';
		}
		$connectionString = "host=$host";
		if ($port != '') $connectionString .= " port=$port";
		$connectionString .= " dbname={$this->database} user={$this->username} password={$this->password}";
		if (($this->conn = pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW)) === false) {
			throw new Exception('Database connection failed');
		}
	} // open()

	public function close() {
		$this->transactionDepth = 0;
		$this->transactionRolledBack = false;

		if ($this->conn !== false) {
			$cn = $this->conn;
			$this->conn = false;
			pg_close($cn);
		}
	} // close()

	public function isOpen() {
		return ($this->conn !== false) ? true : false;
	} // isOpen()

	public function getDialect() {
		return 'pgsql';
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
			return "'".pg_escape_string($this->conn, $val)."'";
		}
		return (string)$val;
	} // encode()

	public function executeUpdate($preparedStatement) {
		$this->lazyOpen();
		$this->updatedRowCount = 0;
		$sql = $preparedStatement->toSQL($this);
		$result = pg_query($this->conn, $sql);
		if ($result === false) {
			// Update or query failed.
			if ($this->throwExceptionOnFailedQuery) {
				throw new Exception(
					'PostgreSQL Error '.pg_last_error($this->conn).
					($this->showSQLInExceptions ? (': '.$sql) : '').
					(isset($_SERVER['REQUEST_URI']) ? ('   page: '.$_SERVER['REQUEST_URI']) : '')
				);
			}
			$this->updatedRowCount = 0;
			return $result;
		}
		$this->updatedRowCount = pg_affected_rows($result);
		pg_free_result($result);
		return true;
	} // executeUpdate()

	public function getUpdatedRowCount() {
		return $this->updatedRowCount;
	} // getUpdatedRowCount()

	public function executeQuery($preparedStatement) {
		$this->lazyOpen();
		$sql = $preparedStatement->toSQL($this);
		$result = pg_query($this->conn, $sql);
		if ($result === false) {
			// Query failed.
			if ($this->throwExceptionOnFailedQuery) {
				throw new Exception(
					'PostgreSQL Error '.pg_last_error($this->conn).
					($this->showSQLInExceptions ? (': '.$sql) : '').
					(isset($_SERVER['REQUEST_URI']) ? ('   page: '.$_SERVER['REQUEST_URI']) : '')
				);
			}
			return $result;
		}
		return $result;
	} // executeQuery()

	public function fetchArray($resultSetIdentifier, $freeResultBeforeReturn = false) {
		$result = pg_fetch_assoc($resultSetIdentifier);
		if ($freeResultBeforeReturn) $this->freeResult($resultSetIdentifier);
		return $result;
	} // fetchArray()

	public function fetchObject($resultSetIdentifier, $freeResultBeforeReturn = false) {
		$result = pg_fetch_object($resultSetIdentifier);
		if ($freeResultBeforeReturn) $this->freeResult($resultSetIdentifier);
		return $result;
	} // fetchObject()

	public function freeResult($resultSetIdentifier) {
		$result = pg_free_result($resultSetIdentifier);
		if ($resultSetIdentifier === false) $retval = false;
		if ( ($this->throwExceptionOnFailedFreeResult) && ($result === false) ) {
			throw new Exception(
				'Attempt to free invalid result set identifier: '.$resultSetIdentifier.
				(isset($_SERVER['REQUEST_URI']) ? (' page: '.$_SERVER['REQUEST_URI']) : '')
			);
		}
		return $result;
	} // freeResult()

	public function getLastInsertId() {
		if ($this->conn === false) return 0;
		$rs = @pg_query($this->conn, 'select lastval() as id');
		if ($rs !== false) {
			$row = @pg_fetch_assoc($rs);
			@pg_free_result($rs);
			if ( ($row) && (isset($row['id'])) ) return $row['id'];
		}
		return false;
	} // getLastInsertId()

	public function beginTransaction() {
		$this->lazyOpen();
		$this->transactionDepth++;
		if ($this->transactionDepth == 1) {
			$this->transactionRolledBack = false;
			$retval = pg_query($this->conn, 'start transaction');
			if ($retval !== false) $retval = true;
		} else {
			$retval = true;
		}
		return $retval;
	} // beginTransaction()

	public function commitTransaction() {
		if ($this->transactionDepth > 0) {
			$retval = true;
			$this->transactionDepth--;
			if ($this->transactionDepth == 0) {
				if ($this->transactionRolledBack) {
					$retval = pg_query($this->conn, 'rollback');
				} else {
					$retval = pg_query($this->conn, 'commit');
				}
				if ($retval !== false) $retval = true;
			}
		} else {
			$retval = false;
		}
		return $retval;
	} // commitTransaction()

	public function rollbackTransaction() {
		if ($this->transactionDepth > 0) {
			$this->transactionRolledBack = true;
			$retval = true;
			$this->transactionDepth--;
			if ($this->transactionDepth == 0) {
				$retval = pg_query($this->conn, 'rollback');
				if ($retval !== false) $retval = true;
			}
		} else {
			$retval = false;
		}
		return $retval;
	} // rollbackTransaction()

	public function getSelectLimitClause($sql, $selectOffset, $selectLimit) {
		if (($selectOffset > 0) || ($selectLimit > 0)) {
			$selectOffset = max(0, (int)$selectOffset);
			$selectLimit = max(0, (int)$selectLimit);
			if ((strlen($sql) >= 7) && (strncasecmp($sql, 'select', 6) == 0) && (ctype_space($sql[6]))) {
				$clause = '';
				if ($selectOffset > 0) {
					$clause .= sprintf(' offset %d', $selectOffset);
				}
				if ($selectLimit > 0) {
					$clause .= sprintf(' limit %d', $selectLimit);
				}
				return $clause;
			} else {
				throw new Exception('selectOffset and selectLimit cannot be applied to the specified SQL statement');
			}
		}
		return '';
	} // getSelectLimitClause()
}
