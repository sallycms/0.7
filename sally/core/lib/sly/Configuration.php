<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * System Configuration
 *
 * @ingroup core
 */
class sly_Configuration {
	const STORE_PROJECT         = 1; ///< int
	const STORE_PROJECT_DEFAULT = 2; ///< int
	const STORE_LOCAL           = 3; ///< int
	const STORE_LOCAL_DEFAULT   = 4; ///< int
	const STORE_STATIC          = 5; ///< int

	private $mode              = array(); ///< array
	private $loadedConfigFiles = array(); ///< array

	private $staticConfig;  ///< sly_Util_Array
	private $localConfig;   ///< sly_Util_Array
	private $projectConfig; ///< sly_Util_Array
	private $cache;         ///< sly_Util_Array
	private $flush;         ///< boolean

	private $localConfigModified   = false; ///< boolean
	private $projectConfigModified = false; ///< boolean

	public function __construct() {
		$this->staticConfig  = new sly_Util_Array();
		$this->localConfig   = new sly_Util_Array();
		$this->projectConfig = new sly_Util_Array();
		$this->cache         = null;
		$this->flush         = true;
	}

	public function __destruct() {
		if ($this->flush) {
			$this->flush();
		}
	}

	public function setFlushOnDestruct($enabled) {
		$this->flush = (boolean) $enabled;
	}

	/**
	 * @return string  the directory where the config is stored
	 */
	protected function getConfigDir() {
		static $protected = false;

		$dir = SLY_DATAFOLDER.DIRECTORY_SEPARATOR.'config';

		if (!$protected) {
			sly_Util_Directory::createHttpProtected($dir, true);
		}

		$protected = true;
		return $dir;
	}

	/**
	 * @return string  the full path to the local config file
	 */
	protected function getLocalConfigFile() {
		return $this->getConfigDir().DIRECTORY_SEPARATOR.'sly_local.yml';
	}

	/**
	 * @return string  the full path to the project config file
	 */
	public function getProjectConfigFile() {
		return $this->getConfigDir().DIRECTORY_SEPARATOR.'sly_project.yml';
	}

	public function loadDevelop() {
		$dir = new sly_Util_Directory(SLY_DEVELOPFOLDER.DIRECTORY_SEPARATOR.'config');

		if ($dir->exists()) {
			foreach ($dir->listPlain() as $file) {
				if (fnmatch('*.yml', $file) || fnmatch('*.yaml', $file)) {
					$this->loadStatic($dir.DIRECTORY_SEPARATOR.$file);
				}
			}
		}
	}

	/**
	 * @throws sly_Exception      when something is fucked up (file not found, bad parameters, ...)
	 * @param  string  $filename  the file to load
	 * @param  boolean $force     force reloading the config or not
	 * @param  string  $key       where to mount the loaded config
	 * @return mixed              false when an error occured, else the loaded configuration (most likely an array)
	 */
	public function loadProject($filename, $force = false, $key = '/') {
		return $this->loadInternal($filename, self::STORE_PROJECT_DEFAULT, $force, $key);
	}

	/**
	 * @throws sly_Exception     when something is fucked up (file not found, bad parameters, ...)
	 * @param  string $filename  the file to load
	 * @param  string $key       where to mount the loaded config
	 * @return mixed             false when an error occured, else the loaded configuration (most likely an array)
	 */
	public function loadStatic($filename, $key = '/') {
		return $this->loadInternal($filename, self::STORE_STATIC, false, $key);
	}

	/**
	 * @throws sly_Exception      when something is fucked up (file not found, bad parameters, ...)
	 * @param  string  $filename  the file to load
	 * @param  boolean $force     force reloading the config or not
	 * @param  string  $key       where to mount the loaded config
	 * @return mixed              false when an error occured, else the loaded configuration (most likely an array)
	 */
	public function loadLocalDefaults($filename, $force = false, $key = '/') {
		return $this->loadInternal($filename, self::STORE_LOCAL_DEFAULT, $force, $key);
	}

	/**
	 * @throws sly_Exception      when something is fucked up (file not found, bad parameters, ...)
	 * @param  string  $filename  the file to load
	 * @param  boolean $force     force reloading the config or not
	 * @param  string  $key       where to mount the loaded config
	 * @return mixed              false when an error occured, else the loaded configuration (most likely an array)
	 */
	public function loadProjectDefaults($filename, $force = false, $key = '/') {
		return $this->loadInternal($filename, self::STORE_PROJECT_DEFAULT, $force, $key);
	}

