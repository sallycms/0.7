<?php
/*
 * Copyright (c) 2009, webvariants GbR, http://www.webvariants.de
 *
 * Diese Datei steht unter der MIT-Lizenz. Der Lizenztext befindet sich in der
 * beiliegenden LICENSE Datei und unter:
 *
 * http://www.opensource.org/licenses/mit-license.php
 * http://de.wikipedia.org/wiki/MIT-Lizenz
 */

/**
 * Ist noch ein wrapper für $REX wird irgendwann mal umgebaut
 * 
 * @author zozi@webvariants.de
 *
 */
class sly_Configuration implements ArrayAccess
{
	private $config;
	private $filename;
	
	private static $instances;

	private function __construct($filename)
	{
		$this->filename = $filename;
		$this->config   = new ArrayObject(self::load($filename));
	}
	
	protected static function findLocalStorage($filename)
	{
		global $REX;
		
		if (!file_exists($filename)) {
			throw new Exception('Konfigurationsdatei '.$filename.' konnte nicht gefunden werden.');
		}
		
		$filename     = str_replace('\\', '/', realpath($filename));                // "/var/www/web01/redaxo/include/addon/config.yaml"
		$projectBase  = str_replace('\\', '/', realpath($REX['FRONTEND_PATH']));    // "/var/www/web01"
		$relativeFile = str_replace($projectBase.'/', '', $filename);               // "redaxo/include/addon/config.yaml"
		$localDir     = $REX['DYNFOLDER'].'/internal/sally/config';
		$localFile    = $localDir.'/'.str_replace('/', '_', $relativeFile).'.php';  // "/data/dyn/../redaxo_include_addon_config.yaml"
		
		if (!is_dir($localDir) && !mkdir($localDir, '0755', true)) {
			throw new Exception('Cache-Verzeichnis '.$localDir.' konnte nicht erzeugt werden.');
		}
		
		return array('local' => $localFile, 'const' => $filename);
	}
	
	public static function load($filename)
	{
		$store    = self::findLocalStorage($filename);
		$const    = $store['const'];
		$local    = $store['local'];
		$hasLocal = file_exists($local);
		
		if (!$hasLocal || filemtime($const) > filemtime($local)) {
			$config = sfYaml::load($const);
			file_put_contents($local, '<?php $config = '.var_export($config, true).';');
		}
		else {
			include $local;
		}
		
		return $config;
	}
	
	public function save()
	{
		$store = self::findLocalStorage($this->filename);
		$local = $store['local'];
		$code  = '<?php $config = '.var_export($this->config->getArrayCopy(), true).';';
		return file_put_contents($local, $code) > 0;
	}

	/**
	 * @return sly_Configuration
	 */
	public static function getInstance($filename = null)
	{
		global $REX;
		
		if (!is_string($filename)) {
			$filename = $REX['INCLUDE_PATH'].'/config/sally.yaml';
		}
		
		if (!self::$instances[$filename]) self::$instances[$filename] = new self($filename);
		return self::$instances[$filename];
	}

	public function get($key)
	{
		if (empty($key)) {
			return $this->config->getArrayCopy();
		}
		
		if (strpos($key, '/') === false) {
			return $this->config[$key];
		}
		
		$path = array_filter(explode('/', $key));
		$res  = $this->config;
		
		foreach ($path as $step) {
			if (!array_key_exists($step, $res)) break;
			$res = $res[$step];
		}
		
		return $res;
	}

	public function has($key)
	{
		if (strpos($key, '/') === false) {
			return $this->config->offsetExists($key);
		}
		
		$path = array_filter(explode('/', $key));
		$res  = $this->config;
		
		foreach ($path as $step){
			if (!array_key_exists($step, $res)) return false;
			$res = $res[$step];
		}
		
		return !empty($res);
	}

	public function set($key, $value)
	{
		if (strpos($key, '/') === false) {
			$this->config[$key] = $value;
			return $value;
		}
		
		// Da wir Schreibvorgänge anstoßen werden, arbeiten wir hier explizit
		// mit Referenzen. Ja, Referenzen sind i.d.R. böse, deshalb werden sie auch
		// in get() und has() nicht benutzt. Copy-on-Write und so.
		
		$path = array_filter(explode('/', $key));
		$res  = &$this->config;
		
		foreach ($path as $step) {
			if (!array_key_exists($step, $res)) {
				$res[$step] = array();
			}
			
			$res = &$res[$step];
		}
		
		$res = $value;
		return $value;
	}
	
	public function appendFile($filename, $key = null)
	{
		$data = self::load($filename);
		$this->appendArray($data, $key);
	}
	
	public function appendArray($array, $key = null)
	{
		if ($key !== null) {
			$this->set($key, $array);
		}
		else {
			foreach ($array as $k => $v) $this->config[$k] = $v;
		}
	}
	
	public function offsetExists($index)       { return $this->config->offsetExists($index);       }
	public function offsetGet($index)          { return $this->config->offsetGet($index);          }
	public function offsetSet($index, $newval) { return $this->config->offsetSet($index, $newval); }
	public function offsetUnset($index)        { return $this->config->offsetUnset($index);        }
}
