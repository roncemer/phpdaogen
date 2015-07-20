<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// SQLServerConnection.class.php
// Copyright (c) 2010-2014 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('Connection', false)) include(dirname(__FILE__).'/Connection.class.php');

class SQLServerConnection extends Connection {
	private $server, $username, $password, $database;

	private $conn = false;
	private $transactionDepth = 0;
	private $transactionRolledBack = false;
	private $updatedRowCount = 0;

	public function SQLServerConnection($server, $username, $password, $database) {
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
	} // SQLServerConnection()

	public function open() {
		if ($this->conn !== false) {
			throw new Exception('A connection is already open');
		}

		$this->transactionDepth = 0;
		$this->transactionRolledBack = false;
		$this->updatedRowCount = 0;

		if (($this->conn = odbc_connect(
				'DRIVER={SQL Server};SERVER='.$this->server.';DATABASE='.$this->database,
				$this->username,
				$this->password)) === false) {
			throw new Exception('Database connection failed');
		}
	} // open()

	public function close() {
		$this->transactionDepth = 0;
		$this->transactionRolledBack = false;

		if ($this->conn !== false) {
			$cn = $this->conn;
			$this->conn = false;
			odbc_close($cn);
		}
	} // close()

	public function isOpen() {
		return ($this->conn !== false) ? true : false;
	} // isOpen()

	public function getDialect() {
		return 'mssql';
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
		if (is_string($val)) return "'".str_replace('\'', '\'\'', $s)."'";
		return (string)$val;
	} // encode()

	public function executeUpdate($preparedStatement) {
		$this->lazyOpen();
		$this->updatedRowCount = 0;
		$sql = $preparedStatement->toSQL($this);
		$result = odbc_exec($this->conn, $sql);
		if ( ($this->throwExceptionOnFailedQuery) && ($result === false) ) {
			throw new Exception(
				'Invalid SQL query'.
				($this->showSQLInExceptions ? (': '.$sql) : '').
				(isset($_SERVER['REQUEST_URI']) ? ('   page: '.$_SERVER['REQUEST_URI']) : '')
			);
		}
		if ( ($result === true) || ($result === false) ) {
			$this->updatedRowCount = odbc_num_rows($this->conn);
			return $result;
		}
		// This looks like a query.  Better free the result set.
		odbc_free_result($result);
		$this->updatedRowCount = mysql_affected_rows($this->conn);
		return true;
	} // executeUpdate()

	public function getUpdatedRowCount() {
		return $this->updatedRowCount;
	} // getUpdatedRowCount()

	public function executeQuery($preparedStatement) {
		$this->lazyOpen();
		$sql = $preparedStatement->toSQL($this);
		$result = odbc_exec($this->conn, $sql);
		if ( ($this->throwExceptionOnFailedQuery) && ($result === false) ) {
			throw new Exception(
				'Invalid SQL query'.
				($this->showSQLInExceptions ? (': '.$sql) : '').
				(isset($_SERVER['REQUEST_URI']) ? ('   page: '.$_SERVER['REQUEST_URI']) : '')
			);
		}
		if ($result === false) return $result;
		// This looks like an update.
		// Better return 0 so callers expecting a result set don't blow up.
		if ($result === true) return 0;
		// SQL Server doesn't support offset, only "top" (which is like "limit" but with no offset).
		// So, if $preparedStatement->selectOffset > 0, then we need to skip that many initial rows
		// in order to simulate offset.
		if ($preparedStatement->selectOffset > 0) {
			for ($i = 0; $i < $preparedStatement->selectOffset; $i++) {
				if ($this->fetchObject($result) === false) break;
			}
		}
		return $result;
	} // executeQuery()

	public function fetchArray($resultSetIdentifier, $freeResultBeforeReturn = false) {
		$result = odbc_fetch_array($resultSetIdentifier);
		if ($freeResultBeforeReturn) $this->freeResult($resultSetIdentifier);
		return $result;
	} // fetchArray()

	public function fetchObject($resultSetIdentifier, $freeResultBeforeReturn = false) {
		$result = odbc_fetch_object($resultSetIdentifier);
		if ($freeResultBeforeReturn) $this->freeResult($resultSetIdentifier);
		return $result;
	} // fetchObject()

	public function freeResult($resultSetIdentifier) {
		$retval = odbc_free_result($resultSetIdentifier);
		if ($resultSetIdentifier === false) $retval = false;
		if ( ($this->throwExceptionOnFailedFreeResult) && ($result === false) ) {
			throw new Exception(
				'Attempt to free invalid result set identifier: '.$resultSetIdentifier.
				(isset($_SERVER['REQUEST_URI']) ? (' page: '.$_SERVER['REQUEST_URI']) : '')
			);
		}
		return $retval;
	} // freeResult()

	public function getLastInsertId() {
		if ($this->conn === false) return 0;
		$rs = odbc_exec($this->conn, 'select @@IDENTITY as ID');
		if ($rs !== false) {
			$row = odbc_fetch_array($rs);
			odbc_free_result($rs);
			if ( ($row) && (isset($row['ID'])) ) return $row['ID'];
		}
		return false;
	} // getLastInsertId()

	public function beginTransaction() {
		$this->lazyOpen();
		$this->transactionDepth++;
		if ($this->transactionDepth == 1) {
			$this->transactionRolledBack = false;
			$retval = odbc_exec($this->conn, 'begin transaction txn');
			if ( ($retval !== true) && ($retval !== false) ) {
				@odbc_free_result($retval);
				$retval = true;
			}
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
					$retval = odbc_exec($this->conn, 'rollback transaction txn');
				} else {
					$retval = odbc_exec($this->conn, 'commit transaction txn');
				}
				if ( ($retval !== true) && ($retval !== false) ) {
					@odbc_free_result($retval);
					$retval = true;
				}
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
				$retval = odbc_exec($this->conn, 'rollback transaction txn');
				if ( ($retval !== true) && ($retval !== false) ) {
					@odbc_free_result($retval);
					$retval = true;
				}
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
				return
					substr($sql, 0, 6).
					sprintf(' top %d', $selectOffset+$selectLimit).
					substr($sql, 6);
			} else {
				throw new Exception('selectOffset and selectLimit cannot be applied to the specified SQL statement');
			}
		}
		return '';
	} // getSelectLimitClause()
}
