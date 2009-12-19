<?php

/**
* Object Oriented Framework: Bildet einen Artikel der Struktur ab
* @package redaxo4
* @version svn:$Id$
*/

class OOArticle extends OORedaxo {
	
	private function OOArticle($params = FALSE, $clang = FALSE) {
		parent :: OORedaxo($params, $clang);
	}

	/**
	* CLASS Function:
	* Return an OORedaxo object based on an id
	*/
	public function getArticleById($article_id, $clang = FALSE, $OOCategory = FALSE) {
		
		global $REX;
		
		$article_id = (int) $article_id;
		
		if (!is_int($article_id))
		return NULL;
		
		if ($clang === FALSE)
		$clang = $REX['CUR_CLANG'];
		
		$obj = rex_register_extension_point('OOREDAXO_GET', null, array(
			'id' => $article_id,
			'clang' => $clang,
			'oocategory' => $OOCategory
		));
		
		if (!$obj) {
			$article = rex_sql::getInstance();
			$article->setQuery("SELECT * FROM " . $REX['TABLE_PREFIX'] . "article WHERE id='$article_id' AND clang='$clang'");
			if($article->rows > 0) {
				if ($OOCategory) $obj = new OOCategory(mysql_fetch_array($article->result, MYSQL_ASSOC));
				else $obj = new OOArticle(mysql_fetch_array($article->result, MYSQL_ASSOC));
				rex_register_extension_point('OOREDAXO_CREATED', $obj);
			}
			$article->freeResult();
		}
		
		return $obj;
	}

	/**
	* CLASS Function:
	* Return the site wide start article
	*/
	public function getSiteStartArticle($clang = FALSE) {
		global $REX;
		
		if ($clang === FALSE)
		$clang = $REX['CUR_CLANG'];
		
		return OOArticle :: getArticleById($REX['START_ARTICLE_ID'], $clang);
	}

	/**
	* CLASS Function:
	* Return start article for a certain category
	*/
	public function getCategoryStartArticle($a_category_id, $clang = FALSE) {
		global $REX;
		
		if ($clang === FALSE) $clang = $REX['CUR_CLANG'];
		
		return OOArticle :: getArticleById($a_category_id, $clang);
	}

	/**
	* CLASS Function:
	* Return a list of articles for a certain category
	*/
	public function getArticlesOfCategory($a_category_id, $ignore_offlines = FALSE, $clang = FALSE) {
		global $REX;

		if ($clang === FALSE)
		$clang = $REX['CUR_CLANG'];

		$alist = rex_register_extension_point('ALIST_GET', null, array(
			'category_id' => $a_category_id,
			'clang' => $clang
		));
		
		if($alist === null){
			$alist = array ($a_category_id);
			
			$sql = rex_sql::getInstance();
			$sql->setQuery("SELECT id FROM " . $REX['TABLE_PREFIX'] . "article WHERE re_id='$a_category_id' AND clang='$clang' ORDER BY prior,name");
			while ($row = mysql_fetch_array($sql->result, MYSQL_NUM)) {
				$alist[] = $row[0];  
			}
			$sql->freeResult();
			
			rex_register_extension_point('ALIST_CREATED', $alist, array(
				'category_id' => $a_category_id,
				'clang' => $clang
			));
		}

		$artlist = array ();
			
		foreach ($alist as $var) {
			
			$article = OOArticle :: getArticleById($var, $clang);
			
			if (!$ignore_offlines || ($ignore_offlines && $article->isOnline())) {
				$artlist[] = $article;
			}
		}
		
		return $artlist;
	}

	/**
	* CLASS Function:
	* Return a list of top-level articles
	*/
	public function getRootArticles($ignore_offlines = FALSE, $clang = FALSE) {
		return OOArticle :: getArticlesOfCategory(0, $ignore_offlines, $clang);
	}

	/**
	* Accessor Method:
	* returns the category id
	*/
	public function getCategoryId() {
		return $this->isStartPage() ? $this->getId() : $this->getParentId();
	}

	/*
	* Object Function:
	* Returns the parent category
	*/
	public function getCategory() {
		return OOCategory :: getCategoryById($this->getCategoryId(),$this->getClang());
	}

	/*
	* Static Method: Returns boolean if article exists with requested id
	*/
	public static function exists($articleId) {
		
		global $REX;
		
		//TODO
		//return (id exists in MetaCache);
		
		// pr�fen, ob ID in Content Cache Dateien vorhanden
		$cacheFiles = scandir($REX['INCLUDE_PATH'].DIRECTORY_SEPARATOR.'generated'.DIRECTORY_SEPARATOR.'articles');
		foreach ($cacheFiles as $cf) {
			if (strLen($cf) > 2 && ($pos = strPos($cf, '.', 1)) !== FALSE
				&& subStr($cf, 0, $pos) == $articleId) return TRUE;
		}
		
		// pr�fen, ob ID in DB vorhanden
		return self::isValid(self::getArticleById($articleId));
	}
	
	/*
	* Static Method: Returns boolean if is article
	*/
	public static function isValid($article) {
		return is_object($article) && is_a($article, 'ooarticle');
	}
	
	public function getValue($value) {
		// alias f�r re_id -> category_id
		if(in_array($value, array('re_id', '_re_id', 'category_id', '_category_id'))) {
			// f�r die CatId hier den Getter verwenden,
			// da dort je nach ArtikelTyp unterscheidungen getroffen werden m�ssen
			return $this->getCategoryId();
		}
		return parent::getValue($value);
	}

	public function hasValue($value) {
		return parent::hasValue($value, array('art_'));
	}
}