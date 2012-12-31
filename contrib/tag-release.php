<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

if (PHP_SAPI !== 'cli') {
	die('This script has to be run from command line.');
}

if (!defined('JSON_UNESCAPED_SLASHES')) {
	die('This script requires PHP 5.4+.');
}

if ($argc < 2) {
	die('Usage: php '.basename(__FILE__).' release');
}

$dir     = realpath(dirname(__FILE__).'/../');
$version = $argv[1];

if ($version[0] !== 'v') {
	$version = 'v'.$version;
}

chdir($dir);
llog('reading current composer.json...');
$json = file_get_contents('composer.json');
$data = json_decode($json, true);

// remember old version
$oldVersion = $data['version'];

llog('detected old version: '.($oldVersion ?: '(null)'));

////////////////////////////////////////////////////////////////////////////////
// update sallyStatic.yml
////////////////////////////////////////////////////////////////////////////////

llog('updating sallyStatic.yml...');
require 'sally/vendor/fabpot/yaml/lib/sfYaml.php';

$shortVersion = ltrim($version, 'v');
$configFile   = 'sally/core/config/sallyStatic.yml';
$static       = sfYaml::load($configFile);

list ($major, $minor, $bugfix) = explode('.', $shortVersion);

$static['VERSION']['MAJOR']  = (int) $major;
$static['VERSION']['MINOR']  = (int) $minor;
$static['VERSION']['BUGFIX'] = (int) $bugfix;

file_put_contents($configFile, sfYaml::dump($static, 3));

////////////////////////////////////////////////////////////////////////////////
// create the version changeset
////////////////////////////////////////////////////////////////////////////////

// set new version
$data['version'] = $shortVersion;

// write it
llog('writing new composer.json...');
$json = trim(json_encode($data, 448))."\n";
file_put_contents('composer.json', $json);

// commit it
llog('committing...');
exec('hg commit -m "version '.$shortVersion.'"');

////////////////////////////////////////////////////////////////////////////////
// tag it
////////////////////////////////////////////////////////////////////////////////

llog('tagging...');
exec('hg tag -r tip '.$version);

////////////////////////////////////////////////////////////////////////////////
// re-use the tag commit to change the composer.json back to dev
////////////////////////////////////////////////////////////////////////////////

// import new commit into mq
llog('importing tag commit into mq...');
exec('hg qimport -r tip');

// set old version
$data['version'] = $oldVersion;

// write composer.json
llog('restoring composer.json...');
$json = trim(json_encode($data, 448))."\n";
file_put_contents('composer.json', $json);

// update current patch
llog('refreshing patch...');
exec('hg qrefresh --message "tagging the '.$shortVersion.' release / going back to dev"');

// and finish the patch
llog('finishing patch...');
exec('hg qfinish qbase');

// done
llog('done');
chdir('..');

////////////////////////////////////////////////////////////////////////////////
// lib
////////////////////////////////////////////////////////////////////////////////

function llog($txt) {
	print "* $txt\n";
}
