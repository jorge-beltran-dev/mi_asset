<?php


/**
 * Short description for build.php
 *
 * Long description for build.php
 *
 * PHP versions 4 and 5
 *
 * Copyright (c) 2009, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2009, Andy Dawson
 * @link          www.ad7six.com
 * @package       mi_asset
 * @subpackage    mi_asset.vendors.shells
 * @since         v 1.0 (20-May-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::import('MiAsset.MiCompressor');

/**
 * BuildShell class
 *
 * @uses          Shell
 * @package       mi_asset
 * @subpackage    mi_asset.vendors.shells
 */
class BuildShell extends Shell {

/**
 * initialize method
 *
 * @return void
 * @access public
 */
	function initialize() {
		return true;
	}

/**
 * reset method
 *
 * @return void
 * @access public
 */
	function reset() {
		$file = MiCompressor::cRead('configFile');
		unlink(APP . $file);
		$this->out('mi_compressor file deleted, run "cake clear css js" to delete existing files if desired');
	}

/**
 * assets method
 *
 * @return void
 * @access public
 */
	function assets() {
		$this->css();
		$this->js();
	}

/**
 * css method
 *
 * @return void
 * @access public
 */
	function css() {
		$this->_compress();
	}

/**
 * js method
 *
 * @return void
 * @access public
 */
	function js() {
		$this->_jsPo();
		$this->_compress('js');
	}

/**
 * compress method
 *
 * @param string $type 'css'
 * @return void
 * @access protected
 */
	function _compress($type = 'css') {
		Configure::write('MiCompressor.store', true);
		$file = MiCompressor::cRead('configFile');
		if (!file_exists($file)) {
			$this->out($file . ' file not found');
			$this->out(' ... skipping');
			return;
		}
		$this->out($file . ' found');

		$config = array();
		include(APP . $file);
		if (empty($config['requestMap'][$type])) {
			$this->out(' ... nothing to do');
			return;
		}
		include_once(APP . $file);
		$allRequests = array();
		$multiRequests = array();
		$prefix = Configure::read('MiCompressor.prefix');
		foreach ($config['requestMap'][$type] as $request => $_) {
			$allRequests[] = $request;
		}
		$allRequests = am(array_unique($allRequests), $multiRequests);
		sort($allRequests);
		MiCompressor::log(null, $this);
		$this->hr();
		$this->out(' *** Normal Assetts ***');
		foreach($allRequests as $request) {
			$this->hr();
			MiCompressor::serve($request, $type);
			MiCompressor::log();
		}
		$this->hr();
		$this->out(' *** Minified Assetts ***');
		foreach($allRequests as $request) {
			$this->hr();
			MiCompressor::serve($request . '.min', $type);
			MiCompressor::log();
		}
	}

/**
 * jsPo method
 *
 * Parse js files for use of the (javascript) function __d('mi', ) and generate a javascript 'po' file.
 * will create app/vendors/i18n.<locale>.js files for each locale that exists in the app/locale dir
 *
 * @return void
 * @access protected
 */
	function _jsPo() {
		$Folder = new Folder(APP . 'locale');
		list($locales, $potFiles) = $Folder->read();
		if (!in_array('javascript.pot', $potFiles)) {
			$this->out(__d('mi', 'The javascript.pot file wasn\'t found - run cake mi_i18n to generate it', true));
			return;
		}
		if (defined('DEFAULT_LANGUAGE')) {
			$locales = array_unique(am(array(DEFAULT_LANGUAGE), $locales));
		}
		if (!class_exists('I18n')) {
			App::import('Core', 'i18n');
		}

		$messages = array();
		foreach ($locales as $locale) {
			if ($locale[0] === '.') {
				continue;
			}
			$data = Cache::read('javascript_' . $locale, '_cake_core_');
			if (!$data) {
				Configure::write('Config.language', $locale);
				__d('javascript', 'foo', true);
				$inst =& I18n::getInstance();
				Cache::write('javascript_' . $locale, array_filter($inst->__domains), '_cake_core_');
				$data = Cache::read('javascript_' . $locale, '_cake_core_');
				if (!$data) {
					continue;
				}
			}
			foreach($data as $type => $i) {
				foreach ($i[$locale]['javascript'] as $lookup => $string) {
					if (!is_string($string)) {
						continue;
					}
					if (!$string) {
						$string = $lookup;
					}
					$messages[$lookup] = $string;
				}
			}
			ob_start();
			include(dirname(__FILE__) . DS . 'templates' . DS . 'js' . DS . 'po.js');
			$contents = ob_get_clean();
			$targetFile = APP . 'vendors' . DS . 'js' . DS . 'i18n.' . $locale . '.js';
			$File = new File($targetFile);
			$File->write($contents);
			$this->out(Debugger::trimPath($targetFile) . ' written');
		}
	}
}