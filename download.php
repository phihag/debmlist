<?php
namespace debmlist;
use \aufschlagwechsel\bup\league_download;

require 'utils.php';
\debmlist\utils\setup_error_handler();

// If the following code fails, check out but at the correct location
if (\is_dir('../bup/')) {
	$BUP_LOCATION = '../bup/';
} elseif (\is_dir('./bup')) {
	$BUP_LOCATION = './bup/';
} else {
	throw new \Exception('Cannot find bup! Run make bup to install it.');
}
require($BUP_LOCATION . 'div/selectevent/league_download.php');



function main() {
	$config = \debmlist\utils\read_config();
	$config['cache_dir'] = __DIR__ . '/cache';

	$leagues = league_download\download_leagues($config);

	$out_dir = __DIR__ . '/output/';
	\debmlist\utils\ensure_dir($out_dir);
	$out_fn = $out_dir . $config['key'] . '.json';
	$out_json = \json_encode($leagues, \JSON_PRETTY_PRINT);
	\file_put_contents($out_fn, $out_json);
	echo 'Wrote to ' . $out_fn . "\n";
}

main();
