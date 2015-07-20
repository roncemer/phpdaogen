<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// DAOCache.interface.php
// Copyright (c) 2010 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

interface DAOCache {
	// Get a group of rows from the cache.
	// Parameters:
	// $query: The SQL query which will be used to retrieve the rows from the database in the
	//     event of a cache miss.
	// Returns:
	// A linear array of the matching rows, or false if a cache miss occurred.
	public function get($query);

	// Store a group of rows into the cache.
	// Parameters:
	// $query: The SQL query which was used to retrieve the rows from the database.
	// $rows: A linear array of the rows resulting from the SQL query.  These will be stored in
	//     the cache using the database name and SQL query as a key.
	public function set($query, $rows);
}