	public function loadLocalConfig() {
		$filename = $this->getLocalConfigFile();
		$localConfigModified = $this->localConfigModified;

		if (file_exists($filename)) {
			$config = sly_Util_YAML::load($filename, false, true);
			$this->setInternal('/', $config, self::STORE_LOCAL);
			$this->cache = null;
			$this->localConfigModified = $localConfigModified;
		}
	}

	public function loadProjectConfig() {
		$filename = $this->getProjectConfigFile();
		$projectConfigModified = $this->projectConfigModified;

		if (file_exists($filename)) {
			$config = sly_Util_YAML::load($filename, false, true);
			$this->setInternal('/', $config, self::STORE_PROJECT);
			$this->cache = null;
			$this->projectConfigModified = $projectConfigModified;
		}
	}

	/**
	 * @throws sly_Exception      when something is fucked up (file not found, bad parameters, ...)
	 * @param  string  $filename  the file to load
	 * @param  int     $mode      the mode in which the file should be loaded
	 * @param  boolean $force     force reloading the config or not
	 * @param  string  $key       where to mount the loaded config
	 * @return mixed              false when an error occured, else the loaded configuration (most likely an array)
	 */
	protected function loadInternal($filename, $mode, $force = false, $key = '/') {
		if ($mode != self::STORE_LOCAL_DEFAULT && $mode != self::STORE_STATIC && $mode != self::STORE_PROJECT_DEFAULT) {
			throw new sly_Exception('Konfigurationsdateien können nur mit STORE_STATIC, STORE_LOCAL_DEFAULT oder STORE_PROJECT_DEFAULT geladen werden.');
		}

		if (empty($filename) || !is_string($filename)) throw new sly_Exception('Keine Konfigurationsdatei angegeben.');

		$isStatic = $mode == self::STORE_STATIC;

		// force gibt es nur bei STORE_*_DEFAULT
		$force = $force && !$isStatic;

		// prüfen ob konfiguration in diesem request bereits geladen wurde
		if (!$force && isset($this->loadedConfigFiles[$filename])) {
			// statisch geladene konfigurationsdaten werden innerhalb des requests nicht mehr überschrieben
			if ($isStatic) {
				trigger_error('Statische Konfigurationsdatei '.$filename.' wurde bereits in einer anderen Version geladen! Daten wurden nicht überschrieben.', E_USER_WARNING);
			}
			return false;
		}

		$config = sly_Util_YAML::load($filename, false, true);

		// geladene konfiguration in globale konfiguration mergen
		$this->setInternal($key, $config, $mode, $force);

		$this->loadedConfigFiles[$filename] = true;

		return $config;
	}

	/**
	 * @param  string $key      the key to load
	 * @param  mixed  $default  value to return when $key was not found
	 * @return mixed            the found value or $default
	 */
	public function get($key, $default = null) {
		$this->warmUp();
		return $this->cache->get($key, $default);
	}

	/**
	 * @param  string $key  the key to check
	 * @return boolean      true if found, else false
	 */
	public function has($key) {
		$this->warmUp();
		return $this->cache->has($key);
	}

	/**
	 * @param string $key  the key to remove
	 */
	public function remove($key) {
		$this->localConfig->remove($key);
		$this->localConfigModified = true;
		$this->projectConfig->remove($key);
		$this->projectConfigModified = true;

		$this->cache = null;
	}

	/**
	 * @throws sly_Exception  if the key is invalid or has the wrong mode
	 * @param  string $key    the key to set the value to
	 * @param  mixed  $value  the new value
	 * @return mixed          the set value or false if an error occured
	 */
	public function setStatic($key, $value) {
		return $this->setInternal($key, $value, self::STORE_STATIC);
	}

	/**
	 * @throws sly_Exception  if the key is invalid or has the wrong mode
	 * @param  string $key    the key to set the value to
	 * @param  mixed  $value  the new value
	 * @return mixed          the set value or false if an error occured
	 */
	public function setLocal($key, $value) {
		return $this->setInternal($key, $value, self::STORE_LOCAL);
	}

