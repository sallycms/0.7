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
 * @ingroup cache
 */
interface sly_Cache_ISeekable extends sly_Cache_IFlushable {
	public function find($namespace, $key = '*', $getKey = false, $recursive = false);
	public function getAll($namespace, $recursive = false);
	public function getElementCount($namespace, $recursive = false);
	public function getSize($namespace, $recursive = false);
}
