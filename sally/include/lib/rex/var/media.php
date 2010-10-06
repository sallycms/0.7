<?php
/*
 * Copyright (C) 2009 REDAXO
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License Version 2 as published by the
 * Free Software Foundation.
 */

/**
 * REX_FILE[1],
 * REX_FILELIST[1],
 * REX_FILE_BUTTON[1],
 * REX_FILELIST_BUTTON[1],
 * REX_MEDIA[1],
 * REX_MEDIALIST[1],
 * REX_MEDIA_BUTTON[1],
 * REX_MEDIALIST_BUTTON[1]
 *
 * Alle Variablen die mit REX_FILE beginnnen sind als deprecated anzusehen!
 *
 * @ingroup redaxo
 */
class rex_var_media extends rex_var
{
	// --------------------------------- Actions

	const MEDIA = 'REX_MEDIA';
	const MEDIALIST = 'REX_MEDIALIST';
	const FILE = 'REX_FILE';
	const FILELIST = 'REX_FILELIST';

	function getACRequestValues($REX_ACTION)
	{
		$media = rex_request('MEDIA', 'array');
		foreach($media as $key => $value)
		{
			$REX_ACTION['REX_MEDIA'][$key] = $value;
		}

		$medialist = rex_request('MEDIALIST', 'array');
		foreach($medialist as $key => $value)
		{
			$REX_ACTION['REX_MEDIALIST'][$key] = $value;
		}

		return $REX_ACTION;
	}

	function getACDatabaseValues($REX_ACTION, $slice_id)
	{
		$values = sly_Service_Factory::getService('SliceValue')->find(array('slice_id' => $slice_id, 'type' => 'REX_MEDIA'));
		foreach($values as $value)
		{
			$REX_ACTION['REX_MEDIA'][$value->getFinder()] = $value->getValue();
		}

		$values = sly_Service_Factory::getService('SliceValue')->find(array('slice_id' => $slice_id, 'type' => 'REX_MEDIALIST'));
		foreach($values as $value)
		{
			$REX_ACTION['REX_MEDIALIST'][$value->getFinder()] = $value->getValue();
		}

		return $REX_ACTION;
	}

	function setACValues($slice_id, $REX_ACTION, $escape = false, $prependTableName = true)
	{
		//global $REX;

		//$slice_id = $sql->getValue('slice_id');
		$slice = sly_Service_Factory::getService('Slice')->findById($slice_id);
		if(isset($REX_ACTION['REX_MEDIA'])){
			foreach($REX_ACTION['REX_MEDIA'] as $key => $value){
				$slice->addValue('REX_MEDIA', $key, $value);
			}
		}
		if(isset($REX_ACTION['REX_MEDIALIST'])){
			foreach($REX_ACTION['REX_MEDIALIST'] as $key => $value){
				$slice->addValue('REX_MEDIALIST', $key, $value);
			}
		}
	}

	// --------------------------------- Output

	function getBEInput($slice_id, $content)
	{
		$content = $this->matchMediaButton($slice_id, $content);
		$content = $this->matchMediaListButton($slice_id, $content);
		$content = $this->getOutput($slice_id, $content);
		return $content;
	}

	function getBEOutput($slice_id, $content)
	{
		$content = $this->getOutput($slice_id, $content);
		return $content;
	}

	/**
	 * Ersetzt die Value Platzhalter
	 */
	function getOutput($slice_id, $content)
	{
		$content = $this->matchMedia($slice_id, $content);
		$content = $this->matchMediaList($slice_id, $content);
		return $content;
	}

	/**
	 * @see rex_var::handleDefaultParam
	 */
	function handleDefaultParam($varname, $args, $name, $value)
	{
		switch($name)
		{
			case '1' :
			case 'category' :
				$args['category'] = (int) $value;
				break;
			case 'types' :
				$args[$name] = (string) $value;
				break;
			case 'preview' :
				$args[$name] = (boolean) $value;
				break;
			case 'mimetype' :
				$args[$name] = (string) $value;
				break;
		}
		return parent::handleDefaultParam($varname, $args, $name, $value);
	}

	/**
	 * MediaButton für die Eingabe
	 */
	function matchMediaButton($slice_id, $content)
	{
		$vars = array (
      		'REX_FILE_BUTTON',
      		'REX_MEDIA_BUTTON'
      		);
      		foreach ($vars as $var)
      		{
      			$matches = $this->getVarParams($content, $var);
      			foreach ($matches as $match)
      			{
      				list ($param_str, $args) = $match;
      				list ($id, $args) = $this->extractArg('id', $args, 0);

      				list ($category, $args) = $this->extractArg('category', $args, '');

      				$replace = $this->getMediaButton($id, $category, $args);
      				$replace = $this->handleGlobalWidgetParams($var, $args, $replace);
      				$content = str_replace($var . '[' . $param_str . ']', $replace, $content);
      			}
      		}

      		return $content;
	}

