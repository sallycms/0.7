<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Form_MediaListButton extends sly_Form_ElementBase implements sly_Form_IElement
{
	protected $javascriptID;

	public function __construct($name, $label, $value, $javascriptID, $id = null, $allowedAttributes = null)
	{
		if ($allowedAttributes === null) {
			$allowedAttributes = array('value', 'name', 'id', 'disabled', 'class', 'maxlength', 'readonly', 'style');
		}

		parent::__construct($name, $label, $value, $id, $allowedAttributes);
		$this->setAttribute('class', 'rex-form-select');
		$this->javascriptID = $javascriptID;
	}

	public function render()
	{
		// Prüfen, ob das Formular bereits abgeschickt und noch einmal angezeigt
		// werden soll. Falls ja, übernehmen wir den Wert aus den POST-Daten.

		$name = $this->attributes['name'];

		if (isset($_POST[$name]) && strlen($_POST[$name]) > 0) {
			$this->attributes['value'] = sly_postArray($name, 'string');
		}

		return $this->renderFilename('form/medialistbutton.phtml');
	}
}
