<?php
namespace debmlist;

require 'import_bup.php';
require 'utils.php';
utils\setup_error_handler();


function main() {
	global $BUP_LOCATION;
	$config = utils\read_config();

	$default_fn = $BUP_LOCATION . '/div/selectevent/default_locations.json';
	$location_coords = \json_decode(\file_get_contents($default_fn), true, 512, \JSON_THROW_ON_ERROR);
	if (!$location_coords) {
		throw new \Exception('Failed to read default locations');
	}

	$out_dir = __DIR__ . '/output/';
	$leagues_fn = $out_dir . $config['key'] . '.json';
	$leagues = \json_decode(\file_get_contents($leagues_fn), true);
	if (!$leagues) {
		throw new \Exception('Failed to read leagues');
	}

	foreach ($leagues as $league) {
		foreach ($league['matches'] as $match) {
			$location = $match['location'];
			$coords = $match['loc_coords'];

			if (\array_key_exists($location, $location_coords)) {
				if ($location_coords[$location] !== $coords) {
					throw new Error('Differing coordinates for ' . $location . ': ' . \json_encode($location_coords[$location]) . ' and ' . $coords);
				}
			} else {
				$location_coords[$location] = $coords;
			}
		}
	}

	$locations_fn = $out_dir . 'locations.json';
	\file_put_contents($locations_fn, \json_encode($location_coords, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
	echo 'Wrote ' . $locations_fn . "\n";
}

main();
