<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// PreparedStatement.class.php
// Copyright (c) 2010-2012 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

class PreparedStatement {
	private $sqlPieces;
	private $params = array();
	private $paramsAreBinary = array();
	private $paramIdx = 0;
	public $selectOffset = 0, $selectLimit = 0;

	public function __construct($sql, $selectOffset = 0, $selectLimit = 0, $isRawSQL = false) {
		$sql = trim($sql, " \t\n\r\x00\x0b;");
		if ($isRawSQL) {
			$this->sqlPieces = array($sql);
		} else {
			$this->sqlPieces = explode('?', $sql);
		}
		$this->selectOffset = max(0, (int)$selectOffset);
		$this->selectLimit = max(0, (int)$selectLimit);
	}

	public function clearParams() {
		$this->params = array();
		$this->paramsAreBinary = array();
		$this->paramIdx = 0;
	}

	public function setBoolean($val) {
		$this->params[$this->paramIdx] = ($val === null) ? null : (boolean)$val;
		$this->paramsAreBinary[$this->paramIdx] = false;
		$this->paramIdx++;
	}

	public function setInt($val) {
		$this->params[$this->paramIdx] = ($val === null) ? null : (int)$val;
		$this->paramsAreBinary[$this->paramIdx] = false;
		$this->paramIdx++;
	}

	public function setFloat($val) {
		$this->params[$this->paramIdx] = ($val === null) ? null : (float)$val;
		$this->paramsAreBinary[$this->paramIdx] = false;
		$this->paramIdx++;
	}

	public function setDouble($val) {
		$this->params[$this->paramIdx] = ($val === null) ? null : (double)$val;
		$this->paramsAreBinary[$this->paramIdx] = false;
		$this->paramIdx++;
	}

	public function setString($val) {
		$this->params[$this->paramIdx] = ($val === null) ? null : (string)$val;
		$this->paramsAreBinary[$this->paramIdx] = false;
		$this->paramIdx++;
	}

	public function setBinary($val) {
		$this->params[$this->paramIdx] = ($val === null) ? null : (string)$val;
		$this->paramsAreBinary[$this->paramIdx] = true;
		$this->paramIdx++;
	}

	public function toSQL($connection) {
		if (count($this->params) != (count($this->sqlPieces)-1)) {
			throw new Exception(sprintf(
				'PreparedStatement contains %d placeholder(s) but %d parameter(s)',
				count($this->sqlPieces)-1,
				count($this->params)
			));
		}
		$sql = $this->sqlPieces[0];
		for ($i = 1, $j = 0, $n = count($this->sqlPieces); $i < $n; $i++, $j++) {
			$sql .=
				$connection->encode($this->params[$j], $this->paramsAreBinary[$j]) .
				$this->sqlPieces[$i];
		}
		if (($limitClause = $connection->getSelectLimitClause($sql, $this->selectOffset, $this->selectLimit)) != '') {
			$sql .= $limitClause;
		}
		return $sql;
	}

    // Get an array of the SQL pieces.  Each of the pieces is separated by a ? placeholder in the original SQL query template.
    public function getSQLPieces() { return $this->sqlPieces; }
    // Get an array of the parameters to be inserted into the query.
    public function getParams() { return $this->params; }
    // Get an array of booleans which indicate whether each string parameter is a binary string.
    public function getParamsAreBinary() { return $this->paramsAreBinary; }
    // Get the select offset (number of initial rows to skip in the query).
    public function getSelectOffset() { return $this->selectOffset; }
    // Get the select limit (maximum number of rows to return from the query).
    public function getSelectLimit() { return $this->selectLimit; }
}
