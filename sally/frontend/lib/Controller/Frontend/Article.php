<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Frontend_Article extends sly_Controller_Frontend_Base {
	private $notFound = false;

	public function __construct() {
		sly_Core::dispatcher()->register('SLY_RESOLVE_ARTICLE', array($this, 'oldSchoolResolver'));
	}

	public function indexAction() {
		$article = $this->findArticle();

		if ($article) {
			// preselect the HTTP response code
			$this->prepareResponse($article);

			// set the article data in sly_Core
			sly_Core::setCurrentArticleId($article->getId());
			sly_Core::setCurrentClang($article->getClang());

			// now that we know the frontend language, init the global i18n object
			$i18n = sly_Core::getI18N();
			$i18n->setLocale(strtolower(sly_Util_Language::getLocale()));
			$i18n->appendFile(SLY_DEVELOPFOLDER.'/lang');

			// notify listeners about the article to be rendered
			sly_Core::dispatcher()->notify('SLY_CURRENT_ARTICLE', $article);

			// finally run the template and generate the output
			$output = $article->getArticleTemplate();

			// article postprocessing is a special task, so here's a special event
			$output = sly_Core::dispatcher()->filter('SLY_ARTICLE_OUTPUT', $output, compact('article'));

			// and print it
			print $output;
		}
		else {
			// If we got here, not even the 404 article could be found. Ouch.
			print t('no_startarticle', 'backend/index.php');
		}
	}

	protected function prepareResponse(sly_Model_Article $article) {
		$lastMod  = sly_Core::config()->get('USE_LAST_MODIFIED');
		$response = sly_Core::getResponse();

		// handle 404
		if ($this->notFound) {
			$response->setStatusCode(404);
		}

		// optionally send Last-Modified header
		if ($lastMod === true || $lastMod === 'frontend') {
			$response->setLastModified($article->getUpdateDate());
		}
	}

	protected function findArticle() {
		$article = sly_Core::dispatcher()->filter('SLY_RESOLVE_ARTICLE', null);

		// Did all listeners behave?
		if ($article !== null && !($article instanceof sly_Model_Article)) {
			throw new LogicException('Listeners to SLY_RESOLVE_ARTICLE are required to return a sly_Model_Article instance.');
		}

		// If no article could be found or it has no template, display the not-found article.
		if ($article === null || !$article->getTemplateName()) {
			$this->notFound = true;
			$article = sly_Util_Article::findById(sly_Core::getNotFoundArticleId(), sly_Core::getDefaultClangId());
		}

		return $article;
	}

	public function isNotFound() {
		return $this->notFound;
	}

	public function oldSchoolResolver(array $params) {
		if ($params['subject']) return $params['subject'];

		// we need to know if the params are missing
		$articleID = sly_request('article_id', 'int', null);
		$clangID   = sly_request('clang',      'int', null);
		$isStart   = dirname($_SERVER['PHP_SELF']).'/' === $_SERVER['REQUEST_URI'];

		// it might be the startpage http://example.com/ which has no params
		if ($articleID === null && $isStart) {
			$articleID = sly_Core::getSiteStartArticleId();
		}

		// A wrong language counts as not found!
		// But since we're nice people, we won't just give up and try to use the
		// site's default language, possibly at least showing the requested article.

		if (!sly_Util_Language::exists($clangID)) {
			if (!$isStart) {
				$this->notFound = true;
			}
			$clangID = sly_Core::getDefaultClangId();
		}

		// find the requested article (or give up by returning null)
		return sly_Util_Article::findById($articleID, $clangID);
	}
}