	/**
	 * MediaListButton für die Eingabe
	 */
	function matchMediaListButton($slice_id, $content)
	{
		$vars = array (
      		'REX_FILELIST_BUTTON',
      		'REX_MEDIALIST_BUTTON'
      		);
      		foreach ($vars as $var)
      		{
      			$matches = $this->getVarParams($content, $var);
      			foreach ($matches as $match)
      			{
      				list ($param_str, $args) = $match;
      				list ($id, $args) = $this->extractArg('id', $args, 0);

      				$value = sly_Service_Factory::getService('SliceValue')->findBySliceTypeFinder($slice_id, self::MEDIALIST, $id);
      				if($value){
      					$value = $value->getValue();
      				}else{
      					$value = '';
      				}
      				$category = '';
      				if(isset($args['category']))
      				{
      					$category = $args['category'];
      					unset($args['category']);
      				}

      				$replace = $this->getMedialistButton($id, $value, $category, $args);
      				$replace = $this->handleGlobalWidgetParams($var, $args, $replace);
      				$content = str_replace($var . '[' . $param_str . ']', $replace, $content);
      			}
      		}


      		return $content;
	}

	/**
	 * Wert für die Ausgabe
	 */
	function matchMedia($slice_id, $content)
	{
		$vars = array (
      		self::FILE,
      		self::MEDIA
      		);
      		foreach ($vars as $var)
      		{
      			$matches = $this->getVarParams($content, $var);
      			foreach ($matches as $match)
      			{
      				list ($param_str, $args) = $match;
      				list ($id, $args) = $this->extractArg('id', $args, 0);

      				$value = sly_Service_Factory::getService('SliceValue')->findBySliceTypeFinder($slice_id, self::MEDIA, $id);
      				if($value){
      					$value = $value->getValue();
      				}else{
      					$value = '';
      				}

      				// Mimetype ausgeben
      				if(isset($args['mimetype']))
      				{
      					$OOM = OOMedia::getMediaByName($value);
      					if($OOM)
      					{
      						$replace = $OOM->getType();
      					}
      				}
      				// "normale" ausgabe
      				else
      				{
      					$replace = $value;
      				}

      				$replace = $this->handleGlobalVarParams($var, $args, $replace);
      				$content = str_replace($var . '[' . $param_str . ']', $replace, $content);
      			}
      		}

      		return $content;
	}

	/**
	 * Wert für die Ausgabe
	 */
	function matchMediaList($slice_id, $content)
	{
		$vars = array (
      		self::FILELIST,
      		self::MEDIALIST
      		);
      		foreach ($vars as $var)
      		{
      			$matches = $this->getVarParams($content, $var);
      			foreach ($matches as $match)
      			{
      				list ($param_str, $args) = $match;
      				list ($id, $args) = $this->extractArg('id', $args, 0);

      				$value = sly_Service_Factory::getService('SliceValue')->findBySliceTypeFinder($slice_id, self::MEDIALIST, $id);
      				if($value){
      					$value = $value->getValue();
      				}else{
      					$value = '';
      				}
      				$replace = $this->handleGlobalVarParams($var, $args, $value);
      				$content = str_replace($var . '[' . $param_str . ']', $replace, $content);
      			}
      		}
      		return $content;
	}

	/**
	 * Gibt das Button Template zurück
	 */
	function getMediaButton($id, $category = '', $args = array())
	{
		global $I18N;

		$open_params = '';
		if ($category != '')
		{
			$open_params .= '&amp;rex_file_category=' . $category;
		}

		foreach($args as $aname => $avalue)
		{
			$open_params .= '&amp;args['. urlencode($aname) .']='. urlencode($avalue);
		}

		$wdgtClass = 'rex-widget-media';
		if(isset($args['preview']) && $args['preview'] && OOAddon::isAvailable('image_resize'))
		{
			$wdgtClass .= ' rex-widget-preview';
		}

		$media = '
    <div class="rex-widget">
      <div class="'. $wdgtClass .'">
        <p class="rex-widget-field">
          <input type="text" size="30" name="MEDIA[' . $id . ']" value="REX_MEDIA[' . $id . ']" id="REX_MEDIA_' . $id . '" readonly="readonly" />
        </p>
        <p class="rex-widget-icons">
          <a href="#" class="rex-icon-file-open" onclick="openREXMedia(' . $id . ',\'' . $open_params . '\');return false;"'. rex_tabindex() .'><img src="media/file_open.gif" width="16" height="16" title="'. $I18N->msg('var_media_open') .'" alt="'. $I18N->msg('var_media_open') .'" /></a>
          <a href="#" class="rex-icon-file-add" onclick="addREXMedia(' . $id . ');return false;"'. rex_tabindex() .'><img src="media/file_add.gif" width="16" height="16" title="'. $I18N->msg('var_media_new') .'" alt="'. $I18N->msg('var_media_new') .'" /></a>
          <a href="#" class="rex-icon-file-delete" onclick="deleteREXMedia(' . $id . ');return false;"'. rex_tabindex() .'><img src="media/file_del.gif" width="16" height="16" title="'. $I18N->msg('var_media_remove') .'" alt="'. $I18N->msg('var_media_remove') .'" /></a>
        </p>
        <div class="rex-media-preview"></div>
      </div>
    </div>
		<div class="rex-clearer"></div>
    ';

		return $media;
	}

