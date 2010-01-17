<?php
/**
 * Short description for asset.php
 *
 * Long description for asset.php
 *
 * PHP version 4 and 5
 *
 * Copyright (c) 2009, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright (c) 2008, Andy Dawson
 * @link          www.ad7six.com
 * @package       mi_asset
 * @subpackage    mi_asset.views.helpers
 * @since         v 1.0 (06-Sep-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::import('Vendor', 'MiAsset.MiCompressor');

/**
 * AssetHelper class
 *
 * @uses          AppHelper
 * @package       mi_asset
 * @subpackage    mi_asset.views.helpers
 */
class AssetHelper extends AppHelper {

/**
 * name property
 *
 * @var string 'Asset'
 * @access public
 */
	var $name = 'Asset';

/**
 * helpers property
 *
 * @var array
 * @access public
 */
	var $helpers = array(
		'Html',
		'Javascript'
	);

/**
 * cssStack property
 *
 * @var array
 * @access private
 */
	var $__cssStack = array();

/**
 * cssViewStack property
 *
 * @var array
 * @access private
 */
	var $__cssViewStack = array();

/**
 * stack property
 *
 * @var array
 * @access private
 */
	var $__jsStack = array('default' => array());

/**
 * viewStack property
 *
 * @var array
 * @access private
 */
	var $__jsViewStack = array();

/**
 * scripts property
 *
 * @var string
 * @access private
 */
	var $__scripts = null;

/**
 * beforeLayout method
 *
 * Shuffle the vars so css added in view files are after css added in thelayout
 *
 * @return void
 * @access public
 */
	function beforeLayout() {
		$this->__cssViewStack = $this->__cssStack;
		$this->__cssStack = array();
		$this->__jsViewStack = $this->__jsStack;
		$this->__jsStack = array('default' => array());
	}

/**
 * Returns a JavaScript script tag.
 *
 * @param string $script The JavaScript to be wrapped in SCRIPT tags.
 * @param array $options Set of options:
 * - allowCache: boolean, designates whether this block is cacheable using the
 * current cache settings.
 * - safe: boolean, whether this block should be wrapped in CDATA tags.  Defaults
 * to helper's object configuration.
 * - inline: whether the block should be printed inline, or written
 * to cached for later output (i.e. $scripts_for_layout).
 *
 * @return null
 */
	function codeBlock($script = null, $options = array()) {
		$options['inline'] = true;
		$this->__scripts .= $this->Javascript->codeBlock($script, $options);
	}

/**
 * css method
 *
 * Example usage, from anywhere at all:
 * 	$asset->css('this');
 * 	....
 * 	$asset->css(array('that', 'and', 'the'));
 * 	...
 * 	$asset->css('other');
 *
 * @param mixed $path
 * @param mixed $rel
 * @param array $htmlAttributes
 * @param string $package
 * @return void
 * @access public
 */
	function css($path = null, $rel = null, $htmlAttributes = array(), $package = 'default') {
		if (is_array($path)) {
			foreach ($path as $url) {
				$this->css($url, $rel, $htmlAttributes, false, $package);
			}
			return;
		}
		if (!$rel) {
			$rel = 'stylesheet';
		}
		if (!$htmlAttributes) {
			$htmlAttributes = array ('title' => 'Standard', 'media' => 'screen');
		}
		$key = Inflector::slug($rel . serialize($htmlAttributes));
		$this->__cssStack[$key]['rel'] = $rel;
		$this->__cssStack[$key]['htmlAttributes'] = $htmlAttributes;
		if (empty($this->__cssStack[$key]['files']) || !in_array($path, $this->__cssStack[$key]['files'])) {
			$this->__cssStack[$key]['files'][$package][] = $path;
		}
	}

/**
 * js method
 *
 * Example usage, from anywhere at all:
 * 	$asset->js('this', false);
 * 	....
 * 	$asset->js('that', false);
 * 	...
 * 	$asset->js(array('jquery' => 'plugin1'), false);
 * 	...
 * 	$asset->js(array('jquery' => array('plugin2', 'plugin3')), false);
 *
 * In the layout (preferably right at the end), call with no parameters to output:
 * 	echo $asset->out('js');
 *
 * With the given example it would generate a link to /app/js/somehash.js?123 to be picked up
 * by the mi_compressor vendor class. Note that jquery and plugins will always be first if included
 *
*
 * @param mixed $url null
 * @param string $package 'default'
 * @return void
 * @access public
 */
	function js($url = null, $package = 'default') {
		if (!isset($this->__jsStack[$package])) {
			$this->__jsStack[$package] = array();
		}
		if (is_array($url)) {
			foreach ($url as $key => $value) {
				if (is_numeric($key)) {
					if (!in_array($value, $this->__jsStack[$package])) {
						$this->__jsStack[$package][] = $value;
					}
					continue;
				}
				if (!isset($this->__jsStack[$package][$key])) {
					$this->__jsStack[$package][$key] = (array)$value;
				} else {
					$this->__jsStack[$package][$key] = array_unique(array_merge($this->__jsStack[$package][$key], (array)$value));
				}
			}
			return;
		}
		if (!in_array($url, $this->__jsStack[$package])) {
			$this->__jsStack[$package][] = $url;
		}
	}

/**
 * out method
 *
 * If $sizeLimit is true, files are auto-restricted to 25K for mobile devices, else the value
 * passed is used as the limit. Defaults to false (not filesize limiting)
 *
 * @param mixed $type null
 * @param bool $sizeLimit false
 * @return void
 * @access public
 */
	function out($type = null, $sizeLimit = false) {
		$method = '_print' . ucfirst($type);
		return $this->$method($sizeLimit);
	}

/**
 * printCss method
 *
 * @param mixed $sizeLimit null
 * @return void
 * @access protected
 */
	function _printCss($sizeLimit = null) {
		foreach($this->__cssViewStack as $package => $files) {
			if (isset($this->__cssStack[$package])) {
				$this->__cssStack[$package] = Set::merge($this->__cssStack[$package], $this->__cssViewStack[$package]);
			} else {
				$this->__cssStack[$package] = $this->__cssViewStack[$package];
			}
		}
		$this->__cssViewStack = array();
		if (!$this->__cssStack) {
			return;
		}
		if (!isset($this->__RequestHandler)) {
			$this->__RequestHandler = new RequestHandlerComponent();
		}
		if ($sizeLimit === true) {
			if (!isset($this->__RequestHandler)) {
				App::import('Component', 'RequestHandler');
				$this->__RequestHandler = new RequestHandlerComponent();
			}
			if ($this->__RequestHandler->isMobile()) {
				$sizeLimit = 25 * 1024;
			}
		}
		$return = array();
		foreach ($this->__cssStack as $array) {
			$urls = (array)MiCompressor::url(array_values($array['files']), array('type' => 'css', 'sizeLimit' => $sizeLimit));
			extract($array);
			foreach($urls as $url) {
				$url = str_replace($this->webroot, '/', $this->url($url));
				$return[] = $this->Html->css($url, $rel, $htmlAttributes, true);
			}
		}
		$this->__cssStack = array();
		return "\r" . implode("\r", $return) . "\r";
	}

/**
 * printJs method
 *
 * @param mixed $sizeLimit null
 * @return void
 * @access protected
 */
	function _printJs($sizeLimit = null) {
		foreach($this->__jsViewStack as $package => $files) {
			if (isset($this->__jsStack[$package])) {
				$this->__jsStack[$package] = Set::merge($this->__jsStack[$package], $this->__jsViewStack[$package]);
			} else {
				$this->__jsStack[$package] = $this->__jsViewStack[$package];
			}
		}
		$this->__jsViewStack = array();
		if (!$this->__jsStack) {
			$return = $this->__scripts;
			$this->__scripts = '';
			return $return;
		}
		if ($sizeLimit === true) {
			if (!isset($this->__RequestHandler)) {
				App::import('Component', 'RequestHandler');
				$this->__RequestHandler = new RequestHandlerComponent();
			}
			if ($this->__RequestHandler->isMobile()) {
				$sizeLimit = 25 * 1024;
			}
		}
		if (isset($this->__jsStack['jquery'])) {
			$this->__jsStack = array_merge(array('jquery' => $this->__jsStack['jquery']), $this->__jsStack);
		}
		$urls = MiCompressor::url($this->__jsStack, array('type' => 'js', 'sizeLimit' => $sizeLimit));
		$this->__jsStack = array('default' => array());
		$return = array();
		foreach((array)$urls as $url) {
			$url = str_replace($this->webroot, '/', $this->url($url));
			$return[] = $this->Javascript->link($url);
		}
		$return[] = $this->__scripts;
		$this->__scripts = '';
		return "\r" . implode("\r", $return) . "\r";
	}
}