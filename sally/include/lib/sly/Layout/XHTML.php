<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup layout
 */
class sly_Layout_XHTML extends sly_Layout {
	protected $isTransitional = false;

	protected function setTransitional() {
		$this->isTransitional = true;
	}

	protected function printCSSConcrete() {
		print "\t<style type=\"text/css\">$this->cssCode\n</style>\n";
	}

	protected function printCSSFilesConcrete() {
		foreach ($this->cssFiles as $group => $medias) {
			$isConditional = strtoupper(substr($group, 0, 3)) == 'IF ';

			if ($isConditional) print "\t<!--[if ".strtoupper(substr($group, 3))."]>\n";

			foreach ($medias as $media => $files) {
				foreach ($files as $file) {
					print "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$file[src]\" media=\"$media\" />\n";
				}
			}

			if ($isConditional) print "\t<![endif]-->\n";
		}
	}

	protected function printJavaScriptConcrete() {
		print "\t".'<script type="text/javascript">'.trim($this->javaScriptCode).'</script>';
	}

	protected function printJavaScriptFilesConcrete() {
		foreach ($this->javaScriptFiles as $files) {
			$this->printHeadElements("\t".'<script type="text/javascript" src="%2$s"></script>'."\n", $files);
		}
	}

	protected function printBodyAttrs() {
		$this->printHeadElements(' %s="%s"', $this->bodyAttrs);
	}

	protected function printMetas() {
		$this->printHeadElements("\t".'<meta name="%s" content="%s" />'."\n", $this->metas);
	}

	protected function printHttpMetas() {
		$this->printHeadElements("\t".'<meta http-equiv="%s" content="%s" />'."\n", $this->httpMetas);
	}

	protected function printLink($attributes) {
		print "\t".'<link '.sly_Util_HTML::buildAttributeString($attributes)."/>\n";
	}

	public function printHeader() {
		$this->renderView('views/layout/xhtml/head.phtml');
	}

	private function printHeadElements($format, $data) {
		foreach ($data as $key => $value) {
			printf($format, sly_html(trim($key)), sly_html(trim($value)));
		}
	}
}
