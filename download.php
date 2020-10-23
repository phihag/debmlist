<?php
namespace debmlist;
use \aufschlagwechsel\bup\league_download;

require 'utils.php';
\debmlist\utils\setup_error_handler();

require 'import_bup.php';

function main() {
	$config = \debmlist\utils\read_config();
	$config['cache_dir'] = __DIR__ . '/cache';

	$leagues = league_download\download_leagues($config);

	$out_dir = __DIR__ . '/output/';
	\debmlist\utils\ensure_dir($out_dir);
	$out_fn = $out_dir . $config['key'] . '.json';
	$out_json = \json_encode($leagues, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
	\file_put_contents($out_fn, $out_json);
	echo 'Wrote to ' . $out_fn . "\n";
}

main();
