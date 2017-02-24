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

	$res_matches = [];
	$res_teams = [];
	foreach ($leagues as $league) {
		$teams_by_id = [];
		foreach ($league['teams'] as $team) {
			$teams_by_id[$team['id']] = $team;
		}

		$team_indices = []; // team id => index into $res_teams
		foreach ($league['matches'] as $lmatch) {
			$match_tidxs = [];
			foreach ($lmatch['team_ids'] as $tid) {
				if (! \array_key_exists($tid, $team_indices)) {
					$team_indices[$tid] = \count($res_teams);
					$res_teams[] = $teams_by_id[$tid];
				}
				$match_tidxs[] = $team_indices[$tid];
			}

			$lmatch['team_idxs'] = $match_tidxs;
			$tstr = $lmatch['date'] . ' ' . $lmatch['starttime'] . ':00';
			$FORMAT = 'd.m.Y H:i:s';
			$dt = \DateTime::createFromFormat($FORMAT, $tstr, $tz);
			if ($dt === false) {
				throw new \Exception('Failed to parse ' . $tstr);
			}
			$lmatch['ts'] = $dt->getTimestamp();
			$res_matches[] = $lmatch;
		}
	}

	\usort($res_matches, function($m1, $m2) {
		if ($m1['ts'] < $m2['ts']) {
			return -1;
		}
		if ($m1['ts'] > $m2['ts']) {
			return 1;
		}
		return 0;
	});

	$res = [
		'matches' => $res_matches,
		'teams' => $res_teams,
	];

	$out_fn = $out_dir . 'miniticker_' . $config['key'] . '.json';
	\file_put_contents($out_fn, \json_encode($res));
	echo 'Wrote ' . $out_fn . "\n";
}

main();
