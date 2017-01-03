<?php
$SEASON = '__SEASON__';

if (!isset($_GET['liga'])) {
	\header('HTTP/1.1 400 Bad Request');
	echo 'Missing GET parameter liga';
	exit();
}
$liga = $_GET['liga'];

if (!\preg_match('/^[0-9]+$/', $liga)) {
	\header('HTTP/1.1 400 Bad Request');
	echo 'GET parameter liga is not numeric';
	exit();
}

$fn = __DIR__ . '/' . $SEASON . '/clubs/' . \basename($liga . '.xml');
$xml = \file_get_contents($fn);

if ($xml === false) {
	\header('HTTP/1.1 404 Not Found');
	echo 'Could not find the requested league';
	exit();
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: text/xml');
echo $xml;
