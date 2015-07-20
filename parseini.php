<?php
if ($argc != 2) {
	fprintf(STDERR, "Please specify a single INI filename.\n");
	exit(1);
}
if (@file_exists($argv[1])) {
	$cfg = parse_ini_file($argv[1]);
	if (($cfg === false) || (!is_array($cfg))) $cfg = array();
} else {
	$cfg = array();
}
foreach ($cfg as $key=>$val) {
	echo "ini_$key=".escapeshellarg($val)."\n";
}
exit(0);
