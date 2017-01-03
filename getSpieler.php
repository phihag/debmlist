<?php
if (!isset($_GET['nummer'])) {
	\header('HTTP/1.1 400 Bad Request');
	echo 'Missing GET parameter nummer';
	exit();
}
$nummer = $_GET['nummer'];

if (!\preg_match('/^[0-9]+$/', $nummer)) {
	\header('HTTP/1.1 400 Bad Request');
	echo 'GET parameter nummer is not numeric';
	exit();
}

$fn = __DIR__ . '/cs_buli2016/players/' . \basename($nummer . '.xml');
$xml = \file_get_contents($fn);

if ($xml === false) {
	\header('HTTP/1.1 404 Not Found');
	echo 'Could not find the requested team';
	exit();
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: text/xml');
echo $xml;
