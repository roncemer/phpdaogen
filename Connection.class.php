<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// Connection.class.php
// Copyright (c) 2010-2014 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

abstract class Connection {
	// Set this to true to throw an Exception if a query or update fails.
	// The Exception will include a stack trace to help locate the offending SQL query.
	public $throwExceptionOnFailedQuery = true;
	// Set this to true to show the SQL query in any exception which is thrown when executing
	// a query or update.
	public $showSQLInExceptions = false;
	// Set this to true to throw an Exception if a call to freeResult() fails to free
	// the result set (typically due to an invalid result set identifier, most often
	// caused by an invalid SQL query).
	// The Exception will include a stack trace to help locate the source of the problem.
	public $throwExceptionOnFailedFreeResult = true;
	// The "like" operator to use for this database engine.
	// If this database has a case-insensitive "like" operator (e.g. "ilike" in postgresql),
	// this is it.  If not, this is the case-sensitive "like" operator.
	public $likeOperator = 'like';
	// true if this database engine supports case-insensitve "like" comparisons;
	// false if it does not.
	public $hasCaseInsensitiveLike = true;

	// Open a connection.
	// Throws Exception if an error is encountered while opening a new connection,
	// or if a connection is already open.
	public abstract function open();

	// Close a connection.
	public abstract function close();

	// Returns true if a connection is currently open; false if not.
	public abstract function isOpen();

	// Lazily open a connection.
	// If a connection is not currently open, open a new connection.
	// Throws Exception if an error is encountered while opening a new connection.
	protected function lazyOpen() {
		if (!$this->isOpen()) {
			$this->open();
		}
	} // lazyOpen()

	// Get the SQL dialect, as a string.  Example: 'mysql', 'pgsql', 'mssql'.
	public abstract function getDialect();

	// Encode a value for SQL usage.
	// $val is any allowable type (string, int, float/double, boolean, null).
	// $encodeAsBinary is true to encode as a binary column; false to encode as whatever type
	// the value is.  Optional.  Defaults to false.
	// Returns the SQL representation of the value.
	public abstract function encode($val, $encodeAsBinary = false);

	// Execute an updating query.
	// Returns true if success; false if failure.
	public abstract function executeUpdate($preparedStatement);

	// Following a successful call to executeUpdate(), this returns the number of rows
	// which were affected by the update.
	public abstract function getUpdatedRowCount();

	// Execute a query and return a result set.
	// Returns a result set identifier which can be used fetch the result rows.
	public abstract function executeQuery($preparedStatement);

	// Fetch the next row of a result set identifier as an associative array.
	// Returns null if there are no more rows.
	// If $freeResultBeforeReturn is true, frees the result set before returning.
	public abstract function fetchArray($resultSetIdentifier, $freeResultBeforeReturn = false);

	// Fetch the next row of a result set identifier as an object.
	// Returns null if there are no more rows.
	// If $freeResultBeforeReturn is true, frees the result set before returning.
	public abstract function fetchObject($resultSetIdentifier, $freeResultBeforeReturn = false);

	// Fetch the remaining rows of a result set identifier as an array of associative arrays.
	// If $freeResultBeforeReturn is true, frees the result set before returning.
	public function fetchAllArrays($resultSetIdentifier, $freeResultBeforeReturn = false) {
		$rows = array();
		while ($row = $this->fetchArray($resultSetIdentifier, false)) $rows[] = $row;
		if ($freeResultBeforeReturn) $this->freeResult($resultSetIdentifier);
		return $rows;
	} // fetchAllArrays()

	// Fetch the remaining rows of a result set identifier as an array of objects.
	// If $freeResultBeforeReturn is true, frees the result set before returning.
	public function fetchAllObjects($resultSetIdentifier, $freeResultBeforeReturn = false) {
		$rows = array();
		while ($row = $this->fetchObject($resultSetIdentifier, false)) $rows[] = $row;
		if ($freeResultBeforeReturn) $this->freeResult($resultSetIdentifier);
		return $rows;
	} // fetchAllObjects()

	// Free a result set identifier.
	// Returns true if success; false if failure.
	public abstract function freeResult($resultSetIdentifier);

	// Get the last insert Id.
	// Returns false if none.
	public abstract function getLastInsertId();

	// Begin a transaction.
	// For database engines which don't support nested transactions, only
	// the first transaction begun will be honored.  In this case, a counter
	// will be used to keep track of the virtual transaction depth, and only
	// transitions from 0 to 1 and 1 to 0 will actually take any action.
	// Returns true if success; false if failure.
	public abstract function beginTransaction();

	// Commit a transaction.
	// For database engines which don't support nested transactions, only
	// the first transaction begun will be honored.  In this case, a counter
	// will be used to keep track of the virtual transaction depth, and only
	// transitions from 0 to 1 and 1 to 0 will actually take any action.
	// Returns true if success; false if failure.
	public abstract function commitTransaction();

	// Rollback a transaction.
	// For database engines which don't support nested transactions, only
	// the first transaction begun will be honored.  In this case, a counter
	// will be used to keep track of the virtual transaction depth, and only
	// transitions from 0 to 1 and 1 to 0 will actually take any action.
	// Returns true if success; false if failure.
	public abstract function rollbackTransaction();

	// Given an SQL statement, an offset (# of initial rows to skip) and a limit
	// (max # of rows to return, or 0 for unlimited), return a limit clause in the
	// connection's dialect, to be appended to the SQL statement.
	// The PreparedStatement class calls this function from its toSQL() function.
	// Throws an Exception if the SQL statement does not begin with "select".
	public abstract function getSelectLimitClause($sql, $selectOffset, $selectLimit);
}
