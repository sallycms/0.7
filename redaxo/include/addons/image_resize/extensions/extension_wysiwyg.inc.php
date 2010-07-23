<?php
/*
 * Copyright (C) 2009 REDAXO
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License Version 2 as published by the
 * Free Software Foundation.
 */

/**
 * Image-Resize Addon
 *
 * @author office[at]vscope[dot]at Wolfgang Hutteger
 * @author <a href="http://www.vscope.at">www.vscope.at</a>
 *
 * @author markus[dot]staab[at]redaxo[dot]de Markus Staab
 *
 * @package redaxo4
 */

// Resize WYSIWYG Editor Images
function rex_resize_wysiwyg_output($params)
{
	global $REX;

	$content = $params['subject'];

	preg_match_all('/<img[^>]*?ismap="ismap"[^>]*?>/is', $content, $matches);

	if (is_array($matches[0])) {
		foreach ($matches[0] as $var) {
			preg_match('/width="(.*?)"/is', $var, $width);
			if (!$width) preg_match('/width: (.*?)px/is', $var, $width);

			preg_match('/height="(.*?)"/is', $var, $height);
			if (!$height) preg_match('/height: (.*?)px/is', $var, $height);

			if ($width) {
				preg_match('/src="(.*?files\/(.*?))"/is', $var, $src);

				if (file_exists($REX['HTDOCS_PATH'].'files/'.$src[2])) {
					$realsize = getimagesize($REX['HTDOCS_PATH'].'files/'.$src[2]);

					if ($realsize[0] != $width[1] || $realsize[1] != $height[1]) {
						$newsrc   = $REX['FRONTEND_FILE'].'?rex_resize='.$width[1].'w__'.$height[1].'h__'.$src[2];
						$newimage = str_replace(array($src[1], 'ismap="ismap" '), array($newsrc, ''), $var);
						$content  = str_replace($var, $newimage, $content);
					}
					elseif ($REX['REDAXO']) {
						$newsrc   = $REX['HTDOCS_PATH'].'files/'.$src[2];
						$newimage = str_replace(array($src[1], 'ismap="ismap" '), array($newsrc, ''), $var);
						$content  = str_replace($var, $newimage, $content);
					}
				}
			}
		}
	}

	return $content;
}
