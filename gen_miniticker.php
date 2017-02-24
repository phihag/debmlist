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

			$team1 = $res_teams[$match_tidxs[0]];
			$team2 = $res_teams[$match_tidxs[1]];

			$lmatch['id'] = 'mt_' . $league['league_key'] . '_' . $lmatch['date'] . '_' . $team1['name'] . '-' . $team2['name'];
			$lmatch['league_key'] = $league['league_key'];
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

	foreach ($res_teams as &$t) {
		foreach ($t['players'] as &$p) {
			$p['name'] = $p['firstname'] . ' ' . $p['lastname'];
			unset($p['firstname']);
			unset($p['lastname']);
		}
	}

	$res = [
		'matches' => $res_matches,
		'teams' => $res_teams,
	];

	$out_fn = $out_dir . 'miniticker_' . $config['key'] . '.json';
	\file_put_contents($out_fn, \json_encode($res));
	echo 'Wrote ' . $out_fn . "\n";

	// Generate only current events
	$now = \time();
	$min_time = $now - 24 * 60 * 60;
	$max_time = $now + 14 * 24 * 60 * 60;
	$cur_matches = \array_values(\array_filter($res_matches, function($m) use($min_time, $max_time) {
		return ($m['ts'] >= $min_time) && ($m['ts'] <= $max_time);
	}));
	foreach ($cur_matches as &$cm) {
		$team1 = $res_teams[$cm['team_idxs'][0]];
		$team2 = $res_teams[$cm['team_idxs'][1]];
		$cm['team_names'] = [$team1['name'], $team2['name']];
		$cm['all_players'] = [$team1['players'], $team2['players']];
		unset($cm['team_ids']);
		unset($cm['team_idxs']);
	}
	$cur_out_fn = $out_dir . 'miniticker_cur_' . $config['key'] . '.json';
	\file_put_contents($cur_out_fn, \json_encode($cur_matches));
	echo 'Wrote ' . $cur_out_fn . "\n";
}

main();
