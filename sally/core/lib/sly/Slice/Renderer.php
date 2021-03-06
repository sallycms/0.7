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
 * @since  0.6
 * @author zozi@webvariants.de
 */
class sly_Slice_Renderer {
	private $moduleName;
	private $values;

	/**
	 * @param array $values
	 */
	public function __construct($moduleName, array $values = array()) {
		$this->moduleName = $moduleName;
		$this->setValues($values);
	}

	/**
	 * sets the values that can be displayed
	 *
	 * @param array $values
	 */
	public function setValues($values) {
		if (!sly_Util_Array::isAssoc($values)) {
			throw new sly_Exception('Values must be assoc array!');
		}

		$this->values = $values;
	}

	public function renderInput($dataIndex) {
		$service  = sly_Service_Factory::getModuleService();
		$filename = $service->getFolder().DIRECTORY_SEPARATOR.$service->getInputFilename($this->moduleName);
		$values   = new sly_Slice_Values($this->values);
		$form     = new sly_Slice_Form();

		unset($service);
		ob_start();

		try {
			include $filename;

			if ($form instanceof sly_Form) {
				$form->setSubmitButton(null);
				$form->setResetButton(null);

				print $form->render($dataIndex);
			}

			return ob_get_clean();
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}
	}

	public function renderOutput(sly_Model_ISlice $slice) {
		// Allow addOns to modify/switch the slice before rendering it.
		// If no slice instance is returned, then it's assumed that the slice
		// shall not be rendered and an empty string is returned.
		$dispatcher = sly_Core::dispatcher();
		$slice      = $dispatcher->filter('SLY_SLICE_PRE_RENDER', $slice, array('module' => $this->moduleName));

		if (!($slice instanceof sly_Model_ISlice)) {
			return '';
		}

		$service  = sly_Service_Factory::getModuleService();
		$filename = $service->getFolder().DIRECTORY_SEPARATOR.$service->getOutputFilename($this->moduleName);
		$values   = new sly_Slice_Values($this->values);

		unset($service, $dispatcher);
		ob_start();

		try {
			include $filename;

			$output = ob_get_clean();
			$output = $this->replaceLinks($output);
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}

		// Allow addOns to alter the output before it's returned.
		$dispatcher = sly_Core::dispatcher();
		$output     = $dispatcher->filter('SLY_SLICE_POST_RENDER', $output, array(
			'slice'  => func_get_arg(0),
			'module' => $this->moduleName
		));

		return $output;
	}

	/**
	 * Replaces sally://ARTICLEID and sally://ARTICLEID-CLANGID in
	 * the slice content by article http URLs.
	 *
	 * @param string $content
	 * @return string
	 */
	protected function replaceLinks($content) {
		return sly_Util_HTML::replaceSallyLinks($content);
	}
}
