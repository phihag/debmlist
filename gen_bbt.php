<?php
namespace debmlist;
require('utils.php');
utils\setup_error_handler();


function main() {
	$tz = new \DateTimeZone('Europe/Berlin');
	$config = utils\read_config();

	$out_dir = __DIR__ . '/output/';
	$leagues_fn = $out_dir . $config['key'] . '.json';
	$leagues = \json_decode(\file_get_contents($leagues_fn), true);
	if (!$leagues) {
		throw new \Exception('Failed to read leagues');
	}

	$res = [];

	foreach ($leagues as $league) {
		$teams_by_id = [];
		foreach ($league['teams'] as $team) {
			$teams_by_id[$team['id']] = $team;
		}

		foreach ($league['matches'] as $lmatch) {
			$teams = \array_map(function($team_id) use ($teams_by_id) {
				return $teams_by_id[$team_id];
			}, $lmatch['team_ids']);

			$tstr = $lmatch['date'] . ' ' . $lmatch['starttime'] . ':00';
			$FORMAT = 'd.m.Y H:i:s';
			$dt = \DateTime::createFromFormat($FORMAT, $tstr, $tz);
			if ($dt === false) {
				throw new \Exception('Failed to parse ' . $tstr);
			}

			$rmatch = [
				'type' => 'auto',
				'team_names' => [$teams[0]['name'], $teams[1]['name']],
				'league_key' => $league['league_key'],
				'starttime' => $lmatch['starttime'],
				'date' => $dt->format('Y-m-d'),
				'date_de' => $lmatch['date'],
				'ts' => $dt->getTimestamp(),
			];
			$res[] = $rmatch;
		}
	}

	\usort($res, function($m1, $m2) {
		if ($m1['ts'] < $m2['ts']) {
			return -1;
		}
		if ($m1['ts'] > $m2['ts']) {
			return 1;
		}
		return 0;
	});

	$out_fn = $out_dir . 'bbt_' . $config['key'] . '.json';
	\file_put_contents($out_fn, \json_encode($res, \JSON_PRETTY_PRINT));
	echo 'Wrote ' . $out_fn . "\n";
}

main();
