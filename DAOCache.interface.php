<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// DAOCache.interface.php
// Copyright (c) 2010-2016 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

interface DAOCache {
	// Get a group of rows from the cache.
	// Parameters:
	// $cacheKey: A unique cache key derived from the PreparedStatement which will be used to retrieve
	//     the rows from the database in the event of a cache miss.
	// Returns:
	// A linear array of the matching rows, or false if a cache miss occurred.
	public function get($cacheKey);

	// Store a group of rows into the cache.
	// Parameters:
	// $cacheKey: A unique cache key derived from the PreparedStatement which was used to retrieve
	//     the rows from the database.
	// $rows: A linear array of the rows resulting from the SQL query contained in the PreparedStatement
	//     which was used to fetch the rows.  These will be stored in the cache using the database name
	//     and cache key as a key.
	public function set($cacheKey, $rows);
}
