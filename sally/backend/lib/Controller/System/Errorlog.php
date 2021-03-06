<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_System_Errorlog extends sly_Controller_System {
	protected function init() {
		parent::init();

		$this->handler = sly_Core::getErrorHandler();

		if (get_class($this->handler) !== 'sly_ErrorHandler_Production') {
			print sly_Helper_Message::warn(t('cant_work_with_foreign_errorhandler', get_class($this->handler)));
			$this->handler = null;
		}
	}

	public function indexAction() {
		$this->init();

		if ($this->handler === null) return;

		// check log existence

		$log     = $this->handler->getLog();
		$logfile = $log->getFilename();

		if (!file_exists($logfile) || filesize($logfile) === 0) {
			print sly_Helper_Message::info(t('errorlog_is_empty'));
			return;
		}

		// get last N lines

		$max = sly_get('max', 'int', 50);

		if ($max <= 0) {
			$max = 50;
		}
		elseif ($max > 5000) {
			$max = 5000;
		}

		$lines     = $this->tail($logfile, $max);
		$lineCount = $this->getNumberOfLines($logfile);

		if (empty($lines)) {
			print sly_Helper_Message::info(t('errorlog_is_empty'));
			return;
		}

		// render data

		$regex = '#^\[(.*?)\] PHP (.+?) \((.+?)\): (.+) in (.+?) line (\d+) \[(((?:GET|POST|HEAD) (?:.+?))|(?:php:(.+?)))\]$#';
		$lines = array_reverse($lines); // put most recent line on the top
		$data  = array();

		foreach ($lines as $line) {
			if (!preg_match($regex, $line, $matches)) continue;

			$data[] = array(
				'date'    => strtotime($matches[1]),
				'type'    => $matches[2],
				'code'    => $matches[3],
				'message' => $matches[4],
				'file'    => $matches[5],
				'line'    => (int) $matches[6],
				'request' => $matches[7]
			);
		}

		$this->render('system/errorlog.phtml', compact('data', 'lineCount', 'max'), false);
	}

	public function clearAction() {
		$this->init();

		$log     = $this->handler->getLog();
		$logfile = $log->getFilename();

		if (unlink($logfile)) {
			print sly_Helper_Message::info(t('errorlog_cleared'));
		}
		else {
			print sly_Helper_Message::warn(t('errorlog_not_cleared'));
		}

		return $this->indexAction();
	}

	protected function tail($filename, $lines = 10) {
		$fp = @fopen($filename, 'r');
		if (!$fp) return false;

		$lines = abs($lines);

		if ($lines < 1) {
			$lines = 1;
		}

		$data      = array();
		$linesRead = 0;

		while (!feof($fp)) {
			$data[] = trim(fgets($fp, 4096));
			++$linesRead;

			if ($linesRead > $lines+50) {
				$data = array_slice($data, -$lines);
			}
		}

		fclose($fp);

		array_pop($data); // letzte, leere Zeile wegwerfen
		return array_slice($data, -$lines);
	}

	protected function getNumberOfLines($filename) {
		$fp = @fopen($filename, 'r');
		if (!$fp) return 0;

		$lines = 0;

		while (fgets($fp, 4096)) {
			++$lines;
		}

		fclose($fp);
		return $lines;
	}
}
