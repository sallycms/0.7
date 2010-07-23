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

/*
// TODOS:
// - alles vereinfachen
// - <? ?> $ Problematik bei REX_ACTION
*/

unset ($REX_ACTION);

$category_id = sly_request('category_id', 'rex-category-id');
$article_id  = sly_request('article_id',  'rex-article-id');
$clang       = sly_request('clang',       'rex-clang-id', $REX['START_CLANG_ID']);
$slice_id    = sly_request('slice_id',    'rex-slice-id', '');
$function    = sly_request('function',    'string');

$article_revision = 0;
$slice_revision   = 0;
$warning          = '';
$global_warning   = '';
$info             = '';
$global_info      = '';

require $REX['INCLUDE_PATH'].'/functions/function_rex_content.inc.php';

$article = new rex_sql();
$article->setQuery('SELECT startpage, name, re_id, template FROM #_article a WHERE a.id = '.$article_id.' AND clang = '.$clang, '#_');

if ($article->getRows() == 1) {
	$service      = sly_Service_Factory::getService('Template');
	$templateName = $article->getValue('template');

	// Slot validieren

	$REX['CTYPE'] = $service->getSlots($templateName);
	$slot         = rex_request('ctype', 'rex-ctype-id', 0);

	if (!array_key_exists($slot, $REX['CTYPE'])) {
		$slot = 0;
	}

	// Artikel wurde gefunden - Kategorie holen

	$OOArt       = OOArticle::getArticleById($article_id, $clang);
	$category_id = $OOArt->getCategoryId();

	// Kategoriepfad und -rechte

	require $REX['INCLUDE_PATH'].'/functions/function_rex_category.inc.php';
	// $KATout kommt aus dem include
	// $KATPERM

	if ($REX['PAGE'] == 'content' && $article_id > 0) {
		$KATout .= '<p>';

		if ($article->getValue('startpage') == 1) {
			$KATout .= $I18N->msg('start_article').' : ';
		}
		else {
			$KATout .= $I18N->msg('article').' : ';
		}

		$catname = str_replace(' ', '&nbsp;', sly_html($article->getValue('name')));

		$KATout .= '<a href="index.php?page=content&amp;article_id='.$article_id.'&amp;mode=edit&amp;clang='.$clang.'">'.$catname.'</a>';
		$KATout .= '</p>';
	}

	// Titel anzeigen

	rex_title($I18N->msg('content'), $KATout);

	// Request Parameter

	$mode     = sly_request('mode', 'string', 'edit');
	$function = sly_request('function', 'string');
	$warning  = sly_request('warning', 'string');
	$info     = sly_request('info', 'string');

	// Sprachenblock

	$sprachen_add = '&amp;mode='.$mode.'&amp;category_id='.$category_id.'&amp;article_id='.$article_id;
	require $REX['INCLUDE_PATH'].'/functions/function_rex_languages.inc.php';

	// EXTENSION POINT

	print rex_register_extension_point('PAGE_CONTENT_HEADER', '', array(
		'article_id'       => $article_id,
		'clang'            => $clang,
		'function'         => $function,
		'mode'             => $mode,
		'slice_id'         => $slice_id,
		'page'             => 'content',
		'slot'             => $slot,
		'ctype'            => $slot, // REDAXO-Kompatibilität
		'category_id'      => $category_id,
		'article_revision' => &$article_revision,
		'slice_revision'   => &$slice_revision
	));

	// Rechte prüfen

	if (!($KATPERM || $REX['USER']->hasPerm('article['.$article_id.']'))) {
		// keine Rechte
		print rex_warning($I18N->msg('no_rights_to_edit'));
	}
	else {
		// Slice add/edit/delete

		$moduleService = sly_Service_Factory::getService('Module');

		if (rex_request('save', 'boolean') && in_array($function, array('add', 'edit', 'delete'))) {
			// check module

			if ($function == 'edit' || $function == 'delete') {
				$module = rex_slice_module_exists($slice_id, $clang);
			}
			else { // add
				$module = sly_post('module', 'string');
			}

			if (!$moduleService->exists($module)) {
				$global_warning = $I18N->msg('module_not_found');
				$slice_id       = '';
				$function       = '';
			}
			else {
				// Rechte am Modul

				$templateService = sly_Service_Factory::getService('Template');

				if (!$templateService->hasModule($templateName, $slot, $module)) {
					$global_warning = $I18N->msg('no_rights_to_this_function');
					$slice_id       = '';
					$function       = '';
				}
				elseif (!($REX['USER']->isAdmin() || $REX['USER']->hasPerm('module['.$module.']') || $REX['USER']->hasPerm('module[0]'))) {
					$global_warning = $I18N->msg('no_rights_to_this_function');
					$slice_id       = '';
					$function       = '';
				}
				else {
					// Daten einlesen

					$REX_ACTION         = array();
					$REX_ACTION['SAVE'] = true;

					foreach (sly_Core::getVarTypes() as $idx => $obj) {
						$REX_ACTION = $obj->getACRequestValues($REX_ACTION);
					}

					// ----- PRE SAVE ACTION [ADD/EDIT/DELETE]

					list($action_message, $REX_ACTION) = rex_execPreSaveAction($module, $function, $REX_ACTION);

					// Statusspeicherung für die rex_article Klasse

					$REX['ACTION'] = $REX_ACTION;

					// Werte werden aus den REX_ACTIONS übernommen wenn SAVE=true

					if (!$REX_ACTION['SAVE']) {
						// DONT SAVE/UPDATE SLICE
						if (!empty($action_message)) {
							$warning = $action_message;
						}
						elseif ($function == 'delete') {
							$warning = $I18N->msg('slice_deleted_error');
						}
						else {
							$warning = $I18N->msg('slice_saved_error');
						}
					}
					else {
						// SAVE / UPDATE SLICE

						if ($function == 'add' || $function == 'edit') {
							$newsql = new rex_sql();
							$newsql->setTable('article_slice', true);

							if ($function == 'edit') {
								$ooslice = OOArticleSlice::getArticleSliceById($slice_id);
								$realslice = sly_Service_Factory::getService('Slice')->findById($ooslice->getSliceId());
								$realslice->flushValues();
								unset($ooslice);
								$newsql->setWhere('id = '.$slice_id);
								$newsql->setValue('slice_id', $realslice->getId());
							}
							elseif ($function == 'add') {
								$realslice = sly_Service_Factory::getService('Slice')->create(array('module' => $module));

								$newsql->setValue('slice_id', $realslice->getId());
								$newsql->setValue('re_article_slice_id', $slice_id);
								$newsql->setValue('article_id', $article_id);
								$newsql->setValue('module', $module);
								$newsql->setValue('clang', $clang);
								$newsql->setValue('ctype', $slot);
								$newsql->setValue('revision', $slice_revision);
							}

							// ****************** SPEICHERN FALLS NÖTIG
							foreach (sly_Core::getVarTypes() as $obj) {
								$obj->setACValues($realslice->getId(), $REX_ACTION, true, false);
							}

							if ($function == 'edit') {
								$newsql->addGlobalUpdateFields();

								if ($newsql->update()) {
									$info = $action_message.$I18N->msg('block_updated');
								}
								else {
									$warning = $action_message.$newsql->getError();
								}
							}
							elseif ($function == 'add') {
								$newsql->addGlobalUpdateFields();
								$newsql->addGlobalCreateFields();

								if ($newsql->insert()) {
									$last_id = $newsql->getLastId();
									$query   =
										'UPDATE #_article_slice '.
										'SET re_article_slice_id = '.$last_id.' '.
										'WHERE re_article_slice_id = '.$slice_id.' '.
										'AND id <> '.$last_id.' AND article_id = '.$article_id.' '.
										'AND clang = '.$clang.' AND revision = '.$slice_revision;

									if ($newsql->setQuery($query, '#_')) {
										$info     = $action_message.$I18N->msg('block_added');
										$slice_id = $last_id;
									}

									$function = '';
								}
								else {
									$warning = $action_message.$newsql->getError();
								}
							}

							$newsql = null;
						}
						else {
							// make delete

							if (rex_deleteSlice($slice_id)) {
								$global_info = $I18N->msg('block_deleted');
							}
							else {
								$global_warning = $I18N->msg('block_not_deleted');
							}
						}
						// ----- / SAVE SLICE

						// Artikel neu generieren

						$update = new rex_sql();
						$update->setTable('article', true);
						$update->setWhere('id = '.$article_id.' AND clang = '.$clang);
						$update->addGlobalUpdateFields();
						$update->update();
						$update = null;

						rex_deleteCacheArticleContent($article_id, $clang);
						rex_deleteCacheSliceContent($slice_id);

						// POST SAVE ACTION [ADD/EDIT/DELETE]

						$info .= rex_execPostSaveAction($module, $function, $REX_ACTION);

						// Update Button wurde gedrückt?

						if (rex_post('btn_save', 'string')) {
							$function = '';
						}
					}
				}
			}
		}

		// END: Slice add/edit/delete
		if($mode == 'meta'){
			// START: ARTICLE2STARTARTICLE

			if (rex_post('article2startpage', 'string')) {
				if ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('article2startpage[]')) {
					if (rex_article2startpage($article_id)) {
						$info = $I18N->msg('content_tostartarticle_ok');
						while (ob_get_level()) ob_end_clean();
						header('Location: index.php?page=content&mode=meta&clang='.$clang.'&ctype='.$slot.'&article_id='.$article_id.'&info='.urlencode($info));
						exit;
					}
					else {
						$warning = $I18N->msg('content_tostartarticle_failed');
					}
				}
			}

			// END: ARTICLE2STARTARTICLE
			// START: COPY LANG CONTENT

			if (rex_post('copycontent', 'string')) {
				if ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('copyContent[]')) {
					$clang_a = rex_post('clang_a', 'rex-clang-id');
					$clang_b = rex_post('clang_b', 'rex-clang-id');

					if (rex_copyContent($article_id, $article_id, $clang_a, $clang_b)) {
						$info = $I18N->msg('content_contentcopy');
					}
					else {
						$warning = $I18N->msg('content_errorcopy');
					}
				}
			}

			// END: COPY LANG CONTENT
			// START: MOVE ARTICLE

			if (rex_post('movearticle', 'string') && $category_id != $article_id) {
				$category_id_new = rex_post('category_id_new', 'rex-category-id');

				if ($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('moveArticle[]') && ($REX['USER']->hasPerm('csw[0]') || $REX['USER']->hasPerm('csw['.$category_id_new.']')))) {
					if (rex_moveArticle($article_id, $category_id, $category_id_new)) {
						$info = $I18N->msg('content_articlemoved');
						while (ob_get_level()) ob_end_clean();
						header('Location: index.php?page=content&article_id='.$article_id.'&mode=meta&clang='.$clang.'&ctype='.$slot.'&info='.urlencode($info));
						exit;
					}
					else {
						$warning = $I18N->msg('content_errormovearticle');
					}
				}
				else {
					$warning = $I18N->msg('no_rights_to_this_function');
				}
			}

			// END: MOVE ARTICLE
			// START: COPY ARTICLE

			if (rex_post('copyarticle', 'string')) {
				$category_copy_id_new = rex_post('category_copy_id_new', 'rex-category-id');

				if ($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('copyArticle[]') && ($REX['USER']->hasPerm('csw[0]') || $REX['USER']->hasPerm('csw['.$category_copy_id_new.']')))) {
					if (($new_id = rex_copyArticle($article_id, $category_copy_id_new)) !== false) {
						$info = $I18N->msg('content_articlecopied');
						while (ob_get_level()) ob_end_clean();
						header('Location: index.php?page=content&article_id='.$new_id.'&mode=meta&clang='.$clang.'&ctype='.$slot.'&info='.urlencode($info));
						exit;
					}
					else {
						$warning = $I18N->msg('content_errorcopyarticle');
					}
				}
				else {
					$warning = $I18N->msg('no_rights_to_this_function');
				}
			}

			// END: COPY ARTICLE
			// START: MOVE CATEGORY

			if (rex_post('movecategory', 'string')) {
				$category_id_new = rex_post('category_id_new', 'rex-category-id');

				if ($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('moveCategory[]') && (($REX['USER']->hasPerm('csw[0]') || $REX['USER']->hasPerm('csw['.$category_id.']')) && ($REX['USER']->hasPerm('csw[0]') || $REX['USER']->hasPerm('csw['.$category_id_new.']'))))) {
					if ($category_id != $category_id_new && rex_moveCategory($category_id, $category_id_new)) {
						$info = $I18N->msg('category_moved');
						while (ob_get_level()) ob_end_clean();
						header('Location: index.php?page=content&article_id='.$category_id.'&mode=meta&clang='.$clang.'&ctype='.$slot.'&info='.urlencode($info));
						exit;
					}
					else {
						$warning = $I18N->msg('content_error_movecategory');
					}
				}
				else {
					$warning = $I18N->msg('no_rights_to_this_function');
				}
			}

			// END: MOVE CATEGORY
			// START: SAVE METADATA

			if (rex_post('savemeta', 'string')) {
				$meta_article_name = rex_post('meta_article_name', 'string');

				$meta_sql = new rex_sql();
				$meta_sql->setTable('article', true);
				$meta_sql->setWhere('id = '.$article_id.' AND clang = '.$clang);
				$meta_sql->setValue('name', $meta_article_name);
				$meta_sql->addGlobalUpdateFields();

				if ($meta_sql->update()) {
					$article->setQuery('SELECT * FROM '.$REX['DATABASE']['TABLE_PREFIX'].'article WHERE id = '.$article_id.' AND clang = '.$clang);

					$info     = $I18N->msg('metadata_updated');
					$meta_sql = null;

					rex_deleteCacheArticle($article_id, $clang);
				}
				else {
					$meta_sql = null;
					$warning  = $meta_sql->getError();
				}
			}

			$info = rex_register_extension_point('ART_META_UPDATED', $info, array(
				'id'    => $article_id,
				'clang' => $clang,
			));

			// END: SAVE METADATA
		}
		// START: CONTENT HEAD MENUE

		$numSlots = count($REX['CTYPE']);
		$slotMenu = '';

		if ($numSlots > 0) {
			$listElements = array($I18N->msg($numSlots > 1 ? 'content_types' : 'content_type').' : ');

			foreach ($REX['CTYPE'] as $key => $val) {
				$s     = '';
				$class = '';

				if ($key == $slot && $mode == 'edit') {
					$class = ' class="rex-active"';
				}

				$val = rex_translate($val);
				$s  .= '<a href="index.php?page=content&amp;article_id='.$article_id.'&amp;clang='.$clang.'&amp;ctype='.$key.'&amp;mode=edit"'.$class.''.rex_tabindex().'>'.$val.'</a>';

				$listElements[] = $s;
			}

			$listElements = rex_register_extension_point('PAGE_CONTENT_CTYPE_MENU', $listElements, array(
				'article_id' => $article_id,
				'clang'      => $clang,
				'function'   => $function,
				'mode'       => $mode,
				'slice_id'   => $slice_id
			));

			$slotMenu  .= '<ul id="rex-navi-ctype">';

			foreach ($listElements as $idx => $listElement) {
				$class = '';

				if ($idx == 1) { // das erste Element ist nur Beschriftung -> überspringen
					$class = ' class="rex-navi-first"';
				}

				$slotMenu .= '<li'.$class.'>'.$listElement.'</li>';
			}

			$slotMenu .= '</ul>';
		}

		$menu         = $slotMenu;
		$listElements = array();
		$baseURL      = 'index.php?page=content&amp;article_id='.$article_id.'&amp;clang='.$clang.'&amp;ctype='.$slot;

		if ($mode == 'edit') {
			$listElements[] = '<a href="'.$baseURL.'&amp;mode=edit" class="rex-active"'.rex_tabindex().'>'.$I18N->msg('edit_mode').'</a>';
			$listElements[] = '<a href="'.$baseURL.'&amp;mode=meta"'.rex_tabindex().'>'.$I18N->msg('metadata').'</a>';
		}
		else {
			$listElements[] = '<a href="'.$baseURL.'&amp;mode=edit"'.rex_tabindex().'>'.$I18N->msg('edit_mode').'</a>';
			$listElements[] = '<a href="'.$baseURL.'&amp;mode=meta" class="rex-active"'.rex_tabindex().'>'.$I18N->msg('metadata').'</a>';
		}

		$listElements[] = '<a href="../'.$REX['FRONTEND_FILE'].'?article_id='.$article_id.'&amp;clang='.$clang.'" onclick="window.open(this.href); return false;" '.rex_tabindex().'>'.$I18N->msg('show').'</a>';

		$listElements = rex_register_extension_point('PAGE_CONTENT_MENU', $listElements, array(
			'article_id' => $article_id,
			'clang'      => $clang,
			'function'   => $function,
			'mode'       => $mode,
			'slice_id'   => $slice_id
		));

		$menu .= '<ul class="rex-navi-content">';

		foreach ($listElements as $idx => $element) {
			$class = $idx == 0 ? ' class="rex-navi-first"' : '';
			$menu .= '<li'.$class.'>'.$element.'</li>';
		}

		$menu .= '</ul>';

		// END: CONTENT HEAD MENUE
		// START: AUSGABE

		print '
