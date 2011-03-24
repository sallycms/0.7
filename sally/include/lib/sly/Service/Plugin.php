<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @author  christoph@webvariants.de
 * @ingroup service
 */
class sly_Service_Plugin extends sly_Service_AddOn_Base {
	/**
	 * Installiert ein Plugin
	 *
	 * @param array $plugin  Plugin als array(addon, plugin)
	 */
	public function install($plugin, $installDump = true) {
		list ($addon, $pluginName) = $plugin;

		$pluginDir   = $this->baseFolder($plugin);
		$installFile = $pluginDir.'install.inc.php';
		$installSQL  = $pluginDir.'install.sql';
		$configFile  = $pluginDir.'config.inc.php';

		// return error message if an addOn wants to stop the install process

		$state = $this->extend('PRE', 'INSTALL', $plugin, true);

		if ($state !== true) {
			return $state;
		}

		// check for config.inc.php before we do anything

		if (!is_readable($configFile)) {
			return t('config_not_found');
		}

		// check requirements

		if (!$this->isAvailable($plugin)) {
			$this->loadConfig($plugin); // static.yml, defaults.yml
		}

		$requires = sly_makeArray($this->getProperty($plugin, 'requires'));
		$aService = sly_Service_Factory::getAddOnService();

		foreach ($requires as $requiredAddon) {
			if (!$aService->isAvailable($requiredAddon)) {
				// TODO I18n
				return 'The addOn '.$requiredAddon.' is required to install this plugIn.';
			}
		}

		// check Sally version

		$sallyVersions = $this->getProperty($plugin, 'sally');

		if (!empty($sallyVersions)) {
			$sallyVersions = sly_makeArray($sallyVersions);
			$versionOK     = false;

			foreach ($sallyVersions as $version) {
				$versionOK |= $this->checkVersion($version);
			}

			if (!$versionOK) {
				return 'This plugIn is not marked as compatible with your SallyCMS version ('.sly_Core::getVersion('X.Y.Z').').';
			}
		}
		else {
			return t('plugin_has_no_sally_version_info');
		}

		// include install.inc.php if available

		if (is_readable($installFile)) {
			try {
				$this->req($installFile);
			}
			catch (Exception $e) {
				return t('plugin_no_install', $plugin, $e->getMessage());
			}
		}

		// read install.sql and install DB

		if ($installDump && is_readable($installSQL)) {
			$state = rex_install_dump($installSQL);

			if ($state !== true) {
				return 'Error found in install.sql:<br />'.$state;
			}
		}

		// copy assets to data/dyn/public

		if (is_dir($pluginDir.'assets')) {
			$this->copyAssets($plugin);
		}

		// mark plugIn as installed
		$this->setProperty($plugin, 'install', true);

		// store current plugin version
		$version = $this->getProperty($plugin, 'version', false);

		if ($version !== false) {
			sly_Util_Versions::set('plugins/'.implode('_', $plugin), $version);
		}

		// notify listeners
		return $this->extend('POST', 'INSTALL', $plugin, true);
	}

	/**
	 * De-installiert ein Plugin
	 *
	 * @param array $plugin  Plugin als array(addon, plugin)
	 */
	public function uninstall($plugin) {
		list($addon, $pluginName) = $plugin;

		$pluginDir      = $this->baseFolder($plugin);
		$uninstallFile  = $pluginDir.'uninstall.inc.php';
		$uninstallSQL   = $pluginDir.'uninstall.sql';

		// if not installed, try to disable if needed

		if (!$this->isInstalled($plugin)) {
			return $this->deactivate($plugin);
		}

		// stop if addOn forbids uninstall

		$state = $this->extend('PRE', 'UNINSTALL', $plugin, true);

		if ($state !== true) {
			return $state;
		}

		// deactivate addOn first

		$state = $this->deactivate($plugin);

		if ($state !== true) {
			return $state;
		}

		// include uninstall.inc.php if available

		if (is_readable($uninstallFile)) {
			try {
				$this->req($uninstallFile);
			}
			catch (Exception $e) {
				return t('plugin_no_uninstall', $plugin, $e->getMessage());
			}
		}

		// read uninstall.sql

		if (is_readable($uninstallSQL)) {
			$state = rex_install_dump($uninstallSQL);

			if ($state !== true) {
				return 'Error found in uninstall.sql:<br />'.$state;
			}
		}

		// mark plugIn as not installed
		$this->setProperty($plugin, 'install', false);

		// delete files
		$state  = $this->deletePublicFiles($plugin);
		$stateB = $this->deleteInternalFiles($plugin);

		if ($stateB !== true) {
			// overwrite or concat stati
			$state = $state === true ? $stateB : $stateA.'<br />'.$stateB;
		}

		// notify listeners
		return $this->extend('POST', 'UNINSTALL', $plugin, $state);
	}

