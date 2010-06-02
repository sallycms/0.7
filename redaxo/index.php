<?php

define('IS_SALLY', true);

ob_start();
ob_implicit_flush(0);

if (!defined('SLY_IS_TESTING')) {
	define('SLY_IS_TESTING', false);
}

// So sieht eine Datei aus, die sich darauf freut, bald obsolet zu werden.
require 'include/functions/function_rex_mquotes.inc.php';

unset($REX);

$REX['REDAXO']      = true;
$REX['SALLY']       = true;
$REX['HTDOCS_PATH'] = SLY_IS_TESTING ? SLY_TESTING_ROOT : '../';

require 'include/master.inc.php';

// addon/normal page path
$REX['PAGEPATH'] = '';
$REX['PAGES']    = array(); // array(name,addon=1,htmlheader=1)
$REX['PAGE']     = '';
$REX['USER']     = null;
$REX['LOGIN']    = null;

// Setup vorbereiten

if (!SLY_IS_TESTING && $config->get('SETUP')) {
	$REX['LANG']      = 'de_de';
	$REX['LANGUAGES'] = array();
	
	$requestLang = sly_request('lang', 'string');
	$langpath    = $REX['INCLUDE_PATH'].'/lang';
	$languages   = glob($langpath.'/*.lang');
	
	if ($languages) {
		foreach ($languages as $language) {
			$locale = substr(basename($language), 0, -5);
			$REX['LANGUAGES'][] = $locale;
			
			if ($requestLang == $locale) {
				$REX['LANG'] = $locale;
			}
		}
	}
	
	$I18N = rex_create_lang($REX['LANG']);
	
	$REX['PAGES']['setup'] = array($I18N->msg('setup'), 0, 1);
	$REX['PAGE']           = 'setup';
	$_REQUEST['page']      = 'setup';
}
else {
	$I18N = rex_create_lang($REX['LANG']);
	
	// Login vorbereiten

	$REX['LOGIN']   = new rex_backend_login($REX['TABLE_PREFIX'].'user');
	$rex_user_login = rex_post('rex_user_login', 'string');  // addslashes()!
	$rex_user_psw   = rex_post('rex_user_psw', 'string');    // addslashes()!

	$REX['LOGIN']->setPasswordFunction($REX['PSWFUNC']);
	$REX['LOGIN']->setLogout(rex_get('rex_logout', 'boolean'));
	$REX['LOGIN']->setLogin($rex_user_login, $rex_user_psw);
	
	$loginCheck = $REX['LOGIN']->checkLogin();
	
	// Login OK / Session gefunden?

	if ($loginCheck === true) {
		// Userspezifische Sprache einstellen, falls gleicher Zeichensatz
		$lang = $REX['LOGIN']->getLanguage();
		
		if ($I18N->msg('htmlcharset') == rex_create_lang($lang, '', false)->msg('htmlcharset')) {
			$I18N = rex_create_lang($lang);
		}

		$REX['USER'] = $REX['LOGIN']->USER;
	}
	else {
		$rex_user_loginmessage = $REX['LOGIN']->message;

		// Fehlermeldung von der Datenbank
		
		if (is_string($loginCheck)) {
			$rex_user_loginmessage = $loginCheck;
		}

		$REX['PAGES']['login'] = array('login', 0, 1);
		$REX['PAGE']           = 'login';
		$REX['USER']           = null;
		$REX['LOGIN']          = null;
	}
}

// Core-Seiten initialisieren

if ($REX['USER']) {
	$REX['PAGES']['profile'] = array($I18N->msg('profile'), 0, 1);
	$REX['PAGES']['credits'] = array($I18N->msg('credits'), 0, 1);

	if ($REX['USER']->isAdmin() || $REX['USER']->hasStructurePerm()) {
		$REX['PAGES']['structure'] = array($I18N->msg('structure'), 0, 1);
		$REX['PAGES']['mediapool'] = array($I18N->msg('mediapool'), 0, 0, 'NAVI' => array('href' =>'#', 'onclick' => 'openMediaPool()', 'class' => ' rex-popup'));
		$REX['PAGES']['linkmap']   = array($I18N->msg('linkmap'), 0, 0);
		$REX['PAGES']['content']   = array($I18N->msg('content'), 0, 1);
	}
	elseif ($REX['USER']->hasPerm('mediapool[]')) {
		$REX['PAGES']['mediapool'] = array($I18N->msg('mediapool'), 0, 0, 'NAVI' => array('href' =>'#', 'onclick' => 'openMediaPool()', 'class' => ' rex-popup'));
	}

	if ($REX['USER']->isAdmin()) {
	  $REX['PAGES']['template'] = array($I18N->msg('template'), 0, 1);
	  $REX['PAGES']['module']   = array($I18N->msg('modules'), 0, 1, 'SUBPAGES' => array(array('', $I18N->msg('modules')), array('actions', $I18N->msg('actions'))));
	  $REX['PAGES']['user']     = array($I18N->msg('user'), 0, 1);
	  $REX['PAGES']['addon']    = array($I18N->msg('addon'), 0, 1);
	  $REX['PAGES']['specials'] = array($I18N->msg('specials'), 0, 1, 'SUBPAGES' => array(array('', $I18N->msg('main_preferences')), array('languages', $I18N->msg('languages'))));
	}
}

