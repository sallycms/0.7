<?php

/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup util
 */
class sly_Util_Session {

	private static $uniqueInstallationId;

	/**
	 * Start a session if it is not already started
	 *
	 */
	public static function start() {
		if (!session_id())
			session_start();
	}

	/**
	 * Gets the value of a session var casted to $type.
	 *
	 * @param string $key the key where to find the var in superglobal aray $_SESSION
	 * @param string $type the type to cast to
	 * @param mixed  $default ther default value to return if session var is empty
	 *
	 * @return mixed $value casted to $type
	 */
	public static function get($key, $type = '', $default = '') {
		/**
		 * FIXME: can we really put this under MIT license? ist some sort of copy
		 * of redaxo code
		 *
		 */
		if (isset($_SESSION[self::getUID()][$key]))
			return _rex_cast_var($_SESSION[self::getUID()][$key], $type, $default, 'found', false);

		if ($default === '')
			return _rex_cast_var($default, $type, $default, 'default', false);


		return $default;
	}

	/**
	 * Sets the value of a session var
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public static function set($key, $value) {
		$_SESSION[self::getUID()][$key] = $value;
	}

	/**
	 * Unsets a session var
	 *
	 * @param string $key
	 */
	public static function reset($key) {
		unset($_SESSION[self::getUID()][$key]);
	}

	/**
	 * return the unique installation id of this sally instance
	 *
	 * @return string
	 */
	private static function getUID() {
		if (!self::$uniqueInstallationId)
			self::$uniqueInstallationId = sly_Core::config()->get('INSTNAME');

		return self::$uniqueInstallationId;
	}

}

?>