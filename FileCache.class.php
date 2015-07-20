<?php
// THIS FILE IS PART OF THE phpdaogen PACKAGE.  DO NOT EDIT.
// THIS FILE GETS RE-WRITTEN EACH TIME THE DAO GENERATOR IS EXECUTED.
// ANY MANUAL EDITS WILL BE LOST.

// FileCache.class.php
// Copyright (c) 2010-2013 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

class FileCache {
	// Get the private cache directory for the current application.
	// By using the return value of this function as the parent directory
	// for every value of $path (first argument) passed to the FileCache
	// constructor, it is possible to ensure that no two applications
	// using phpdaogen running on the same server will overwrite each
	// other's values.
	//
	// Subdirectories can be appended onto the return value of this function,
	// separated by a slash directory separator, in order to segregate groups
	// of cache keys by function and expiration time/clean interval.
	//
	// If $cacheRootDir is specified and not empty, all caches will be placed
	// under the specified directory.  Otherwise, all caches will be placed
	// under the system temporary directory.
	public static function getApplicationPrivateCacheDir($cacheRootDir = null) {
		if (strlen($cacheRootDir) == '') {
			$cacheRootDir = (function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : '/tmp');
		}
		return
			$cacheRootDir.'/.jax_cache/'.
			((isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] != '')) ?
				$_SERVER['SERVER_NAME'] : $_SERVER['SERVER_ADDR']).
			'/'.sha1($_SERVER['DOCUMENT_ROOT']);
	}

	// Create a new FileCache instance.
	// Parameters:
	// $path: The path to the base directory which will be used for the cache.
	// $expirationTimeInSeconds: The expiration time, in seconds, for items added to the
	//     cache.  Any value less than 1 is automatically clamped to 1.
	//     Optional.  Defaults to 30.
	// $cleanInterval: How often to clean expired entries.  On average, expired entries will
	//     be cleaned every $cleanInterval get or set requests.  Any value < 1 will be clamped
	//     to 1.
	//     Optional.  Defaults to 1000.
	// $directoryDepth: The number of directories deep to make the cache.  The directory names
	//     are derived from segments of the sha1 hash of the cache key, working from left to right.
	//     Each segment consists of the next two hexadecimal characters of the sha1 hash of the
	//     cache key.  This must be between 1 and 10, inclusive.
	//     Optional.  Defaults to 2.
	public function FileCache(
		$path,
		$expirationTimeInSeconds = 30,
		$cleanInterval = 1000,
		$directoryDepth = 2) {

		$this->path = (string)$path;

		if ($expirationTimeInSeconds < 1) $expirationTimeInSeconds = 1;
		$this->expirationTimeInSeconds = (int)$expirationTimeInSeconds;

		if ($cleanInterval < 1) $cleanInterval = 1;
		$this->cleanInterval = (int)$cleanInterval;

		if ($directoryDepth < 1) $directoryDepth = 1; else if ($directoryDepth > 10) $directoryDepth = 10;
		$this->directoryDepth = (int)$directoryDepth;
	}

	// Get an object from the cache.
	// Parameters:
	//     $key: The cache key.
	// Returns:
	//     The object which was previously stored, or false if a cache miss occurred.
	public function get($key) {
		if (($this->cleanInterval == 1) ||
			(rand(1, $this->cleanInterval) == $this->cleanInterval)) {
			$this->clean();
		}
		$val = false;
		$fn = $this->getCacheFilename($key);
		$exptime = time()-$this->expirationTimeInSeconds;
		if (@file_exists($fn) && (@filemtime($fn) > $exptime)) {
			if (($fp = @fopen($fn, 'rb')) !== false) {
				@flock($fp, LOCK_SH);
				$obj = @unserialize(fread($fp, filesize($fn)));
				if (isset($obj->k) && isset($obj->v) && ($obj->k == $key)) {
					$val = $obj->v;
				}
				@flock($fp, LOCK_UN);
				@fclose($fp);
			}
		}
		return $val;
	}

	// Store an object into the cache.
	// Parameters:
	//     $key: The cache key.
	//     $value: The object to store.
	public function set($key, $value) {
		if (($this->cleanInterval == 1) ||
			(rand(1, $this->cleanInterval) == $this->cleanInterval)) {
			$this->clean();
		}
		$fn = $this->getCacheFilename($key, true);
		if (($fp = @fopen($fn, 'w+b')) !== false) {
			@flock($fp, LOCK_EX);
			$obj = new stdClass();
			$obj->k = $key;
			$obj->v = $value;
			if (@fwrite($fp, serialize($obj)) === false) {
				@flock($fp, LOCK_UN);
				@fclose($fp);
				@unlink($fn);
			} else {
				@flock($fp, LOCK_UN);
				@fclose($fp);
			}
		}
	}

	// Increment a counter in the cache.
	// Parameters:
	//     $key: The cache key.
	//     $value: The value to add to the key.  May be either positive or negative.  Defaults to 1.
	// Returns:
	//     The object which was previously stored, or false if a cache miss occurred.
	public function increment($key, $value = 1) {
		if (($this->cleanInterval == 1) ||
			(rand(1, $this->cleanInterval) == $this->cleanInterval)) {
			$this->clean();
		}
		$fn = $this->getCacheFilename($key);
		$exptime = time()-$this->expirationTimeInSeconds;
		if (@file_exists($fn) && (@filemtime($fn) > $exptime)) {
			if (($fp = @fopen($fn, 'rb')) !== false) {
				@flock($fp, LOCK_EX);
				$obj = @unserialize(fread($fp, filesize($fn)));
				if (isset($obj->k) && isset($obj->v) && ($obj->k == $key)) {
					$obj->v += $value;
					$value = $obj->v;
				} else {
					$obj = new stdClass();
					$obj->k = $key;
					$obj->v = $value;
				}
				if (@fwrite($fp, serialize($obj)) === false) {
					@flock($fp, LOCK_UN);
					@fclose($fp);
					@unlink($fn);
				} else {
					@flock($fp, LOCK_UN);
					@fclose($fp);
				}
			}
		} else {
			$this->set($key, $value);
		}
		return $value;
	}

	// Delete an object from the cache.
	// Parameters:
	//     $key: The cache key.
	public function delete($key) {
		$fn = $this->getCacheFilename($key);
		// Delete the cache file.
		if (@unlink($fn)) {
			// Delete empty subdirectories, all the way up to but excluding the top-level cache dir.
			$refPath = rtrim($this->path, "/\\");
			$dir = rtrim(dirname($fn), "/\\");
			for ($i = 0; $i < $this->directoryDepth; $i++) {
				if (!@rmdir($dir)) break;
				$dir = rtrim(dirname($dir), "/\\");
			}
		}
	}

	// Clean expired entries.
	public function clean() {
		$this->cleanPath($this->path);
	}

	// Clean expired entries from a directory.
	public function cleanPath($path) {
		$exptime = time()-$this->expirationTimeInSeconds;
		foreach (@glob($path.'/*', GLOB_NOSORT) as $fn) {
			if (@is_dir($fn)) {
				$this->cleanPath($fn);
			} else if (@filemtime($fn) <= $exptime) {
				@unlink($fn);
			}
		}
		if ($path != $this->path) {
			@rmdir($path);
		}
	}

	private function getCacheFilename($key, $autoCreateDirectory = false) {
		$hash = sha1($key);
		$path = $this->path;
		for ($i = 0, $idx = 0; $i < $this->directoryDepth; $i++, $idx += 2) {
			$path .= '/'.substr($hash, $idx, 2);
		}
		if ($autoCreateDirectory) @mkdir($path, 0777, true);
		$path .= '/'.$hash;
		return $path;
	}
}