// AddOns einbinden

require_once $REX['INCLUDE_PATH'].'/addons.inc.php';

if ($REX['USER']) {
	// AddOn-Seiten vorbereiten
	foreach ($REX['ADDON']['status'] as $addon => $status) {
		if (!$status) continue;
		
		$perm = '';
		$name = '';
		$link = empty($REX['ADDON']['link'][$addon]) ? $addon : $REX['ADDON']['link'][$addon];
		
		if (isset($REX['ADDON']['perm'][$addon])) $perm = $REX['ADDON']['perm'][$addon];
		if (isset($REX['ADDON']['name'][$addon])) $name = $REX['ADDON']['name'][$addon];
		
		$link = '<a href="index.php?page='.urlencode($link).'">';
		
		if (!empty($name) && (empty($perm) || $REX['USER']->hasPerm($perm) || $REX['USER']->isAdmin())) {
			$popup = isset($REX['ADDON']['popup'][$addon]) ? 0 : 1;
			$REX['PAGES'][strtolower($addon)] = array($name, 1, $popup, $link);
		}
	}

	// Startseite ermitteln

	$REX['USER']->pages = $REX['PAGES'];
	$REX['PAGE']        = strtolower(sly_request('page', 'string'));
	
	if (!empty($rex_user_login)) {
		$REX['PAGE'] = strtolower($REX['LOGIN']->getStartpage());
	}
	
	// Erst normale Startseite, dann User-Startseite, dann System-Startseite und
	// zuletzt auf die Profilseite zurückfallen.
	
	if (!isset($REX['PAGES'][$REX['PAGE']])) {
		$REX['PAGE'] = strtolower($REX['LOGIN']->getStartpage());
		
		if (!isset($REX['PAGES'][$REX['PAGE']])) {
			$REX['PAGE'] = strtolower($REX['START_PAGE']);
			
			if (!isset($REX['PAGES'][$REX['PAGE']])) {
				$REX['PAGE'] = 'profile';
			}
		}
	}
	
	// Login OK -> Redirect auf Startseite
	
	if (!empty($rex_user_login)) {
		header('Location: index.php?page='.urlencode($REX['PAGE']));
		exit();
	}
}

$REX['PAGE_NO_NAVI'] = $REX['PAGES'][$REX['PAGE']][2] == 1 ? 0 : 1;

// Seite gefunden. AddOns benachrichtigen

//$config->appendArray($REX);
rex_register_extension_point('PAGE_CHECKED', $REX['PAGE'], array('pages' => $REX['PAGES']));

// Im Testmodus verlassen wir das Script jetzt.

if (SLY_IS_TESTING) return;

// Gewünschte Seite einbinden
$forceLogin = !$REX['SETUP'] && !$REX['USER'];
$controller = sly_Controller_Base::factory($forceLogin ? 'login' : null, $forceLogin ? 'index' : null);

if ($controller !== null) {
	try {
		$CONTENT = $controller->dispatch();
	}
	catch (sly_Authorisation_Exception $e1) {
		rex_title('Sicherheitsverletzung');
		print rex_warning($e1->getMessage());
	}
	catch (sly_Controller_Exception $e2) {
		rex_title('Controller-Fehler');
		print rex_warning($e2->getMessage());
	}
	catch (Exception $e3) {
		rex_title('Ausnahme');
		print rex_warning('Es ist eine unerwartete Ausnahme aufgetreten: '.$e3->getMessage());
	}
}
else {
	// View laden
	$layout = sly_Core::getLayout('Sally');
	$layout->openBuffer();
	$layout->appendToTitle(t($REX['PAGE']));

	if (!empty($REX['PAGES'][$REX['PAGE']]['PATH'])) { // If page has a new/overwritten path
		require $REX['PAGES'][$REX['PAGE']]['PATH'];
	}
	elseif ($REX['PAGES'][strtolower($REX['PAGE'])][1]) { // Addon Page
		require $REX['INCLUDE_PATH'].'/addons/'. $REX['PAGE'] .'/pages/index.inc.php';
	}
	else { // Core Page
		require $REX['INCLUDE_PATH'].'/pages/'.$REX['PAGE'].'.inc.php';
	}
	
	$layout->closeBuffer();
	$CONTENT = $layout->render();
}

rex_send_article(null, $CONTENT, 'backend', true);