	/**
	 * @throws sly_Exception   if the key is invalid or has the wrong mode
	 * @param  string  $key    the key to set the value to
	 * @param  mixed   $value  the new value
	 * @param  boolean $force  force reloading the config or not
	 * @return mixed           the set value or false if an error occured
	 */
	public function setLocalDefault($key, $value, $force = false) {
		return $this->setInternal($key, $value, self::STORE_LOCAL_DEFAULT, $force);
	}

	/**
	 * @throws sly_Exception   if the key is invalid or has the wrong mode
	 * @param  string  $key    the key to set the value to
	 * @param  mixed   $value  the new value
	 * @param  boolean $force  force reloading the config or not
	 * @return mixed           the set value or false if an error occured
	 */
	public function setProjectDefault($key, $value, $force = false) {
		return $this->setInternal($key, $value, self::STORE_PROJECT_DEFAULT, $force);
	}

	/**
	 * @throws sly_Exception  if the key is invalid or has the wrong mode
	 * @param  string $key    the key to set the value to
	 * @param  mixed  $value  the new value
	 * @param  int    $mode   one of the classes MODE constants
	 * @return mixed          the set value or false if an error occured
	 */
	public function set($key, $value, $mode = self::STORE_PROJECT) {
		return $this->setInternal($key, $value, $mode);
	}

	/**
	 * @throws sly_Exception   if the key is invalid or has the wrong mode
	 * @param  string  $key    the key to set the value to
	 * @param  mixed   $value  the new value
	 * @param  int     $mode   one of the classes MODE constants
	 * @param  boolean $force  force reloading the config or not
	 * @return mixed           the set value or false if an error occured
	 */
	protected function setInternal($key, $value, $mode, $force = false) {
		if (is_null($key) || strlen($key) === 0) {
			throw new sly_Exception('Key '.$key.' ist nicht erlaubt!');
		}

		if (!empty($value) && sly_Util_Array::isAssoc($value)) {
			$key = trim($key, '/');
			foreach ($value as $ikey => $val) {
				$currentPath = $key.'/'.$ikey;
				$this->setInternal($currentPath, $val, $mode, $force);
			}
			return $value;
		}

		$mode = $this->getStoreMode($key, $mode, $force);

		if($mode === null) return false;

		$this->mode[$key] = $mode;
		$this->cache = null;
		$result = false;

		switch ($mode) {
			case self::STORE_STATIC:
				$result = $this->staticConfig->set($key, $value);
				break;
			case self::STORE_LOCAL:
				$this->localConfigModified = true;
				$result = $this->localConfig->set($key, $value);
				break;
			case self::STORE_PROJECT:
				$this->projectConfigModified = true;
				$result = $this->projectConfig->set($key, $value);

		}

		return $result;
	}

	/**
	 * @throws sly_Exception  if the mode is wrong
	 * @param  string  $key   the key to set the mode of
	 * @param  int     $mode  one of the classes MODE constants
	 * @return int            one of the classes MODE constants or null
	 */
	protected function getStoreMode($key, $mode, $force) {
		//handle default facilities
		if($mode === self::STORE_LOCAL_DEFAULT || $mode === self::STORE_PROJECT_DEFAULT) {
			$mode--; //move to real facility
			// if  the key does not exists or else it is in our real facility and we force override
			if(!isset($this->mode[$key]) || ($force && $this->mode[$key] === $mode)) {
				return $mode;
			}
			return null;
		}
		else {
			// for all others allow duplicate setting of a key only in a higher level facility
			if (isset($this->mode[$key]) && $this->mode[$key] < $mode) {
				throw new sly_Exception('Mode für '.$key.' wurde bereits auf '.$this->mode[$key].' gesetzt.');
			}
		}
		return $mode;
	}

	/**
	 * write the local and projectconfiguration to disc
	 */
	protected function flush() {
		if ($this->localConfigModified) {
			sly_Util_YAML::dump($this->getLocalConfigFile(), $this->localConfig->get(null));
		}

		if ($this->projectConfigModified) {
			sly_Util_YAML::dump($this->getProjectConfigFile(), $this->projectConfig->get(null));
		}
	}

	/**
	 * warm up the internal cache for get/has operations
	 */
	protected function warmUp() {
		if ($this->cache === null) {
			// build merged config cache
			$this->cache = array_replace_recursive($this->staticConfig->get('/', array()), $this->localConfig->get('/', array()), $this->projectConfig->get('/', array()));
			$this->cache = new sly_Util_Array($this->cache);
		}
	}
}
