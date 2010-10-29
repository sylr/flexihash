<?php

include '../classes/Flexihash/Exception.php';
include '../classes/Flexihash/Hasher.php';
include '../classes/Flexihash/Crc32Hasher.php';
include '../classes/Flexihash/Md5Hasher.php';
include '../classes/Flexihash.php';

$hasher = new Flexihash_Md5Hasher();
$hash = new Flexihash($hasher, 32);

//var_dump(memory_get_usage());

// bulk add
$hash->addTargets(array('1', '2', '3'), 1);
$hash->addTargets(array('4', '5', '6'), 1);

//var_dump(memory_get_usage());

$i = 0;

$count = array(
	'1' => 0,
	'2' => 0,
	'3' => 0,
	'4' => 0,
	'5' => 0,
	'6' => 0,
);

$start = microtime(true);

while ($i++ <= 1000)
{
	$count[$hash->lookup($i)]++;
}


$time = microtime(true) - $start;
$gap = max($count) - min($count);

//echo str_pad($time, 20, ' ', STR_PAD_RIGHT).' '.$gap.'     '.implode($count, ' | ').chr(10);
echo implode($count, ' | ').chr(10);
//var_dump($count);
//var_dump($gap);