<!-- *** OUTPUT OF ARTICLE-CONTENT - START *** -->
<div class="rex-content-header">
	<div class="rex-content-header-2">
		'.$menu.'
		<div class="rex-clearer"></div>
	</div>
</div>
		';

		// Meldungen

		if (!empty($global_warning)) print rex_warning($global_warning);
		if (!empty($global_info))    print rex_info($global_info);

		if ($mode != 'edit') {
			if (!empty($warning)) print rex_warning($warning);
			if (!empty($info))    print rex_info($info);
		}

		print '
<div class="rex-content-body">
	<div class="rex-content-body-2">
	';

		if ($mode == 'edit') {
			// START: Slice move up/down

			if ($function == 'moveup' || $function == 'movedown') {
				if ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('moveSlice[]')) {
					// Modul und Rechte vorhanden?

					$module = rex_slice_module_exists($slice_id, $clang);

					if ($module == -1) {
						// MODUL IST NICHT VORHANDEN
						$warning  = $I18N->msg('module_not_found');
						$slice_id = '';
						$function = '';
					}
					else {
						// RECHTE AM MODUL ?
						if ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('module['.$module.']') || $REX['USER']->hasPerm('module[0]')) {
							list($success, $message) = rex_moveSlice($slice_id, $clang, $function);

							if ($success) {
								$info = $message;
							}
							else {
								$warning = $message;
							}
						}
						else {
							$warning = $I18N->msg('no_rights_to_this_function');
						}
					}
				}
				else {
					$warning = $I18N->msg('no_rights_to_this_function');
				}
			}

			// END: Slice move up/down

			// START: MODULE EDITIEREN/ADDEN ETC.

			print '
		<!-- *** OUTPUT OF ARTICLE-CONTENT-EDIT-MODE - START *** -->
		<div class="rex-content-editmode">
			';
			$CONT = new rex_article();
			$CONT->getContentAsQuery();
			$CONT->info = $info;
			$CONT->warning = $warning;
			$CONT->template = $templateName;
			$CONT->setArticleId($article_id);
			$CONT->setSliceId($slice_id);
			$CONT->setMode($mode);
			$CONT->setCLang($clang);
			$CONT->setEval(true);
			$CONT->setSliceRevision($slice_revision);
			$CONT->setFunction($function);
			print $CONT->getArticle($slot);

			print '
		</div>
		<!-- *** OUTPUT OF ARTICLE-CONTENT-EDIT-MODE - END *** -->
	';
			// END: MODULE EDITIEREN/ADDEN ETC.
		}
		elseif ($mode == 'meta') {
			// START: META VIEW

			print '
		<div class="rex-form" id="rex-form-content-metamode">
			<form action="index.php" method="post" enctype="multipart/form-data" id="REX_FORM">
				<fieldset class="rex-form-col-1">
					<legend><span>'.$I18N->msg('general').'</span></legend>

					<input type="hidden" name="page" value="content" />
					<input type="hidden" name="article_id" value="'.$article_id.'" />
					<input type="hidden" name="mode" value="meta" />
					<input type="hidden" name="save" value="1" />
					<input type="hidden" name="clang" value="'.$clang.'" />
					<input type="hidden" name="ctype" value="'.$slot.'" />

					<div class="rex-form-wrapper">

						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-text">
								<label for="rex-form-meta-article-name">'.$I18N->msg('name_description').'</label>
								<input class="rex-form-text" type="text" id="rex-form-meta-article-name" name="meta_article_name" value="'.htmlspecialchars($article->getValue('name')).'" size="30"'.rex_tabindex().' />
							</p>
							<div class="rex-clearer"></div>
						</div>

						<div class="rex-clearer"></div>
						';

			print rex_register_extension_point('ART_META_FORM', '', array(
				'id'      => $article_id,
				'clang'   => $clang,
				'article' => $article
			));

			print '
						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-submit">
								<input class="rex-form-submit" type="submit" name="savemeta" value="'.$I18N->msg('update_metadata').'" />
							</p>
						</div>

						<div class="rex-clearer"></div>
					</div>
				</fieldset>';

			print rex_register_extension_point('ART_META_FORM_SECTION', '', array(
				'id'    => $article_id,
				'clang' => $clang
			));

			// SONSTIGES START

			if ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('article2startpage[]') || $REX['USER']->hasPerm('moveArticle[]') || $REX['USER']->hasPerm('copyArticle[]') || ($REX['USER']->hasPerm('copyContent[]') && count($REX['CLANG']) > 1)) {
				// ZUM STARTARTICLE MACHEN START

				if ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('article2startpage[]')) {
					print '
				<fieldset class="rex-form-col-1">
					<legend>'.$I18N->msg('content_startarticle').'</legend>
					<div class="rex-form-wrapper">

						<div class="rex-form-row">
							<p class="rex-form-col-a';

					if ($article->getValue('startpage') == 0 && $article->getValue('re_id') == 0) {
						print ' rex-form-read"><span class="rex-form-read">'.$I18N->msg('content_nottostartarticle').'</span>';
					}
					else if ($article->getValue('startpage')==1) {
						print ' rex-form-read"><span class="rex-form-read">'.$I18N->msg('content_isstartarticle').'</span>';
					}
					else {
						print ' rex-form-submit"><input class="rex-form-submit" type="submit" name="article2startpage" value="'.$I18N->msg('content_tostartarticle').'"'.rex_tabindex().' onclick="return confirm(\''.$I18N->msg('content_tostartarticle').'?\')" />';
					}

					print '
							</p>
						</div>
					</div>
				</fieldset>';
				}

				// ZUM STARTARTICLE MACHEN END
				// INHALTE KOPIEREN START

				if (($REX['USER']->isAdmin() || $REX['USER']->hasPerm('copyContent[]')) && count($REX['CLANG']) > 1) {
					$lang_a = new rex_select();
					$lang_a->setStyle('class="rex-form-select"');
					$lang_a->setId('clang_a');
					$lang_a->setName('clang_a');
					$lang_a->setSize('1');
					$lang_a->setAttribute('tabindex', rex_tabindex(false));

					foreach ($REX['CLANG'] as $key => $val) {
						$val = rex_translate($val);
						$lang_a->addOption($val, $key);
					}

					$lang_b = new rex_select();
					$lang_b->setStyle('class="rex-form-select"');
					$lang_b->setId('clang_b');
					$lang_b->setName('clang_b');
					$lang_b->setSize('1');
					$lang_b->setAttribute('tabindex', rex_tabindex(false));

					foreach ($REX['CLANG'] as $key => $val) {
						$val = rex_translate($val);
						$lang_b->addOption($val, $key);
					}

					$lang_a->setSelected(rex_request('clang_a', 'rex-clang-id', null));
					$lang_b->setSelected(rex_request('clang_b', 'rex-clang-id', null));

					print '
				<fieldset class="rex-form-col-2">
					<legend>'.$I18N->msg('content_submitcopycontent').'</legend>
					<div class="rex-form-wrapper">
						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-select">
								<label for="clang_a">'.$I18N->msg('content_contentoflang').'</label>
								'.$lang_a->get().'
							</p>
							<p class="rex-form-col-b rex-form-select">
								<label for="clang_b">'.$I18N->msg('content_to').'</label>
								'.$lang_b->get().'
							</p>
						</div>

						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-submit">
								<input class="rex-form-submit" type="submit" name="copycontent" value="'.$I18N->msg('content_submitcopycontent').'"'.rex_tabindex().' onclick="return confirm(\''.$I18N->msg('content_submitcopycontent').'?\')" />
							</p>
						</div>

						<div class="rex-clearer"></div>
					</div>
				</fieldset>';
				}

				// INHALTE KOPIEREN ENDE
				// ARTIKEL VERSCHIEBEN START

				if ($article->getValue('startpage') == 0 && ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('moveArticle[]'))) {
					// Wenn Artikel kein Startartikel dann Selectliste darstellen, sonst...
					$move_a = new rex_category_select();
					$move_a->setStyle('class="rex-form-select"');
					$move_a->setId('category_id_new');
					$move_a->setName('category_id_new');
					$move_a->setSize('1');
					$move_a->setAttribute('tabindex', rex_tabindex(false));
					$move_a->setSelected($category_id);

					print '
				<fieldset class="rex-form-col-1">
					<legend>'.$I18N->msg('content_submitmovearticle').'</legend>
					<div class="rex-form-wrapper">

						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-select">
								<label for="category_id_new">'.$I18N->msg('move_article').'</label>
								'.$move_a->get().'
							</p>
						</div>

						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-submit">
								<input class="rex-form-submit" type="submit" name="movearticle" value="'.$I18N->msg('content_submitmovearticle').'"'.rex_tabindex().' onclick="return confirm(\''.$I18N->msg('content_submitmovearticle').'?\')" />
							</p>
						</div>

						<div class="rex-clearer"></div>
					</div>
				</fieldset>';
				}

				// ARTIKEL VERSCHIEBEN ENDE
				// ARTIKEL KOPIEREN START

				if ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('copyArticle[]')) {
					$move_a = new rex_category_select();
					$move_a->setStyle('class="rex-form-select"');
					$move_a->setName('category_copy_id_new');
					$move_a->setId('category_copy_id_new');
					$move_a->setSize('1');
					$move_a->setSelected($category_id);
					$move_a->setAttribute('tabindex', rex_tabindex(false));

					print '
				<fieldset class="rex-form-col-1">
					<legend>'.$I18N->msg('content_submitcopyarticle').'</legend>
					<div class="rex-form-wrapper">

						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-select">
								<label for="category_copy_id_new">'.$I18N->msg('copy_article').'</label>
								'.$move_a->get().'
							</p>
						</div>

						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-submit">
								<input class="rex-form-submit" type="submit" name="copyarticle" value="'.$I18N->msg('content_submitcopyarticle').'"'.rex_tabindex().' onclick="return confirm(\''.$I18N->msg('content_submitcopyarticle').'?\')" />
							</p>
						</div>

						<div class="rex-clearer"></div>
					</div>
				</fieldset>';
				}

				// ARTIKEL KOPIEREN ENDE
				// KATEGORIE/STARTARTIKEL VERSCHIEBEN START

				if ($article->getValue('startpage') == 1 && ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('moveCategory[]'))) {
					$move_a = new rex_category_select();
					$move_a->setStyle('class="rex-form-select"');
					$move_a->setId('category_id_new');
					$move_a->setName('category_id_new');
					$move_a->setSize('1');
					$move_a->setSelected($article_id);
					$move_a->setAttribute('tabindex', rex_tabindex(false));

					print '
				<fieldset class="rex-form-col-1">
					<legend>'.$I18N->msg('content_submitmovecategory').'</legend>
					<div class="rex-form-wrapper">

						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-select">
								<label for="category_id_new">'.$I18N->msg('move_category').'</label>
								'.$move_a->get().'
							</p>
						</div>

						<div class="rex-form-row">
							<p class="rex-form-col-a rex-form-submit">
								<input class="rex-form-submit" type="submit" name="movecategory" value="'.$I18N->msg('content_submitmovecategory').'"'.rex_tabindex().' onclick="return confirm(\''.$I18N->msg('content_submitmovecategory').'?\')" />
							</p>
						</div>

						<div class="rex-clearer"></div>
					</div>
				</fieldset>';
				}
				// KATEGROIE/STARTARTIKEL VERSCHIEBEN ENDE
			}
			// SONSTIGES ENDE

			print '
			</form>
		</div>';

			// END: META VIEW
		}

		print '
	</div>
</div>
<!-- *** OUTPUT OF ARTICLE-CONTENT - END *** -->
';
		// END: AUSGABE
	}
}
