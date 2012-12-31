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
 * @ingroup util
 */
class sly_Util_Template {
	/**
	 * render a given template
	 *
	 * This method includes a template identified by its name.
	 * A unlimited number of variabled can be given to the templates
	 * through an associated array like array('varname' => 'value' ...).
	 *
	 * @param string $name    the template name
	 * @param array  $params  template variables as an associative array of parameters
	 */
	public static function render($name, array $params = array()) {
		try {
			sly_Service_Factory::getTemplateService()->includeFile($name, $params);
		}
		catch (sly_Service_Template_Exception $e) {
			print $e->getMessage();
		}
	}

	/**
	 * Can be called inside of a template and wraps the given outer template ($name, $params)
	 * around the inner template. The outer template gets the content of the inner template
	 * by the paramater named by $contentName.
	 * This must be called before the first output of the inner template.
	 *
	 * @param string $name         template name
	 * @param array  $params       array of params to be available in the template
	 * @param string $contentName  name of the content variable
	 */
	public static function wrap($name, array $params = array(), $contentName = 'content') {
		try {
			sly_Service_Factory::getTemplateService()->wrapFile($name, $params, $contentName);
		}
		catch (sly_Service_Template_Exception $e) {
			print $e->getMessage();
		}
	}

	/**
	 * render a template and return its content
	 *
	 * @throws sly_Exception   if an exception is thrown inside the template
	 * @param  string $name    template name
	 * @param  array  $params  template variables as an associative array of parameters
	 * @return string          rendered content
	 */
	public static function renderAsString($name, array $params = array()) {
		try {
			ob_start();
			self::render($name, $params);
			return ob_get_clean();
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}
	}

	/**
	 * checks if a template exists
	 *
	 * @param  string $name
	 * @return boolean
	 */
	public static function exists($name) {
		return sly_Service_Factory::getTemplateService()->exists($name);
	}
}
