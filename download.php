<?php

\set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

function _ensure_dir($fn) {
	if (!\is_dir($fn)) {
		\mkdir($fn);
	}
}

function _make_url($page, $tournament_id, $suffix) {
	return 'http://www.turnier.de/sport/' . $page . '.aspx?id=' . $tournament_id . $suffix;
}

function _strip_crap($name) {
	if (\preg_match('/^(?P<real_name>.*?)\s+\[[A-Z0-9]+\]\s*$/', $name, $m)) {
		return $m['real_name'];
	}
	return $name;
}

function _download_html($url, $use_cache) {
	if (!$use_cache) {
		return \file_get_contents($url);
	}

	$cache_dir = __DIR__ . '/cache';
	_ensure_dir($cache_dir);
	$cache_fn = $cache_dir . '/' . \preg_replace('/[^a-z0-9\.]+/', '_', $url) . '.html';
	if (\file_exists($cache_fn)) {
		return \file_get_contents($cache_fn);
	}
	$res = \file_get_contents($url);
	\file_put_contents($cache_fn, $res);
	return $res;
}

function _parse_players($players_html, $gender) {
	if (\preg_match_all('/
		<tr>\s*
		<td>(?P<teamnum>[0-9]+)-(?P<ranking>[0-9]+)(?:-D(?P<ranking_d>[0-9]+))?<\/td>
		<td><\/td>\s*
		<td\s+id="playercell"><a\s+href="player\.aspx[^"]+">
			(?P<lastname>[^<]+),\s*(?P<firstname>[^<]+)
		<\/a><\/td>\s*
		<td\s+class="flagcell">(?:
			<img[^>]+\/><span\s*class="printonly\s*flag">\[(?P<nationality>[A-Z]{2,})\]\s*<\/span>
		)?
		<\/td>\s*
		<td>(?P<player_id>[0-9-]+)<\/td>\s*
		<td>(?P<birthyear>[0-9]{4})?<\/td>
		/xs', $players_html, $players_m, \PREG_SET_ORDER) === false) {
		throw new \Exception('Failed to match players');
	}

	$res = \array_map(function($m) use ($gender) {
		$p = [
			'ranking' => \intval($m['ranking']),
			'firstname' => $m['firstname'],
			'lastname' => $m['lastname'],
			'player_id' => $m['player_id'],
			'gender' => $gender,
		];
		if ($m['ranking_d']) {
			$p['ranking_d'] = \intval($m['ranking_d']);
		}
		if ($m['nationality']) {
			$p['nationality'] = $m['nationality'];
		}
		return $p;
	}, $players_m);

	if (\count($res) < 1) {
		die($players_html);
	}
	return $res;
}

function download_team($tournament_id, $team_id, $team_name, $use_cache) {
	$players_url = _make_url('teamrankingplayers', $tournament_id, '&tid=' . $team_id);
	$players_html = _download_html($players_url, $use_cache);

	if (!\preg_match(
			'/<table\s+class="ruler">\s*<caption>\s*Herren(?P<tbody>.*?)<\/table>/s',
			$players_html, $players_m_m)) {
		throw new \Exception('Cannot find male players in ' . $players_url);
	}
	$male_players = _parse_players($players_m_m['tbody'], 'm');
	if (\count($male_players) === 0) {
		throw new \Exception('Could not find any male players in ' . $players_url);
	}

	if (!\preg_match(
			'/<table\s+class="ruler">\s*<caption>\s*Damen(?P<tbody>.*?)<\/table>/s',
			$players_html, $players_f_m)) {
		throw new \Exception('Cannot find male players in ' . $players_url);
	}
	$female_players = _parse_players($players_f_m['tbody'], 'f');
	if (\count($female_players) === 0) {
		throw new \Exception('Could not find any female players in ' . $players_url);
	}


	$players = \array_merge([], $male_players, $female_players);

	return [
		'id' => $team_id,
		'name' => $team_name,
		'players' => $players,
	];
}

