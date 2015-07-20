<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// ChildRowUpdater.class.php
// Copyright (c) 2010 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('Connection', false)) include(dirname(__FILE__).'/Connection.class.php');
if (!class_exists('PreparedStatement', false)) include(dirname(__FILE__).'/PreparedStatement.class.php');

class ChildRowUpdater {
	// Update a set of child rows for a parent row.
	// Child rows which exist in the child table but don't exist in the $childRows array
	// (as identified by the list of uniquely identifying columns specified in the
	// $uniqueIdentifyingColumnNames array) will be deleted.
	// Child rows which exist in both the $childRows array and the child table, will be
	// updated.
	// Child rows which exist in the $childRows array but don't exist in the child table,
	// will be inserted.
	//
	// $db is an open database Connection instance.
	//
	// $childDataClassName is the name of the data class for the child table.  This is
	// NOT the name of the DAO class.  The DAO class will be derived from this name.
	// The DAO class must have already been included, as it will NOT be included
	// automatically.
	//
	// $childRows is an array containing either objects or associative arrays, one entry
	// per child row.  These will be automatically converted to data objects of the
	// $childDataClassName class.
	//
	// $commonIdentifiers is an associative array whose keys are the column names within
	// the child table which relate that child table to the parent table, and whose values
	// are the corresponding values which identify the parent row.  These would typically
	// comprise the foreign key of the child table and the primary key of the parent table.
	// NOTE: This array must NOT reference any binary-type column(s).
	//
	// $uniqueIdentifyingColumnNames is a linear array which contains the column names
	// within the child table which uniquely identify a child row.  This would typically
	// describe the primary key of the child table.
	//
	// $forcedChildRowValues is an associative array whose keys are column names within
	// the child table, and whose values are forced values for those columns.  Any child
	// row which is inserted or updated will have these common values assigned their
	// corresponding columns, overriding any possible corresponding value in the $childRows
	// array's entry for that row.  This can be an associative array, or null.  Optional.
	// Defaults to null.
	//
	// $neverUpdateChildColumnNames is a linear array of columns in the child table which should
	// never get updated when updating a child row.  This can be a linear array, or null.
	// Optional.  Defaults to null.
	//
	// $childRowInsertCallback can specify a function which is called AFTER each time a child row
	// is inserted.  It can be either a string name of a function, or an array with two elements:
	// an object instance and a function name within that instance.  Two arguments are passed to
	// the callback function: the actual data object being inserted, and the child row element
	// from $childRows.
	// Optional.  Defaults to null.
	//
	// $childRowUpdateCallback can specify a function which is called AFTER each time a child row
	// is updated.  It can be either a string name of a function, or an array with two elements:
	// an object instance and a function name within that instance.  Two arguments are passed to
	// the callback function: the actual data object being updated, and the child row element
	// from $childRows.
	// Optional.  Defaults to null.
	//
	// $childRowDeleteCallback can specify a function which is called BEFORE each time a child row
	// is deleted.  It can be either a string name of a function, or an array with two elements:
	// an object instance and a function name within that instance.  One argument is passed to
	// the callback function: the child row element from $childRows.
	// Optional.  Defaults to null.
	public static function updateChildRows(
		$db,
		$childDataClassName,
		$childRows,
		$commonIdentifiers,
		$uniqueIdentifyingColumnNames,
		$forcedChildRowValues = null,
		$neverUpdateChildColumnNames = null,
		$childRowInsertCallback = null,
		$childRowUpdateCallback = null,
		$childRowDeleteCallback = null) {

		$childDAOClassName = $childDataClassName.'DAO';
		$childTableName =
			strtolower(substr($childDataClassName, 0, 1)) .
			substr($childDataClassName, 1);

		$childDAO = new $childDAOClassName($db);

		if ( (!isset($childRows)) || (!is_array($childRows)) ) {
			$childRows = array();
		}

		$commonIdWhereClause = '';
		$whereAnd = ' where ';
		foreach (array_keys($commonIdentifiers) as $cn) {
			$commonIdWhereClause .= $whereAnd.$cn.' = ?';
			$whereAnd = ' and ';
		}

		$uniqueIdWhereClause = '';
		$whereAnd = ' where ';
		foreach ($uniqueIdentifyingColumnNames as $cn) {
			$uniqueIdWhereClause .= $whereAnd.$cn.' = ?';
			$whereAnd = ' and ';
		}

		// Find existing child rows; load them into $oldRows.
		$ps = new PreparedStatement('select * from '.$childTableName.$commonIdWhereClause);
		$row = new $childDataClassName();
		$row->loadFromArray($commonIdentifiers);
		ChildRowUpdater::loadPS($ps, array_keys($commonIdentifiers), $row);
		$oldRows = $childDAO->findWithPreparedStatement($ps);

		// Delete rows which exist in $oldRows but don't exist in the $childRows array.
		$ps = new PreparedStatement('delete from '.$childTableName.$uniqueIdWhereClause);
		for ($ori = 0; $ori < count($oldRows);) {
			$oldRow = $oldRows[$ori];
			$found = false;
			foreach ($childRows as $cr) {
				$childRow = new $childDataClassName();
				$childRow->loadFromArray((array)$cr);
				foreach ($commonIdentifiers as $key=>$val) $childRow->$key = $val;
				$found = true;
				foreach ($uniqueIdentifyingColumnNames as $cn) {
					if ($childRow->$cn != $oldRow->$cn) {
						$found = false;
						break;
					}
				}
				if ($found) break;
			}
			if (!$found) {
				if ((strlen($childRowDeleteCallback) > 0) || (is_array($childRowDeleteCallback))) {
					call_user_func($childRowDeleteCallback, $oldRow);
				}
				ChildRowUpdater::loadPS($ps, $uniqueIdentifyingColumnNames, $oldRow);
				$db->executeUpdate($ps);
				unset($oldRows[$ori]);
				$oldRows = array_slice($oldRows, 0);
			} else {
				$ori++;
			}
		}

		// Update existing rows; insert missing rows.
		foreach ($childRows as $cr) {
			$childRow = new $childDataClassName();
			$childRow->loadFromArray((array)$cr);
			foreach ($commonIdentifiers as $key=>$val) $childRow->$key = $val;
			$found = false;
			foreach ($oldRows as $oldRow) {
				$found = true;
				foreach ($uniqueIdentifyingColumnNames as $cn) {
					if ($childRow->$cn != $oldRow->$cn) {
						$found = false;
						break;
					}
				}
				if ($found) break;
			}
			if ( ($found) && (is_array($neverUpdateChildColumnNames)) ) {
				foreach ($neverUpdateChildColumnNames as $cn) {
					$childRow->$cn = $oldRow->$cn;
				}
			}
			if (is_array($forcedChildRowValues)) {
				foreach ($forcedChildRowValues as $key=>$val) $childRow->$key = $val;
			}
			if ($found) {
				$childDAO->update($childRow);
				if ((strlen($childRowUpdateCallback) > 0) || (is_array($childRowUpdateCallback))) {
					call_user_func($childRowUpdateCallback, $childRow, $cr);
				}
			} else {
				$childDAO->insert($childRow);
				if ((strlen($childRowInsertCallback) > 0) || (is_array($childRowInsertCallback))) {
					call_user_func($childRowInsertCallback, $childRow, $cr);
				}
			}
		}
	}

	private static function loadPS(&$ps, $valueNames, $dataObject) {
		$ps->clearParams();
		foreach ($valueNames as $cn) {
			$val = $dataObject->$cn;
			switch (gettype($val)) {
			case 'boolean':
				$ps->setBoolean($val);
				break;
			case 'int':
			case 'integer':
				$ps->setInt($val);
				break;
			case 'float':
				$ps->setFloat($val);
				break;
			case 'double':
				$ps->setDouble($val);
				break;
			case 'string':
			default:
				$ps->setString($val);
				break;
			}
		}
	}
}
