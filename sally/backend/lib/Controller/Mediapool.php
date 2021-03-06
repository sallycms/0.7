<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $warning;
	protected $info;
	protected $category;
	protected $selectBox;
	protected $categories;
	protected $action;
	protected $popupHelper;

	private $init = false;

	protected function init($action = '') {
		if ($this->init) return;
		$this->init = true;

		// load our i18n stuff
		sly_Core::getI18N()->appendFile(SLY_SALLYFOLDER.'/backend/lang/pages/mediapool/');

		// init custom query string params
		$params = array('callback' => 'string', 'args' => 'array');

		$this->popupHelper = new sly_Helper_Popup($params, 'SLY_MEDIAPOOL_URL_PARAMS');
		$this->popupHelper->init();

		$this->info    = sly_request('info', 'string', '');
		$this->warning = sly_request('warning', 'string', '');
		$this->action  = $action;

		// init category filter
		$cats = $this->popupHelper->getArgument('categories');

		// do NOT use empty(), as '0' is a valid value!
		if (strlen($cats) > 0) {
			$cats             = array_unique(array_map('intval', explode('|', $cats)));
			$this->categories = count($cats) === 0 ? null : $cats;
		}

		$this->getCurrentCategory();

		// build navigation

		$layout = sly_Core::getLayout();
		$nav    = $layout->getNavigation();
		$page   = $nav->find('mediapool');

		if ($page) {
			$cur     = sly_Core::getCurrentControllerName();
			$values  = $this->popupHelper->getValues();
			$subline = array(
				array('mediapool',        t('media_list')),
				array('mediapool_upload', t('upload_file'))
			);

			if ($this->isMediaAdmin()) {
				$subline[] = array('mediapool_structure', t('categories'));
				$subline[] = array('mediapool_sync',      t('sync_files'));
			}

			foreach ($subline as $item) {
				$sp = $page->addSubpage($item[0], $item[1]);

				if (!empty($values)) {
					$sp->setExtraParams($values);

					// ignore the extra params when detecting the current page
					if ($cur === $item[0]) $sp->forceStatus(true);
				}
			}
		}

		$page = sly_Core::dispatcher()->filter('SLY_MEDIAPOOL_MENU', $page);

		$layout->showNavigation(false);
		$layout->pageHeader(t('media_list'), $page);
		$layout->setBodyAttr('class', 'sly-popup sly-mediapool');

		$this->render('mediapool/javascript.phtml', array(), false);
	}

	protected function appendQueryString($url, $separator = '&amp;') {
		return $this->popupHelper->appendQueryString($url, $separator);
	}

	protected function appendParamsToForm(sly_Form $form) {
		return $this->popupHelper->appendParamsToForm($form);
	}

	protected function getCurrentCategory() {
		if ($this->category === null) {
			$category = sly_request('category', 'int', -1);
			$service  = sly_Service_Factory::getMediaCategoryService();

			if ($category == -1) {
				$category = sly_Util_Session::get('media[category]', 'int');
			}

			// respect category filter
			if (!empty($this->categories) && !in_array($category, $this->categories)) {
				$category = reset($this->categories);
			}

			$category = $service->findById($category);
			$category = $category ? $category->getId() : 0;

			sly_util_Session::set('media[category]', $category);
			$this->category = $category;
		}

		return $this->category;
	}

	protected function getOpenerLink(sly_Model_Medium $file) {
		$link     = '';
		$callback = $this->popupHelper->get('callback');

		if (!empty($callback)) {
			$filename = $file->getFilename();
			$title    = $file->getTitle();
			$link     = '<a href="#" data-filename="'.sly_html($filename).'" data-title="'.sly_html($title).'">'.t('apply_file').'</a>';
		}

		return $link;
	}

	protected function getFiles() {
		$cat   = $this->getCurrentCategory();
		$where = 'f.category_id = '.$cat;
		$where = sly_Core::dispatcher()->filter('SLY_MEDIA_LIST_QUERY', $where, array('category_id' => $cat));
		$where = '('.$where.')';
		$types = $this->popupHelper->getArgument('types');

		if (!empty($types)) {
			$types = explode('|', preg_replace('#[^a-z0-9/+.-|]#i', '', $types));

			if (!empty($types)) {
				$where .= ' AND filetype IN ("'.implode('","', $types).'")';
			}
		}

		$db     = sly_DB_Persistence::getInstance();
		$prefix = sly_Core::getTablePrefix();
		$query  = 'SELECT f.id FROM '.$prefix.'file f LEFT JOIN '.$prefix.'file_category c ON f.category_id = c.id WHERE '.$where.' ORDER BY f.updatedate DESC';
		$files  = array();

		$db->query($query);

		foreach ($db as $row) {
			$files[$row['id']] = sly_Util_Medium::findById($row['id']);
		}

		return $files;
	}

	public function indexAction() {
		$this->init('index');

		$files = $this->getFiles();

		$this->render('mediapool/toolbar.phtml', array(), false);

		if (empty($files)) {
			print sly_Helper_Message::info(t('no_media_found'));
		}
		else {
			$this->render('mediapool/index.phtml', compact('files'), false);
		}
	}

	public function batchAction() {
		$this->init('batch');

		if (!empty($_POST['delete'])) {
			return $this->deleteAction();
		}

		return $this->moveAction();
	}

	public function moveAction() {
		$this->init('move');

		if (!$this->isMediaAdmin()) {
			return $this->indexAction();
		}

		$media = sly_postArray('selectedmedia', 'int');

		if (empty($media)) {
			$this->warning = t('no_files_selected');
			return $this->indexAction();
		}

		$service = sly_Service_Factory::getMediumService();

		foreach ($media as $mediumID) {
			$medium = sly_Util_Medium::findById($mediumID);
			if (!$medium) continue;

			$medium->setCategoryId($this->category);
			$service->update($medium);
		}

		// refresh asset cache in case permissions have changed
		$this->revalidate();

		$this->info = t('selected_files_moved');
		$this->indexAction();
	}

	public function deleteAction() {
		$this->init('delete');

		if (!$this->isMediaAdmin()) {
			return $this->indexAction();
		}

		$files = sly_postArray('selectedmedia', 'int');

		if (empty($files)) {
			$this->warning = t('no_files_selected');
			return $this->indexAction();
		}

		foreach ($files as $fileID) {
			$media = sly_Util_Medium::findById($fileID);

			if ($media) {
				$retval = $this->deleteMedia($media);
			}
			else {
				$this->warning[] = t('file_not_found', $fileID);
			}
		}

		$this->indexAction();
	}

	protected function deleteMedia(sly_Model_Medium $medium) {
		$filename = $medium->getFileName();
		$user     = sly_Util_User::getCurrentUser();

		// TODO: Is $this->isMediaAdmin() redundant? The user rights are already checked in delete()...

		if ($this->isMediaAdmin() || $user->hasRight('mediacategory', 'access', $medium->getCategoryId())) {
			$usages = $this->isInUse($medium);

			if ($usages === false) {
				$service = sly_Service_Factory::getMediumService();

				try {
					$service->deleteByMedium($medium);
					$this->revalidate();
					$this->info[] = t('medium_deleted');
				}
				catch (sly_Exception $e) {
					$this->warning[] = $e->getMessage();
				}
			}
			else {
				$tmp   = array();
				$tmp[] = t('file_delete_error_1', $filename).' '.t('file_delete_error_2').'<br />';
				$tmp[] = '<ul>';

				foreach ($usages as $usage) {
					if (!empty($usage['link'])) {
						$tmp[] = '<li><a href="javascript:openPage(\''.sly_html($usage['link']).'\')">'.sly_html($usage['title']).'</a></li>';
					}
					else {
						$tmp[] = '<li>'.sly_html($usage['title']).'</li>';
					}
				}

				$tmp[] = '</ul>';
				$this->warning[] = implode("\n", $tmp);
			}
		}
		else {
			$this->warning[] = t('no_permission');
		}
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('pages', 'mediapool'));
	}

	protected function isMediaAdmin() {
		$user = sly_Util_User::getCurrentUser();
		return $user->isAdmin() || $user->hasRight('mediacategory', 'access', sly_Authorisation_ListProvider::ALL);
	}

	protected function canAccessFile(sly_Model_Medium $medium) {
		return $this->canAccessCategory($medium->getCategoryId());
	}

	protected function canAccessCategory($cat) {
		$user = sly_Util_User::getCurrentUser();
		return $this->isMediaAdmin() || $user->hasRight('mediacategory', 'access', intval($cat));
	}

	protected function getCategorySelect() {
		$user = sly_Util_User::getCurrentUser();

		if ($this->selectBox === null) {
			$this->selectBox = sly_Form_Helper::getMediaCategorySelect('category', null, $user);
			$this->selectBox->setLabel(t('categories'));
			$this->selectBox->setMultiple(false);
			$this->selectBox->setAttribute('value', $this->getCurrentCategory());

			// filter categories
			if (!empty($this->categories)) {
				$values = array_keys($this->selectBox->getValues());

				foreach ($values as $catID) {
					if (!in_array($catID, $this->categories)) {
						$this->selectBox->removeValue($catID);
					}
				}
			}
		}

		return $this->selectBox;
	}

	protected function getDimensions($width, $height, $maxWidth, $maxHeight) {
		if ($width > $maxWidth) {
			$factor  = (float) $maxWidth / $width;
			$width   = $maxWidth;
			$height *= $factor;
		}

		if ($height > $maxHeight) {
			$factor  = (float) $maxHeight / $height;
			$height  = $maxHeight;
			$width  *= $factor;
		}

		return array(ceil($width), ceil($height));
	}

	protected function isDocType(sly_Model_Medium $medium) {
		static $docTypes = array(
			'bmp', 'css', 'doc', 'docx', 'eps', 'gif', 'gz', 'jpg', 'mov', 'mp3',
			'ogg', 'pdf', 'png', 'ppt', 'pptx','pps', 'ppsx', 'rar', 'rtf', 'swf',
			'tar', 'tif', 'txt', 'wma', 'xls', 'xlsx', 'zip'
		);

		return in_array($medium->getExtension(), $docTypes);
	}

	protected function isImage(sly_Model_Medium $medium) {
		static $exts = array('gif', 'jpeg', 'jpg', 'png', 'bmp', 'tif', 'tiff', 'webp');
		return in_array($medium->getExtension(), $exts);
	}

	protected function isInUse(sly_Model_Medium $medium) {
		$sql      = sly_DB_Persistence::getInstance();
		$filename = $medium->getFilename();
		$prefix   = sly_Core::getTablePrefix();
		$query    =
			'SELECT s.article_id, s.clang FROM '.$prefix.'slice sv, '.$prefix.'article_slice s, '.$prefix.'article a '.
			'WHERE sv.id = s.slice_id AND a.id = s.article_id AND a.clang = s.clang '.
			'AND serialized_values REGEXP ? GROUP BY s.article_id, s.clang';

		$res    = array();
		$usages = array();
		$b      = '[^[:alnum:]_+-]'; // more or less like a \b in PCRE
		$quoted = str_replace(array('.', '+'), array('\.', '\+'), $filename);
		$data   = array("(^|$b)$quoted(\$|$b)");

		$sql->query($query, $data);
		foreach ($sql as $row) $res[] = $row;

		foreach ($res as $row) {
			$article = sly_Util_Article::findById($row['article_id'], $row['clang']);

			$usages[] = array(
				'title' => $article->getName(),
				'type'  => 'sly-article',
				'link'  => 'index.php?page=content&article_id='.$row['article_id'].'&mode=edit&clang='.$row['clang']
			);
		}

		$usages = sly_Core::dispatcher()->filter('SLY_MEDIA_USAGES', $usages, array(
			'filename' => $medium->getFilename(),
			'media'    => $medium
		));

		return empty($usages) ? false : $usages;
	}

	protected function revalidate() {
		// re-validate asset cache
		sly_Service_Factory::getAssetService()->validateCache();
	}
}
