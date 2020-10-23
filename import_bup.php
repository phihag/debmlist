<?php

// If the following code fails, check out but at the correct location
if (\is_dir('../bup/')) {
	$BUP_LOCATION = '../bup/';
} elseif (\is_dir('./bup')) {
	$BUP_LOCATION = './bup/';
} else {
	throw new \Exception('Cannot find bup! Run make bup to install it.');
}
require($BUP_LOCATION . 'div/selectevent/league_download.php');
