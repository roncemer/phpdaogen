<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// MemcacheDAOCache.class.php
// Copyright (c) 2010 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('DAOCache', false)) include(dirname(__FILE__).'/DAOCache.interface.php');

class MemcacheDAOCache implements DAOCache {
	protected $memcache;
	protected $expirationTimeInSeconds;
	protected $keyPrefix;

	// Create a new MemcacheDAOCache instance.
	// Parameters:
	// $memcache: A Memcache (http://www.php.net/manual/en/book.memcache.php) or
	//     Memcached (http://www.php.net/manual/en/book.memcached.php) instance.
	// $expirationTimeInSeconds: The expiration time, in seconds, for items added to the
	//     cache.  Any value less than 1 is automatically clamped to 1.
	//     Optional.  Defaults to 30.
	// $keyPrefix: A prefix to be added to the keys (such as the database name, for example),
	//     in order to prevent identical queries on different databases or from different
	//     web server clusters from accidentally sharing unrelated data through the cache
	//     (by overwriting each other's cache entries or other accidental cache key clashes).
	//     Optional.  Defaults to empty.
	public function MemcacheDAOCache($memcache, $expirationTimeInSeconds = 30, $keyPrefix = '') {
		if ($expirationTimeInSeconds < 1) $expirationTimeInSeconds = 1;
		$this->memcache = $memcache;
		$this->expirationTimeInSeconds = $expirationTimeInSeconds;
		$this->keyPrefix = $keyPrefix;
	}

	// Get a group of rows from the cache.
	// Parameters:
	// $query: The SQL query which will be used to retrieve the rows from the database in the
	//     event of a cache miss.
	// Returns:
	// A linear array of the matching rows, or false if a cache miss occurred.
	public function get($query) {
		$key = sprintf('DAOCache:%s:%s', $this->keyPrefix, sha1($query));
		$hits = $this->memcache->get($key);
		if (($hits !== false) && (isset($hits['q'])) && (isset($hits['r'])) &&
			($hits['q'] == $query)) {
			return $hits['r'];
		}
		return false;
	}

	// Store a group of rows into the cache.
	// Parameters:
	// $query: The SQL query which was used to retrieve the rows from the database.
	// $rows: A linear array of the rows resulting from the SQL query.  These will be stored in
	//     the cache using the database name and SQL query as a key.
	public function set($query, $rows) {
		$key = sprintf('DAOCache:%s:%s', $this->keyPrefix, sha1($query));
		$val = array('q'=>$query, 'r'=>$rows);
		switch (get_class($this->memcache)) {
		case 'Memcache':
			$this->memcache->set($key, $val, MEMCACHE_COMPRESSED, $this->expirationTimeInSeconds);
			break;
		case 'Memcached':
			$this->memcache->set($key, $val, $this->expirationTimeInSeconds);
			break;
		}
	}
}
