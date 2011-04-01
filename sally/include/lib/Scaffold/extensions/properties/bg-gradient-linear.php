<?php

/**
 * @license BSD License
 */

/**
 * Creates a gradient with CSS3.
 *
 * usage:
 *   bg-gradient-linear: $startPos, $endPos, $color1 $startPos1, $color2 $startPos2[, $color3 $startPos3[, ...]]
 *
 * examples:
 *   bg-gradient-linear: 50% 0%, 50% 100%, #802647 0, #000 0.5, #802647 1;
 *
 *   bg-gradient-linear: center top, center bottom, red 0, black 0.5, red 1;
 *
 *   bg-gradient-linear: left bottom, right top, green, blue;
 *
 *
 * @author robert.koppsieker@webvariants.de
 * @param  string $start   Start position of gradient
 * @param  string $end     End position of gradient
 * @param  string $colors  Array with colors and positions
 * @return string          The properties
 */
function Scaffold_bg_gradient_linear($params) {

	if (!is_string($params) || !$params) throw new Exception('paramerer is not a string');

	// replace commas with tilde outside of brackets
	$params = str_split($params);
	$bracketOpen = false;
	$bracketCounter = 0;
	for ($i = 0; $i < count($params); $i++) {
		if ($params[$i] == '(') {
			$bracketOpen = true;
			$bracketCounter++;
			continue;
		}
		elseif ($params[$i] == ')') {
			$bracketCounter--;
			if ($bracketCounter < 1) $bracketOpen = false;
			continue;
		}
		if (!$bracketOpen && $params[$i] == ',') $params[$i] = '~';
	}
	$params = implode('', $params);
	
	$params = explode('~', $params);
	if (count($params) < 3) throw new Exception('function expects at least 3 parameters');

	$startPos = trim(array_shift($params));
	$endPos   = trim(array_shift($params));
	$colors   = $params;

	for ($i = 0; $i < count($colors); $i++) {
		$color = trim($colors[$i]);
		$colors[$i] = array();
		$pos = strpos($color, ' ');
		if ($pos !== false) {
			$colors[$i]['color'] = substr($color, 0, $pos);
			$colors[$i]['offset'] = substr($color, $pos);
		}
		else {
			$colors[$i]['color'] = $color;
		}
	}

	$css = Gradient::getLinear($startPos, $endPos, $colors);

	return $css;
}