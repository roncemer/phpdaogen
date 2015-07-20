<?php
// SequentialStringStore.class.php
// Copyright (c) 2011 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

// This class can store a series of strings to a file, then retrieve them in the same
// order in which they were stored.  The length of each string is stored before the
// actual string, so the strings can contain any byte value(s) without problems.
// The maximum length of any string which can be stored, is (2^31)-1.
class SequentialStringStore {
	// Write a string, preceded by its length, at the current position in the file.
	// A four-byte length is stored first, in big endian order, followed by the
	// bytes of the string.
	// Parameters:
	// $fp: A file pointer, created by fopen().
	// Throws an Exception in the event of a write failure.
	public static function write($fp, $s) {
		$len = (int)strlen($s);
		$lnbuf = 'xxxx';
		$lnbuf[0] = chr(($len >> 24) & 0xff);
		$lnbuf[1] = chr(($len >> 16) & 0xff);
		$lnbuf[2] = chr(($len >> 8) & 0xff);
		$lnbuf[3] = chr($len & 0xff);
		if (($nbw = fwrite($fp, $lnbuf, 4)) != 4) {
			throw new Exception(sprintf(
				'Length write failed; expected to write %d bytes but actually wrote %d bytes',
				4,
				$nbw
			));
		}
		if ($len > 0) {
			if (($nbw = fwrite($fp, $s, $len)) != $len) {
				throw new Exception(sprintf(
					'String write failed; expected to write %d bytes but actually wrote %d bytes',
					$len,
					$nbw
				));
			}
		}
	}

	// Read a string, preceded by its length, at the current position in the file.
	// Parameters:
	// $fp: A file pointer, created by fopen().
	// Returns the string which was read, or false if end of file.
	// Throws an Exception in the event of a read failure.
	public static function read($fp) {
		$lnbuf = fread($fp, 4);
		if (($lnbuf === false) || ($lnbuf == '')) {
			return false;
		}
		if (strlen($lnbuf) != 4) {
			throw new Exception(sprintf(
				'Length read failed; expected to read %d bytes but actually read %d bytes',
				4,
				strlen($lnbuf)
			));
		}
		$len =
			((((int)ord($lnbuf[0])) << 24) & 0xff000000) |
			((((int)ord($lnbuf[1])) << 16) & 0xff0000) |
			((((int)ord($lnbuf[2])) << 8) & 0xff00) |
			(((int)ord($lnbuf[3])) & 0xff);
		if ($len == 0) return '';
		$s = fread($fp, $len);
		if (($s === false) || (strlen($s) != $len)) {
			throw new Exception(sprintf(
				'String read failed; expected to read %d bytes but actually read %d bytes',
				$len,
				strlen($s)
			));
		}
		return $s;
	}
}
