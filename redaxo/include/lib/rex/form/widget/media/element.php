<?php
/*
 * Copyright (C) 2009 REDAXO
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License Version 2 as published by the
 * Free Software Foundation.
 */

/**
 * @package redaxo4
 */
class rex_form_widget_media_element extends rex_form_element
{
	// 1. Parameter nicht genutzt, muss aber hier stehen,
	// wg einheitlicher Konstrukturparameter
	public function __construct($tag = '', &$table, $attributes = array())
	{
		parent::__construct('', $table, $attributes);
	}

	public function formatElement()
	{
		static $widget_counter = 1;

		$html = rex_var_media::getMediaButton($widget_counter);
		$html = str_replace('REX_MEDIA['.$widget_counter.']', $this->getValue(), $html);
		$html = str_replace('MEDIA['.$widget_counter.']', $this->getAttribute('name'), $html);

		$widget_counter++;
		return $html;
	}
}
