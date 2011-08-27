<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Credits_Bugreport extends sly_Controller_Credits {
	public function index() {
		print $this->render('credits/bugreport.phtml');
	}

	protected function getLanguages() {
		$langs = sly_Util_Language::findAll();

		foreach ($langs as $idx => $lang) {
			$langs[$idx] = sprintf('[%d] %s (%s)', $lang->getId(), $lang->getName(), $lang->getLocale());
		}

		return $langs;
	}

	protected function getMySQLVersion() {
		$db = sly_DB_Persistence::getInstance();
		$db->query('SELECT VERSION()');
		foreach ($db as $row) $version = reset($row);

		return $version;
	}

	protected function getExtensions() {
		$extensions = get_loaded_extensions();
		$extnum     = count($extensions);
		$extlists   = array();

		natcasesort($extensions);

		for ($i = 0; $i < $extnum; $i += 7) {
			$extlists[] = implode(', ', array_slice($extensions, $i, 7));
		}

		return $extlists;
	}

	public function checkPermission() {
		$user = sly_Util_User::getCurrentUser();
		return $user && $user->isAdmin();
	}
}