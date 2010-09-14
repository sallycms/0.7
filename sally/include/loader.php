<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

require_once SLY_INCLUDE_PATH.'/lib/sly/Loader.php';

sly_Loader::addLoadPath(SLY_INCLUDE_PATH.'/lib');
sly_Loader::addLoadPath(SLY_INCLUDE_PATH.'/lib/sfYaml');
sly_Loader::addLoadPath(SLY_INCLUDE_PATH.'/controllers', 'sly_Controller_');
sly_Loader::addLoadPath(SLY_INCLUDE_PATH.'/layout', 'sly_Layout_');
sly_Loader::addLoadPath(SLY_INCLUDE_PATH.'/lib/rex/oo', 'OO');
sly_Loader::register();

require_once SLY_INCLUDE_PATH.'/lib/compatibility.php';
require_once SLY_INCLUDE_PATH.'/lib/functions.php';

// Funktionen

require_once SLY_INCLUDE_PATH.'/functions/function_rex_globals.inc.php';
require_once SLY_INCLUDE_PATH.'/functions/function_rex_client_cache.inc.php';
require_once SLY_INCLUDE_PATH.'/functions/function_rex_url.inc.php';
require_once SLY_INCLUDE_PATH.'/functions/function_rex_extension.inc.php';
require_once SLY_INCLUDE_PATH.'/functions/function_rex_addons.inc.php';
require_once SLY_INCLUDE_PATH.'/functions/function_rex_plugins.inc.php';
require_once SLY_INCLUDE_PATH.'/functions/function_rex_other.inc.php';

if ($REX['REDAXO']) {
	require_once SLY_INCLUDE_PATH.'/functions/function_rex_time.inc.php';
	require_once SLY_INCLUDE_PATH.'/functions/function_rex_title.inc.php';
	require_once SLY_INCLUDE_PATH.'/functions/function_rex_generate.inc.php';
	require_once SLY_INCLUDE_PATH.'/functions/function_rex_structure.inc.php';
}
