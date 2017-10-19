<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// FileCacheDAOCache.class.php
// Copyright (c) 2010-2016 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!interface_exists('DAOCache', false)) include(dirname(__FILE__).'/DAOCache.interface.php');

class FileCacheDAOCache implements DAOCache {
	protected $fileCache;
	protected $keyPrefix;

	// Create a new FileCacheDAOCache instance.
	// Parameters:
	// $fileCache: A FileCache instance.
	// $keyPrefix: A prefix to be added to the keys (such as the database name, for example),
	//     in order to prevent identical queries on different databases or from different
	//     web server clusters from accidentally sharing unrelated data through the cache
	//     (by overwriting each other's cache entries or other accidental cache key clashes).
	//     Optional.  Defaults to empty.
	public function __construct($fileCache, $keyPrefix = '') {
		$this->fileCache = $fileCache;
		$this->keyPrefix = $keyPrefix;
	}

	// Get a group of rows from the cache.
	// Parameters:
	// $cacheKey: A unique cache key derived from the PreparedStatement which will be used to retrieve
	//     the rows from the database in the event of a cache miss.
	// Returns:
	// A linear array of the matching rows, or false if a cache miss occurred.
	public function get($cacheKey) {
		$key = sprintf('DAOCache:%s:%s', $this->keyPrefix, sha1($cacheKey));
		$hits = $this->fileCache->get($key);
		if (($hits !== false) && (isset($hits['q'])) && (isset($hits['r'])) &&
			($hits['q'] == $cacheKey)) {
			return $hits['r'];
		}
		return false;
	}

	// Store a group of rows into the cache.
	// Parameters:
	// $cacheKey: A unique cache key derived from the PreparedStatement which was used to retrieve
	//     the rows from the database.
	// $rows: A linear array of the rows resulting from the SQL query contained in the PreparedStatement
	//     which was used to fetch the rows.  These will be stored in the cache using the database name
	//     and cache key as a key.
	public function set($cacheKey, $rows) {
		$key = sprintf('DAOCache:%s:%s', $this->keyPrefix, sha1($cacheKey));
		$val = array('q'=>$cacheKey, 'r'=>$rows);
		$this->fileCache->set($key, $val);
	}
}
