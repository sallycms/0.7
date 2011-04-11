<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_FunctionsTest extends PHPUnit_Framework_TestCase {
	public function testSlyMakeArray() {
		$this->assertEquals(sly_makeArray(null),         array());
		$this->assertEquals(sly_makeArray(1),            array(1));
		$this->assertEquals(sly_makeArray(true),         array(true));
		$this->assertEquals(sly_makeArray(false),        array(false));
		$this->assertEquals(sly_makeArray(array()),      array());
		$this->assertEquals(sly_makeArray(array(1,2,3)), array(1,2,3));
	}

	public function testTruncate() {
		$testText = 'Hi. I am just a small text.';

		$this->assertEquals('Hi. I a...', truncate($testText, 10, '...', true));
		$this->assertEquals('Hi. I am j', truncate($testText, 10, '', true));

		$this->assertEquals('', truncate($testText, 0));

		$this->assertEquals('Hi. I...', truncate($testText, 8, '...', false));
		$this->assertEquals('Hi. I...', truncate($testText, 9, '...', false));
		$this->assertEquals('Hi. I...', truncate($testText, 10, '...', false));
		$this->assertEquals('Hi. I am...', truncate($testText, 11, '...', false));
		$this->assertEquals('Hi. I am...', truncate($testText, 12, '...', false));
	}

	public function testRexSplitString() {
		$this->assertEquals(array('a', 'b c', 'de', 'f g ', 'bla'), rex_split_string('a "b c" de \'f g \' bla'));
	}

	public function testRexParamString() {
		$this->assertEquals('&foo=bar', rex_param_string(array('foo' => 'bar'), '&'));
		$this->assertEquals('&foo=%C3%9F%24', rex_param_string(array('foo' => 'ß$'), '&'));
		$this->assertEquals('foo=bar', 'foo=bar');
	}
}