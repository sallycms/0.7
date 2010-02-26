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
 * DB Model Klasse für Slice Values 
 * 
 * @author zozi@webvariants.de
 *
 */
class Service_SliceValue extends Service_Base{

	protected $tablename = 'slice_value';
	
	protected function makeObject(array $params){
		return new Model_SliceValue($params);
	}
	
	public function findBySliceTypeFinder($slice_id, $type, $finder){
		$where = array('slice_id' => $slice_id, 'type' => $type, 'finder' => $finder);

		$res = $this->find($where);
    	if(count($res) == 1) return $res[0];
    	
    	return null;
	}

}
