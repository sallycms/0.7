<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Service_FileYAMLTest extends PHPUnit_Framework_TestCase {
	private $service;
	private $data;

	public function setUp() {
		$this->service = new sly_Service_File_YAML();
		$this->data    = array('yaml' => array('dump' => 'test'));
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testLoadBroken() {
		$this->service->load(SLY_TESTING_ROOT.'/sally/tests/files/fuckedUpYaml.yml');
	}

	public function testDump() {
		$testfile  = SLY_TESTING_ROOT.'/sally/tests/files/goodYamldump.yml';
		$checkfile = SLY_TESTING_ROOT.'/sally/tests/files/goodYaml.yml';
		$this->service->dump($testfile, $this->data);
		$this->assertFileEquals($checkfile, $testfile, 'Dumping YAML failed.');
		unlink($testfile);
	}

	public function testLoad() {
		$data = $this->service->load(SLY_TESTING_ROOT.'/sally/tests/files/goodYaml.yml');
		$this->assertEquals($this->data, $data, 'loading YAML file failed');
	}
}