	/**
	 * Gibt das ListButton Template zurück
	 */
	function getMedialistButton($id, $value, $category = '', $args = array())
	{
		global $I18N;

		$open_params = '';
		if ($category != '')
		{
			$open_params .= '&amp;rex_file_category=' . $category;
		}

		foreach($args as $aname => $avalue)
		{
			$open_params .= '&amp;args['. $aname .']='. urlencode($avalue);
		}

		$wdgtClass = 'rex-widget-medialist';
		if(isset($args['preview']) && $args['preview'] && OOAddon::isAvailable('image_resize'))
		{
			$wdgtClass .= ' rex-widget-preview';
		}

		$options = '';
		$medialistarray = explode(',', $value);
		if (is_array($medialistarray))
		{
			foreach ($medialistarray as $file)
			{
				if ($file != '')
				{
					$options .= '<option value="' . $file . '">' . $file . '</option>';
				}
			}
		}

		$media = '
    <div class="rex-widget">
      <div class="'. $wdgtClass .'">
        <input type="hidden" name="MEDIALIST['. $id .']" id="REX_MEDIALIST_'. $id .'" value="'. $value .'" />
        <p class="rex-widget-field">
          <select name="MEDIALIST_SELECT[' . $id . ']" id="REX_MEDIALIST_SELECT_' . $id . '" size="8"'. rex_tabindex() .'>
            ' . $options . '
          </select>
        </p>
        <p class="rex-widget-icons">
          <a href="#" class="rex-icon-file-top" onclick="moveREXMedialist(' . $id . ',\'top\');return false;"'. rex_tabindex() .'><img src="media/file_top.gif" width="16" height="16" title="'. $I18N->msg('var_medialist_move_top') .'" alt="'. $I18N->msg('var_medialist_move_top') .'" /></a>
          <a href="#" class="rex-icon-file-open" onclick="openREXMedialist(' . $id . ');return false;"'. rex_tabindex() .'><img src="media/file_open.gif" width="16" height="16" title="'. $I18N->msg('var_media_open') .'" alt="'. $I18N->msg('var_media_open') .'" /></a><br />
          <a href="#" class="rex-icon-file-up" onclick="moveREXMedialist(' . $id . ',\'up\');return false;"'. rex_tabindex() .'><img src="media/file_up.gif" width="16" height="16" title="'. $I18N->msg('var_medialist_move_up') .'" alt="'. $I18N->msg('var_medialist_move_top') .'" /></a>
          <a href="#" class="rex-icon-file-add" onclick="addREXMedialist('. $id .');return false;"'. rex_tabindex() .'><img src="media/file_add.gif" width="16" height="16" title="'. $I18N->msg('var_media_new') .'" alt="'. $I18N->msg('var_media_new') .'" /></a><br />
          <a href="#" class="rex-icon-file-down" onclick="moveREXMedialist(' . $id . ',\'down\');return false;"'. rex_tabindex() .'><img src="media/file_down.gif" width="16" height="16" title="'. $I18N->msg('var_medialist_move_down') .'" alt="'. $I18N->msg('var_medialist_move_down') .'" /></a>
          <a href="#" class="rex-icon-file-delete" onclick="deleteREXMedialist(' . $id . ');return false;"'. rex_tabindex() .'><img src="media/file_del.gif" width="16" height="16" title="'. $I18N->msg('var_media_remove') .'" alt="'. $I18N->msg('var_media_remove') .'" /></a><br />
          <a href="#" class="rex-icon-file-bottom" onclick="moveREXMedialist(' . $id . ',\'bottom\');return false;"'. rex_tabindex() .'><img src="media/file_bottom.gif" width="16" height="16" title="'. $I18N->msg('var_medialist_move_bottom') .'" alt="'. $I18N->msg('var_medialist_move_bottom') .'" /></a>
        </p>
        <div class="rex-media-preview"></div>
      </div>
    </div>
	 	<div class="rex-clearer"></div>
    ';

		return $media;
	}

}