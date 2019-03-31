<?php
namespace debmlist;
require('utils.php');
utils\setup_error_handler();


function main() {
	$config = utils\read_config();

	$out_dir = __DIR__ . '/output/';
	$leagues_fn = $out_dir . $config['key'] . '.json';
	$leagues = \json_decode(\file_get_contents($leagues_fn), true);
	if (!$leagues) {
		throw new \Exception('Failed to read leagues');
	}

	$location_coords = [];
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
	\file_put_contents($locations_fn, \json_encode($location_coords, \JSON_PRETTY_PRINT));
	echo 'Wrote ' . $locations_fn . "\n";
}

main();