function download_league($url, $league_key, $use_cache) {
	$m = \preg_match('/https?:\/\/www\.turnier\.de\/sport\/[a-z0-9_]+\.aspx\?id=(?P<id>[0-9A-F-]+)&draw=(?P<draw>[0-9]+)/', $url, $groups);
	if (!$m) {
		throw new \Exception('Cannot parse URL ' . $url);
	}
	$tournament_id = $groups['id'];
	$draw = $groups['draw'];

	$teams_url = _make_url('draw', $tournament_id, '&draw=' . $draw);
	$teams_html = _download_html($teams_url, $use_cache);

	if (!\preg_match('/<th>Konkurrenz:<\/th>\s*<td><a[^>]+>(?P<name>[^<]*)<\/a><\/td>/s', $teams_html, $name_m)) {
		throw new \Exception('Cannot find name in ' . $team_url);
	}
	$league_name = \html_entity_decode($name_m['name']);

	if (!\preg_match('/<table\s+class="ruler">(?P<html>.+?)<\/table>/s', $teams_html, $team_table_m)) {
		throw new \Exception('Cannot find table in ' . $team_url);
	}
	$team_table_html = $team_table_m['html'];

	if (\preg_match_all('/
			<td\s+class="standingsrank">[0-9]+<\/td>
			<td><a\s+href="\/sport\/team.aspx\?id=[A-Z0-9-]+&team=(?P<team_id>[0-9]+)">(?P<name>[^<]+)<\/a>
			/x', $team_table_html, $team_name_m, \PREG_SET_ORDER) === false) {
		throw new \Exception('Failed to match teams in ' . $teams_url);
	}
	$teams = \array_map(function($m) {
		return [
			'name' => _strip_crap($m['name']),
			'team_id' => $m['team_id'],
		];
	}, $team_name_m);
	$teams_by_name = [];
	foreach ($teams as &$t) {
		$teams_by_name[$t['name']] = $t;
	}

	$matches_url = _make_url('drawmatches', $tournament_id, '&draw=' . $draw);
	$matches_html = _download_html($matches_url, $use_cache);

	if (!\preg_match('/<table\s+class="ruler matches">(?P<html>.+?)<\/table>/s', $matches_html, $table_m)) {
		throw new \Exception('Cannot find table in ' . $matches_url);
	}
	$match_table_html = $table_m['html'];

	if (\preg_match_all('/
		<td><\/td>
		<td\s+class="plannedtime"\s+align="right">
			\s*[A-Za-z]{2}\s*(?P<date>[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4})\s*
			<span\s+class="time">\s*(?P<starttime>[0-9]{2}:[0-9]{2})\s*<\/span>
		<\/td>
		<td\s+align="right">(?P<matchday>[0-9]+)<\/td>
		<td\s+align="right">(?P<round>[HR])<\/td>
		<td>(?P<matchnum>[0-9]+)<\/td>
		<td[^>]*>(?:<strong>)?<a\s+class="teamname"[^>]+>(?P<name1>[^<]+)<\/a>(?:<\/strong>)?<\/td>
		<td\s+align="center">-<\/td>
		<td[^>]*>(?:<strong>)?<a\s+class="teamname"[^>]+>(?P<name2>[^<]+)<\/a>(?:<\/strong>)?<\/td>
		<td>(?:<span\s+class="score"><span>[^<]*<\/span><\/span>)?<\/td>
		<td><a\s+href="\.\/location\.aspx\?id=[A-F0-9-]+&lid=[0-9]+">
			(?P<location>[^<]+)<\/a>
		<\/td>
		/x', $match_table_html, $matches_m, \PREG_SET_ORDER) === false) {
		throw new \Exception('Failed to match matches in ' . $matches_url);
	}
	if (\count($matches_m) === 0) {
		throw new \Exception('Could not find any matches in ' . $matches_url);
	}

	$tms = [];
	foreach ($matches_m as $m) {
		$team1_name = _strip_crap($m['name1']);
		$team1 = $teams_by_name[$team1_name];
		if (!$team1) {
			throw new Exception('Cannot find team ' . $team1_name);
		}
		$team2_name = _strip_crap($m['name2']);
		$team2 = $teams_by_name[$team2_name];
		if (!$team2) {
			throw new Exception('Cannot find team ' . $team2_name);
		}

		$tms[] = [
			'date' => $m['date'],
			'starttime' => $m['starttime'],
			'matchday' => $m['matchday'],
			'round' => $m['round'],
			'matchnum' => $m['matchnum'],
			'location' => \html_entity_decode($m['location']),
			'team_ids' => [$team1['team_id'], $team2['team_id']],
		];
	}

	$teams_info = \array_map(function($t) use ($tournament_id, $use_cache) {
		return download_team($tournament_id, $t['team_id'], $t['name'], $use_cache);
	}, $teams);

	$res = [
		'name' => $league_name,
		'league_key' => $league_key,
		'matches' => $tms,
		'teams' => $teams_info,
		'draw_id' => $draw,
	];

	return $res;
}

function create_el($parent, $tagName, $text=false) {
	$doc = $parent->ownerDocument;
	$node = $doc->createElement($tagName);
	if ($text !== false) {
		$textNode = $doc->createTextNode($text);
		$node->appendChild($textNode);
	}
	$parent->appendChild($node);
	return $node;
}

function gen_courtspot_players($out_dir, $leagues) {
	_ensure_dir($out_dir . '/courtspot');
	_ensure_dir($out_dir . '/courtspot/players/');
	foreach ($leagues as $l) {
		foreach ($l['teams'] as $t) {
			if (!\preg_match('/^[0-9]+$/', $t['id'])) {
				throw new Exception('Invalid team id: ' . $t['id']);
			}
			$fn = $out_dir . '/courtspot/players/' . $t['id'] . '.xml';

			$doc = new DOMDocument('1.0', 'UTF-8');
			$root = $doc->createElement('DATEN');
			$root = $doc->appendChild($root);

			foreach ($t['players'] as $p) {
				$pnode = create_el($root, 'Spieler');
				create_el($pnode, 'mw', $p['gender']);
				create_el($pnode, 'Vor', $p['firstname']);
				create_el($pnode, 'Nach', $p['lastname']);
				create_el($pnode, 'ranking', $p['ranking']);
				if (isset($p['ranking_d'])) {
					create_el($pnode, 'ranking_d', $p['ranking_d']);
				}
				if (isset($p['nationality'])) {
					create_el($pnode, 'nationality', $p['nationality']);
				}
			}

			\file_put_contents($fn, $doc->saveXML());
		}
	}
}

function _calc_shortname($config, $name) {
	if (\array_key_exists($name, $config['shortnames'])) {
		return $config['shortnames'][$name];
	}

	$parts = \preg_split('/[\s-]/', $name);
	$longest_part = '';
	foreach ($parts as $p) {
		if (\strlen($p) > \strlen($longest_part)) {
			$longest_part = $p;
		}
	}
	return $longest_part;
}

function _calc_longname($config, $name) {
	if (\array_key_exists($name, $config['longnames'])) {
		return $config['longnames'][$name];
	}
	return $name;
}

function gen_courtspot_clubs($config, $out_dir, $leagues) {
	_ensure_dir($out_dir . '/courtspot');
	_ensure_dir($out_dir . '/courtspot/clubs/');
	foreach ($leagues as $l) {
		if (!\preg_match('/^[0-9]+$/', $l['draw_id'])) {
			throw new Exception('Invalid league id: ' . $l['draw_id']);
		}
		$fn = $out_dir . '/courtspot/clubs/' . $l['draw_id'] . '.xml';

		$doc = new DOMDocument('1.0', 'UTF-8');
		$root = $doc->createElement('DATEN');
		$root = $doc->appendChild($root);

		foreach ($l['teams'] as $t) {
			$tnode = create_el($root, 'Verein');
			create_el($tnode, 'Nummer', $t['id']);
			create_el($tnode, 'Lang', _calc_longname($config, $t['name']));
			create_el($tnode, 'Kurz', _calc_shortname($config, $t['name']));
		}
		\file_put_contents($fn, $doc->saveXML());
	}
}

function main() {
	$config_json = \file_get_contents(__DIR__ . '/config.json');
	$config = \json_decode($config_json, true);
	if(!$config) {
		throw new \Exception('Invalid configuration JSON');
	}
	$use_cache = $config['use_cache'];

	$leagues = [];
	foreach ($config['leagues'] as $l) {
		$leagues[] = download_league($l['url'], $l['league_key'], $use_cache);
	}

	$out_dir = __DIR__ . '/output/';
	_ensure_dir($out_dir);
	$out_fn = $out_dir . $config['key'] . '.json';
	$out_json = \json_encode($leagues, \JSON_PRETTY_PRINT);
	\file_put_contents($out_fn, $out_json);
	echo 'Wrote to ' . $out_fn . "\n";

	gen_courtspot_players($out_dir, $leagues);
	gen_courtspot_clubs($config, $out_dir, $leagues);
}

main();
