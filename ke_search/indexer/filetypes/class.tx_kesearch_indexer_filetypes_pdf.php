<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Stefan Froemken (kennziffer.com) <froemken@kennziffer.com>
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

require_once(t3lib_extMgm::extPath('ke_search').'indexer/types/class.tx_kesearch_indexer_types_file.php');

/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 *
 * @author	Stefan Froemken (kennziffer.com) <froemken@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kesearch
 */
class tx_kesearch_indexer_filetypes_pdf extends tx_kesearch_indexer_types_file implements tx_kesearch_indexer_filetypes {
	var $extConf = array(); // saves the configuration of extension ke_search_hooks
	var $app = array(); // saves the path to the executables
	var $isAppArraySet = false;


	/**
	 * class constructor
	 */
	public function __construct() {
		// get extension configuration of ke_search_hooks
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['ke_search']);

		//
		if($this->extConf['pathPdftotext'])	{
			$pathPdftotext = rtrim($this->extConf['pathPdftotext'], '/') . '/';
			$pathPdfinfo = rtrim($this->extConf['pathPdfinfo'], '/') . '/';
			$safeModeEnabled = t3lib_utility_PhpOptions::isSafeModeEnabled();
			$exe = (TYPO3_OS == 'WIN') ? '.exe' : '';
			if($safeModeEnabled || (@is_file($pathPdftotext . 'pdftotext' . $exe) && @is_file($pathPdfinfo . 'pdfinfo' . $exe))) {
				$this->app['pdfinfo'] = $pathPdfinfo . 'pdfinfo' . $exe;
				$this->app['pdftotext'] = $pathPdftotext . 'pdftotext' . $exe;
                                $this->app['tika'] = 'java -jar /var/www/html/4flow/typo3conf/ext/ke_search/res/tika-app-1.4.jar';
				$this->isAppArraySet = true;
			} else $this->isAppArraySet = false;
		} else $this->isAppArraySet = false;
		if(!$this->isAppArraySet) t3lib_utility_Debug::debug('The path for the pdftools is not correctly set in extConf. You can get the path with "which pdfinfo" or "which pdftotext".');
	}


	/**
	 * get Content of PDF file
	 *
	 * @param string $file
	 * @return string The extracted content of the file
	 */
	public function getContent($file) {
		$this->fileInfo = t3lib_div::makeInstance('tx_kesearch_lib_fileinfo');
		$this->fileInfo->setFile($file);
		// get PDF informations
		if(!$pdfInfo = $this->getPdfInfo($file)) return false;

		// proceed only of there are any pages found
		if(intval($pdfInfo['pages']) && $this->isAppArraySet) {
			// create the tempfile which will contain the content
			$tempFileName = t3lib_div::tempnam('news_files-Indexer');
			@unlink ($tempFileName); // Delete if exists, just to be safe.

			// generate and execute the pdftotext commandline tool
			//

                        //$cmd = $this->app['tika']. ' --text ' . escapeshellarg($file) . ' | tee ' . $tempFileName;
                        $cmd = $this->app['pdftotext'] . ' -enc UTF-8 -f 1 -l 5 -q ' . escapeshellarg($file) . ' ' . $tempFileName;
			t3lib_utility_Command::exec($cmd);

			// check if the tempFile was successfully created
			if(@is_file($tempFileName)) {
				$content = t3lib_div::getUrl($tempFileName);
				unlink($tempFileName);
			} else return false;

			// check if content was found
			if(strlen($content)) {
				return $this->removeEndJunk($content);
			} else return false;
		} else return false;
	}


	/**
	 * execute commandline tool pdfinfo to extract pdf informations from file
	 *
	 * @param string $file
	 * @return array The pdf informations as array
	 */
	public function getPdfInfo($file) {
		if($this->fileInfo->getIsFile()) {
			if($this->fileInfo->getExtension() == 'pdf' && $this->isAppArraySet) {
				$cmd = $this->app['pdfinfo'] . ' ' . escapeshellarg($file);
				t3lib_utility_Command::exec($cmd, $pdfInfoArray);
				$pdfInfo = $this->splitPdfInfo($pdfInfoArray);
				unset($pdfInfoArray);
				return $pdfInfo;
			} else return false;
		} else return false;
	}


	/**
	 * Analysing PDF info into a useable format.
	 *
	 * @param array Array of PDF content, coming from the pdfinfo tool
	 * @return array The pdf informations as array in a useable format
	 */
	function splitPdfInfo($pdfInfoArray) {
		$res = array();
		if(is_array($pdfInfoArray)) {
			foreach($pdfInfoArray as $line) {
				$parts = explode(':', $line, 2);
				if(count($parts) > 1 && trim($parts[0])) {
					$res[strtolower(trim($parts[0]))] = trim($parts[1]);
				}
			}
		}
		return $res;
	}


	/**
	 * Removes some strange char(12) characters and line breaks that then to occur in the end of the string from external files.
	 *
	 * @param string String to clean up
	 * @return string Cleaned up string
	 */
	function removeEndJunk($string)	{
		return trim(preg_replace('/['.LF.chr(12).']*$/', '', $string));
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ke_search/indexer/filetypes/class.tx_kesearch_indexer_filetypes_pdf.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/ke_search/indexer/filetypes/class.tx_kesearch_indexer_filetypes_pdf.php']);
}
?>