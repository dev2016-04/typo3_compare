<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Stefan Froemken (kennziffer.com) <froemken@kennziffer.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Plugin 'Faceted search - searchbox and filters' for the 'ke_search' extension.
 *
 * @author	Stefan Froemken (kennziffer.com) <froemken@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class tx_kesearch_lib_searchresult {

	protected $conf = array();
	protected $row = array();

	/**
	 * @var tx_kesearch_lib
	 */
	protected $pObj;

	/**
	 * @var tslib_cObj
	 */
	protected $cObj;

	/**
	 * @var tx_kesearch_lib_div
	 */
	protected $div;





	/**
	 * The constructor of this class
	 *
	 * @param tx_kesearch_lib $pObj
	 */
	public function __construct(tx_kesearch_lib $pObj) {
		// initializes this object
		$this->init($pObj);
	}


	/**
	 * Initializes this object
	 *
	 * @param tx_kesearch_lib $pObj
	 * @return void
	 */
	public function init(tx_kesearch_lib $pObj) {
		$this->pObj = $pObj;
		$this->cObj = $this->pObj->cObj;
		$this->conf = $this->pObj->conf;
	}


	/**
	 * set row array with current result element
	 *
	 * @param array $row
	 * @return void
	 */
	public function setRow(array $row) {
		$this->row = $row;
	}


	/**
	 * get title for result row
	 *
	 * @return string The linked result title
	 */
	public function getTitle() {
		// configure the link
		$linkconf = $this->getResultLinkConfiguration();

		// clean title
		$linktext = $this->row['title'];
		$linktext = strip_tags($linktext);
		$linktext = $this->pObj->div->removeXSS($linktext);
		$linktext = $linktext;

		// highlight hits in result title?
		if($this->conf['highlightSword'] && count($this->pObj->swords)) {
			$linktext = $this->highlightArrayOfWordsInContent($this->pObj->swords, $linktext);
		}
		return $this->cObj->typoLink($linktext, $linkconf);
	}


	/**
	 * get result url (not linked)
	 *
	 * @return string The results URL
	 */
	public function getResultUrl($linked = FALSE) {
		$linkText = $this->cObj->typoLink_URL($this->getResultLinkConfiguration());
		$linkText = htmlspecialchars($linkText);
		$resultUrl = $this->cObj->typoLink($linkText, $this->getResultLinkConfiguration());
		if($linked) {
			return $this->cObj->typoLink($linkText, $this->getResultLinkConfiguration());
		} return $linkText;
	}


	/**
	 * get result link configuration
	 * It can devide between the result types (file, page, content)
	 *
	 * @return array configuration for typolink
	 */
	public function getResultLinkConfiguration() {
		$linkconf = array();

		switch($this->row['type']) {
			case 'file': // render a link for files
				$relPath = str_replace(PATH_site, '', $this->row['directory']);
				$linkconf['parameter'] = $relPath . rawurlencode($this->row['title']);
				$linkconf['fileTarget'] = $this->conf['resultLinkTarget'];
				break;
			default: // render a link for page targets
				// if params are filled, add them to the link generation process
				if (!empty($this->row['params'])) {
					$additionalParams = $this->row['params'];
				}
				$linkconf['additionalParams'] = $additionalParams;
				$linkconf['parameter'] = $this->row['targetpid'];
				$linkconf['useCacheHash'] = true;
				$linkconf['target'] = $this->conf['resultLinkTarget'];
				break;
		}
		return $linkconf;
	}


	/**
	 * get teaser for result list
	 *
	 * @return string The teaser
	 */
	public function getTeaser() {
		$content = $this->getContentForTeaser();
		return $this->buildTeaserContent($content);
	}


	/**
	 * get content for teaser
	 * This can be the abstract or content col
	 *
	 * @return string The content
	 */
	public function getContentForTeaser() {
		$content = $this->row['content'];
		if (!empty($this->row['abstract'])) {
			$content = nl2br($this->row['abstract']);
			if ($this->conf['previewMode'] == 'hit') {
				if (!$this->isArrayOfWordsInString($this->pObj->swords, $this->row['abstract'])) {
					$content = $this->row['content'];
				}
			}
		}
		return $content;
	}


	/**
	 * check if an array with words was found in given content
	 *
	 * @param array $wordArray A single dimmed Array containing words to search for. F.E. array('hello', 'georg', 'company')
	 * @param string $content The string to search in
	 * @param boolean $checkAll If this is checked, then all words have to be found in string. If false: The method returns true directly, if one of the words was found
	 * @return boolean Returns true if the word(s) are found
	 */
	public function isArrayOfWordsInString(array $wordArray, $content, $checkAll = FALSE) {
		$found = FALSE;
		foreach($wordArray as $word) {
			if(stripos($content, $word) === FALSE) {
				$found = FALSE;
				if($checkAll === TRUE) return FALSE;
			} else {
				$found = TRUE;
				if($checkAll === FALSE) return TRUE;
			}
		}
		return $found;
	}


	/**
	 * Find and highlight the searchwords
	 *
	 * @param array $wordArray
	 * @param string $content
	 * @return string The content with highlighted searchwords
	 */
	public function highlightArrayOfWordsInContent($wordArray, $content) {
		if(is_array($wordArray) && count($wordArray)) {
			foreach($wordArray as $word) {
				$word = str_replace('/', '\/', $word);
				$content = preg_replace('/(' . $word . ')/iu','<span class="hit">\0</span>', $content);
			}
		}
		return $content;
	}


	/**
	 * Build Teasercontent
	 *
	 * @param string $content The whole resultcontent
	 * @return string The cutted recultcontent
	 */
	public function buildTeaserContent($content) {
		if(is_array($this->pObj->swords) && count($this->pObj->swords)) {
			$amountOfSearchWords = count($this->pObj->swords);
			$content = strip_tags($content);
			// with each new searchword and all the croppings here the teaser for each word will become too small/short
			// I decided to add 20 additional letters for each searchword. It looks much better and is more readable
			$charsForEachSearchWord = ceil($this->conf['resultChars'] / $amountOfSearchWords) + 20;
			$charsBeforeAfterSearchWord = ceil($charsForEachSearchWord / 2);
			$aSearchWordWasFound = FALSE;
			$isSearchWordAtTheBeginning = FALSE;
			foreach($this->pObj->swords as $word) {
				$word = ' ' . $word; // our searchengine searches for wordbeginnings
				$pos = stripos($content, $word);
				if($pos === FALSE) {
					// if the word was not found it could be within brakets => (searchWord)
					// so give it a second try
					$pos = stripos($content, trim($word));
					if($pos === FALSE) {
						continue;
					}
				}
				$aSearchWordWasFound = TRUE;

				// if searchword is the first word
				if($pos === 0) {
					$isSearchWordAtTheBeginning = TRUE;
				}

				// find search starting point
				$startPos = $pos - $charsBeforeAfterSearchWord;
				if($startPos < 0) $startPos = 0;

				// crop some words behind searchword
				$partWithSearchWord = substr($content, $startPos);
				$temp = $this->cObj->crop($partWithSearchWord, $charsForEachSearchWord . '|...|1');

				// crop some words before searchword
				// after last cropping our text is too short now. So we have to find a new cutting position
				($startPos > 10)? $length = strlen($temp) - 10: $length = strlen($temp);
				$teaserArray[] = $this->cObj->crop($temp, '-' . $length . '||1');
			}

			// When the searchword was found in title but not in content the teaser is empty
			// in that case we have to get the first x letters without containing any searchword
			if($aSearchWordWasFound === FALSE) {
				$teaser = $this->cObj->crop($content, $this->conf['resultChars'] . '||1');
			} elseif($isSearchWordAtTheBeginning === TRUE) {
				$teaser = implode(' ', $teaserArray);
			} else {
				$teaser = '...' . implode(' ', $teaserArray);
			}

			// highlight hits?
			if ($this->conf['highlightSword']) {
				$teaser = $this->highlightArrayOfWordsInContent($this->pObj->swords, $teaser);
			}
			return $teaser;
		} else return $this->cObj->crop($content, $this->conf['resultChars'] . '|...|1');
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ke_search/lib/class.tx_kesearch_lib_searchresult.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ke_search/lib/class.tx_kesearch_lib_searchresult.php']);
}
?>
