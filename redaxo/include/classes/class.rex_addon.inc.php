<?php

/**
 * Basisklasse für Addons/Plugins
 *
 * @package redaxo4
 * @version svn:$Id$
 */

 class rex_addon
{
	private $data;
	private $name;
	private static $instances;
	
	/**
	 * Privater rex_addon Konstruktor.
	 * Erstellen von Objekten dieser Klasse ist nicht erlaubt!
	 *
	 * @param string|array $namespace Namensraum des rex-Addons
	 */
	private function __construct($namespace)
	{
		global $REX;

		// plugin?
		if(is_array($namespace))
		{
			if(!isset($namespace[0]) || !isset($namespace[1]) ||
			!is_string($namespace[0]) || !is_string($namespace[1]))
			{
				trigger_error('Unexpected namespace format!', E_USER_ERROR);
			}

			$addon = $namespace[0];
			$plugin = $namespace[1];
			$this->data = &$REX['ADDON']['plugins'][$addon];
			$this->name = $plugin;
		}
		// addon?
		else
		{
			$this->data =& $REX['ADDON'];
			$this->name = $namespace;
		}
	}

	/**
	 * Erstellt ein rex-Addon aus dem Namespace $namespace.
	 *
	 * @param string|array $namespace Namensraum des rex-Addons
	 *
	 * @return rex_addon Zum namespace erstellte rex-Addon instanz
	 */
	public static function create($namespace)
	{
		$nsString = $namespace;
		if(is_array($namespace))
		{
			$nsString = implode('/', $namespace);
		}

		if(!isset(self::$instances[$nsString]))
		{
			self::$instances[$nsString] = new self($namespace);
		}

		return self::$instances[$nsString];
	}

	/**
	 * Pr�ft ob das rex-Addon verfügbar ist, also installiert und aktiviert.
	 *
	 * @param string|array $addon Name des Addons
	 *
	 * @return boolean TRUE, wenn das rex-Addon verfügbar ist, sonst FALSE
	 */
	public static function isAvailable($addon)
	{
		return self::isInstalled($addon) && rex_addon::isActivated($addon);
	}

	/**
	 * Pr�ft ob das rex-Addon aktiviert ist.
	 *
	 * @param string|array $addon Name des Addons
	 *
	 * @return boolean TRUE, wenn das rex-Addon aktiviert ist, sonst FALSE
	 */
	public static function isActivated($addon)
	{
		return (bool)self::getProperty($addon, 'status', false) == true;
	}

	/**
	 * Pr�ft ob das rex-Addon installiert ist.
	 *
	 * @param string|array $addon Name des Addons
	 *
	 * @return boolean TRUE, wenn das rex-Addon installiert ist, sonst FALSE
	 */
	public static function isInstalled($addon)
	{
		return (bool)self::getProperty($addon, 'install', false) == true;
	}

	/**
	 * Gibt die Version des rex-Addons zurück.
	 *
	 * @param string|array $addon Name des Addons
	 * @param mixed $default Rückgabewert, falls keine Version gefunden wurde
	 *
	 * @return string Versionsnummer des Addons
	 */
	public static function getVersion($addon, $default = null)
	{
		return self::getProperty($addon, 'version', $default);
	}

	/**
	 * Gibt den Autor des rex-Addons zur�ck.
	 *
	 * @param string|array $addon Name des Addons
	 * @param mixed $default R�ckgabewert, falls kein Autor gefunden wurde
	 *
	 * @return string Autor des Addons
	 */
	public static function getAuthor($addon, $default = null)
	{
		return self::getProperty($addon, 'author', $default);
	}

	/**
	 * Gibt die Support-Adresse des rex-Addons zur�ck.
	 *
	 * @param string|array $addon Name des Addons
	 * @param mixed $default R�ckgabewert, falls keine Support-Adresse gefunden wurde
	 *
	 * @return string Versionsnummer des Addons
	 */
	public static function getSupportPage($addon, $default = null)
	{
		return rex_addon::getProperty($addon, 'supportpage', $default);
	}

	/**
	 * Setzt eine Eigenschaft des rex-Addons.
	 *
	 * @param string|array $addon Name des Addons
	 * @param string $property Name der Eigenschaft
	 * @param mixed $property Wert der Eigenschaft
	 *
	 * @return string Versionsnummer des Addons
	 */
	public static function setProperty($addon, $property, $value)
	{
		$rexAddon = rex_addon::create($addon);

		if(!isset($rexAddon->data[$property]))
		$rexAddon->data[$property] = array();

		$rexAddon->data[$property][$rexAddon->name] = $value;
	}

	/**
	 * Gibt eine Eigenschaft des rex-Addons zur�ck.
	 *
	 * @param string|array $addon Name des Addons
	 * @param string $property Name der Eigenschaft
	 * @param mixed $default R�ckgabewert, falls die Eigenschaft nicht gefunden wurde
	 *
	 * @return string Wert der Eigenschaft des Addons
	 */
	public static function getProperty($addon, $property, $default = null)
	{
		$rexAddon = rex_addon::create($addon);
		return isset($rexAddon->data[$property][$rexAddon->name]) ? $rexAddon->data[$property][$rexAddon->name] : $default;
	}
}