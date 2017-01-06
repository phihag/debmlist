<?php
namespace debmlist\utils;

function setup_error_handler() {
	\set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
	});
}

function ensure_dir($fn) {
	if (!\is_dir($fn)) {
		\mkdir($fn);
	}
}

function read_config() {
	$config_json = \file_get_contents(__DIR__ . '/config.json');
	$config = \json_decode($config_json, true);
	if(!$config) {
		throw new \Exception('Invalid configuration JSON');
	}
	return $config;
}
