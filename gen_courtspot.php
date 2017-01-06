<?php
namespace debmlist;
require('utils.php');
utils\setup_error_handler();

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

function transform_php($config, $out_dir, $basename) {
	$php_fn = __DIR__ . '/' . $basename;
	$php_src = \file_get_contents($php_fn);
	if (!$php_src) {
		throw new \Exception('Cannot read CourtSpot source file');
	}
	$php_src = \str_replace('__SEASON__', $config['key'], $php_src);
	if (!\file_put_contents($out_dir . '/courtspot/' . $basename, $php_src)) {
		throw new \Exception('Cannot write CourtSpot source file');
	}
}

function gen_players($config, $out_dir, $leagues) {
	$basedir = $out_dir . '/courtspot/' . $config['key'] . '/players/';
	utils\ensure_dir($basedir);

	foreach ($leagues as $l) {
		foreach ($l['teams'] as $t) {
			$fn = $basedir . $t['cs_id'] . '.xml';

			$doc = new \DOMDocument('1.0', 'UTF-8');
			$root = $doc->createElement('DATEN');
			$root = $doc->appendChild($root);

			foreach ($t['players'] as $p) {
				$pnode = create_el($root, 'Spieler');
				create_el($pnode, 'mw', (($p['gender'] === 'm') ? 'm' : 'w'));
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

function gen_clubs($config, $out_dir, $leagues) {
	$basedir = $out_dir . '/courtspot/' . $config['key'] . '/clubs/';
	utils\ensure_dir($basedir);

	foreach ($leagues as $l) {
		if (!\preg_match('/^[0-9]+$/', $l['draw_id'])) {
			throw new \Exception('Invalid league id: ' . $l['draw_id']);
		}
		$fn = $basedir . $l['draw_id'] . '.xml';

		$doc = new \DOMDocument('1.0', 'UTF-8');
		$root = $doc->createElement('DATEN');
		$root = $doc->appendChild($root);

		foreach ($l['teams'] as $t) {
			$tnode = create_el($root, 'Verein');
			create_el($tnode, 'Nummer', $t['cs_id']);
			create_el($tnode, 'Lang', _calc_longname($config, $t['name']));
			create_el($tnode, 'Kurz', $t['cs_shortname']);
		}
		\file_put_contents($fn, $doc->saveXML());
	}
}

function calc_ids($config, &$leagues) {
	$next_id = 1;
	foreach ($leagues as &$l) {
		$teams =& $l['teams'];
		foreach ($teams as &$t) {
			$t['cs_shortname'] = _calc_shortname($config, $t['name']);
		}
		usort($teams, function($t1, $t2) {
			return strcmp($t1['cs_shortname'], $t2['cs_shortname']);
		});
		foreach ($teams as &$t) {
			$t['cs_id'] = $next_id;
			$next_id++;
		}
	}
}

function gen_matches($config, $out_dir, $leagues) {
	$matches = [];
	$lines = [];
	foreach ($leagues as $l) {
		$teams_by_id = [];
		foreach ($l['teams'] as $t) {
			$teams_by_id[$t['id']] = $t;
		}

		$last_day = -1;
		foreach ($l['matches'] as $m) {
			$team_names = \array_map(function($team_id) use ($teams_by_id) {
				return $teams_by_id[$team_id]['cs_shortname'];
			}, $m['team_ids']);
			if ($last_day !== $m['matchday']) {
				if (\count($lines) > 0) {
					$lines[] = '';
				}
				$last_day = $m['matchday'];
			}
			$lines[] = (
				$l['draw_id'] . ',' . $m['date'] . ',' . $m['starttime'] . ',' . $m['matchday'] . ',' .
				$team_names[0] . ',' . $team_names[1]);
			$matches[] = [
				'league_id' => $l['draw_id'],
				'date' => $m['date'],
				'starttime' => $m['starttime'],
				'matchday' => $m['matchday'],
				'home_team_shortname' => $team_names[0],
				'away_team_shortname' => $team_names[1],
			];
		}
	}
	$lines[] = '';
	$lines[] = '';

	$txt_fn = $out_dir . '/courtspot/' . $config['key'] . '/begegnungen.txt';
	$s = \implode("\n", $lines);
	\file_put_contents($txt_fn, $s);

	$json_fn = $out_dir . '/courtspot/' . $config['key'] . '/begegnungen.json';
	$json_s = \json_encode($matches, \JSON_PRETTY_PRINT);
	\file_put_contents($json_fn, $json_s);
}

function make_zip($config, $out_dir) {
	if (! \preg_match('/^[0-9a-z]+$/', $config['key'])) {
		throw new \Exception('Invalid config key');
	}

	$zip_fn = \realpath($out_dir) . '/' . 'courtspot.zip';
	$cmd = [
		'zip',
		'-r',
		$zip_fn,
		$config['key'],
		'getVereine.php',
		'getSpieler.php',
		'begegnungen.txt',
		'begegnungen.json',
	];

	$cmdline = \implode(' ', \array_map('escapeshellarg', $cmd));
	\exec('cd ' . \escapeshellarg($out_dir . '/courtspot/') . ' && ' . $cmdline);
	echo 'Wrote ' . $zip_fn . "\n";
}

function main() {
	$config = utils\read_config();

	$out_dir = __DIR__ . '/output/';
	$leagues_fn = $out_dir . $config['key'] . '.json';
	$leagues = \json_decode(\file_get_contents($leagues_fn), true);
	if (!$leagues) {
		throw new \Exception('Failed to read leagues');
	}

	calc_ids($config, $leagues);
	
	utils\ensure_dir($out_dir . '/courtspot');
	utils\ensure_dir($out_dir . '/courtspot/' . $config['key']);

	gen_players($config, $out_dir, $leagues);
	gen_clubs($config, $out_dir, $leagues);
	gen_matches($config, $out_dir, $leagues);

	transform_php($config, $out_dir, 'getSpieler.php');
	transform_php($config, $out_dir, 'getVereine.php');

	make_zip($config, $out_dir);
}

main();