	public function baseFolder($plugin) {
		list($addon, $pluginName) = $plugin;
		return rex_plugins_folder($addon, $pluginName).DIRECTORY_SEPARATOR;
	}

	protected function dynFolder($type, $plugin) {
		list($addon, $pluginName) = $plugin;

		$config = sly_Core::config();
		$s      = DIRECTORY_SEPARATOR;
		$dir    = SLY_DYNFOLDER.$s.$type.$s.$addon.$s.$pluginName;

		sly_Util_Directory::create($dir);
		return $dir;
	}

	protected function extend($time, $type, $plugin, $state) {
		list($addon, $pluginName) = $plugin;
		return rex_register_extension_point('SLY_PLUGIN_'.$time.'_'.$type, $state, array('addon' => $addon, 'plugin' => $pluginName));
	}

	/**
	 * Setzt eine Eigenschaft des Addons.
	 *
	 * @param  array  $plugin    Plugin als array(addon, plugin)
	 * @param  string $property  Name der Eigenschaft
	 * @param  mixed  $property  Wert der Eigenschaft
	 * @return mixed             der gesetzte Wert
	 */
	public function setProperty($plugin, $property, $value) {
		list($addon, $pluginName) = $plugin;
		return sly_Core::config()->set('ADDON/'.$addon.'/plugins/'.$pluginName.'/'.$property, $value);
	}

	/**
	 * Gibt eine Eigenschaft des Plugins zurück.
	 *
	 * @param  array  $plugin     Plugin als array(addon, plugin)
	 * @param  string $property   Name der Eigenschaft
	 * @param  mixed  $default    Rückgabewert, falls die Eigenschaft nicht gefunden wurde
	 * @return string             Wert der Eigenschaft des Plugins
	 */
	public function getProperty($plugin, $property, $default = null) {
		list($addon, $pluginName) = $plugin;
		return sly_Core::config()->has('ADDON/'.$addon.'/plugins/'.$pluginName.'/'.$property) ? sly_Core::config()->get('ADDON/'.$addon.'/plugins/'.$pluginName.'/'.$property) : $default;
	}

	/**
	 * Gibt ein Array aller registrierten Plugins zurück.
	 *
	 * Ein Plugin ist registriert, wenn es dem System bekannt ist (plugins.yaml).
	 *
	 * @return array  Array aller registrierten Plugins
	 */
	public function getRegisteredPlugins($addon) {
		$plugins = isset($this->data[$addon]['plugins']) ? array_keys($this->data[$addon]['plugins']) : array();
		natsort($plugins);
		return $plugins;
	}

	/**
	 * Gibt ein Array von verfügbaren Plugins zurück.
	 *
	 * Ein Plugin ist verfügbar, wenn es installiert und aktiviert ist.
	 *
	 * @return array  Array der verfügbaren Plugins
	 */
	public function getAvailablePlugins($addon) {
		$avail = array();

		foreach ($this->getRegisteredPlugins($addon) as $pluginName) {
			if ($this->isAvailable(array($addon, $pluginName))) {
				$avail[] = $pluginName;
			}
		}

		natsort($avail);
		return $avail;
	}

	/**
	 * Gibt ein Array aller installierten Plugins zurück.
	 *
	 * @param  string $addon  Name des AddOns
	 * @return array          Array aller registrierten Plugins
	 */
	public function getInstalledPlugins($addon) {
		$avail = array();

		foreach ($this->getRegisteredPlugins($addon) as $plugin) {
			if ($this->isInstalled(array($addon, $plugin))) $avail[] = $plugin;
		}

		natsort($avail);
		return $avail;
	}

	public function loadPlugin($plugin) {
		$this->loadConfig($plugin);
		$this->checkUpdate($plugin);

		$pluginConfig = $this->baseFolder($plugin).'config.inc.php';
		$this->req($pluginConfig);
	}

	protected function getI18NPrefix() {
		return 'addon_';
	}

	protected function getVersionKey($plugin) {
		return 'plugins/'.implode('_', $plugin);
	}
}
