<?php
/**
 * Handle requests for css and js files that aren't in the webroot
 *
 * PHP versions 4 and 5
 *
 * Copyright (c) 2008, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2008, Andy Dawson
 * @link          www.ad7six.com
 * @package       mi_asset
 * @subpackage    mi_asset.controllers
 * @since         v 1.0
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * AssetController class
 *
 * @uses          MiAssetAppController
 * @package       mi_asset
 * @subpackage    mi_asset.controllers
 */
class AssetController extends MiAssetAppController {

/**
 * uses property
 *
 * @var array
 * @access public
 */
	var $uses = array();

/**
 * Don't merge anything if it's a public function
 *
 * Means no helpers auto loaded, and 0 components (No session either);
 *
 * @return void
 * @access protected
 */
	function __mergeVars() {
		if (Configure::read()) {
			$this->components = array(
				'Mi.MiSession'
			);
		} else {
			$this->components = false;
		}
		$this->helpers = false;
	}

/**
 * beforeFilter method
 *
 * Ensure public access
 *
 * @access public
 * @return void
 */
	function beforeFilter() {
		if (isset($this->Auth)) {
			$this->Auth->allow('serve');
		}
	}

/**
 * null method
 *
 * Requests routed to this method are logged and sent a 404
 * The 404 error message must not render the flash message - this method is intended to highlight missing images/css/js files
 *
 * @access public
 * @return void
 */
	function null() {
		$message = 'Request for: ' . Router::url('/' . $this->params['url']['url'], true) . ' from ' . $this->referer();
		if (preg_match('@.*\.(?:png|bmp|jpg|jpeg|gif)$@', $this->params['url']['url'])) {
			return $this->_missingImage($message);
		}
		if (Configure::read()) {
			$this->log($message);
		}
		header("HTTP/1.0 404 Not Found");
		$this->autoRender = false;
	}

/**
 * serve method
 *
 * Forward missing css/js requests to MiCompressor; if it isn't for a css or js file
 * bail out early and save some cycles.
 *
 * Don't render a view to prevent any helper/afterFilter logic from further manipulating
 * what MiCompressor has already done
 *
 * Clear the url cache, to regenerate the timestamp on the next request
 *
 * @return void
 * @access public
 */
	function serve() {
		$url = ltrim($this->params->url, '/');
		$message = 'Request for: ' . Router::url('/' . $this->params->url, true) . ' from ' . $this->referer();
		$this->autoRender = false;

		if (!preg_match('@/?(css|js)@', $url)) {
			$message = 'Invalid request. ' . $message;
			if (Configure::read()) {
				$this->Session->setFlash($message);
			}
			return trigger_error($message);
		}
		if (preg_match('@^/?js/theme@', $url) && $this->_linkTheme() && file_exists(WWW_ROOT . $url)) {
			$this->redirect('/' . ltrim($this->params['url']['url'], '/'));
		}

		if (preg_match('@.*\.(?:png|bmp|jpg|jpeg|gif)$@', $this->params->url)) {
			return $this->_missingImage($message);
		}

		if (App::import('Vendor', 'Mi.UrlCache')) {
			$inst = UrlCache::getInstance('/', $this->params);
			$inst->delete($_SERVER['REQUEST_URI']);
		}

		App::import('Vendor', 'MiAsset.MiCompressor');
		$bits = explode('.', $url);
		$type = array_pop($bits);
		if (preg_match('@^/?' . $type . '/(.*)\.' . $type .'@', $url, $matches)) {
			$request = $matches[1];
		} else {
			$request = preg_replace('@\.' . $type . '$@', '', $url);
		}
		$result = MiCompressor::serve($request, $type);
		if (!trim(preg_replace('@/\*.*\*\/@s', '', $result))) {
			if ($result) {
				$message = $result;
			}
			return $this->_missingAsset($message);
		}

		if (Configure::read() && strpos($result, 'PROBLEM:')) {
			preg_match('@^(/\*.*\*/)@s', $result, $matches);
			$this->Session->setFlash('<pre>' . $matches[1] . '</pre>');
		}
		if ($type === 'css') {
			header('Content-type: text/css');
		} else {
			header('Content-type: application/javascript');
		}
		echo $result;
		Configure::write('debug', 0);
	}

/**
 * linkTheme method
 *
 * Download a theme from jqueryui and put it in the webroot
 *
 * @TODO tie in with mi_panel so the theme colors are taken from the panel?
 * @return void
 * @access protected
 */
	function _linkTheme() {
		if (file_exists(TMP . 'theme.lock')) {
			while(file_exists(TMP . 'theme.lock')) {
				sleep(1);
			}
		}

		if (file_exists(WWW_ROOT . $this->params['url']['url'])) {
			return true;
		} elseif (file_exists(WWW_ROOT . 'js' . DS . 'theme' . DS . 'jquery.ui.theme.css')) {
			/*
			   The theme does exist - so it's likely a bad request
		    */
			touch(WWW_ROOT . $this->params['url']['url']);
			return false;
		}
		touch(TMP . 'theme.lock');
		$zipPath = TMP . 'theme' . DS . 'theme.zip';

		if (!is_dir(dirname($zipPath))) {
			$downloaded = true;
			$url = 'http://jqueryui.com/download';
			App::import('Core', 'HttpSocket');
			$HttpSocket = new HttpSocket();
			$out = $HttpSocket->post($url, array(
				'download' => true,
				'theme' => '?ffDefault=Verdana,Arial,sans-serif&' .
					'fwDefault=normal&' .
					'fsDefault=1.1em&' .
					'cornerRadius=4px&' .
					'bgColorHeader=cccccc&' .
					'bgTextureHeader=03_highlight_soft.png&' .
					'bgImgOpacityHeader=75&' .
					'borderColorHeader=aaaaaa&' .
					'fcHeader=222222&' .
					'iconColorHeader=222222&' .
					'bgColorContent=ffffff&' .
					'bgTextureContent=01_flat.png&' .
					'bgImgOpacityContent=75&' .
					'borderColorContent=aaaaaa&' .
					'fcContent=222222&' .
					'iconColorContent=222222&' .
					'bgColorDefault=e6e6e6&' .
					'bgTextureDefault=02_glass.png&' .
					'bgImgOpacityDefault=75&' .
					'borderColorDefault=d3d3d3&' .
					'fcDefault=555555&' .
					'iconColorDefault=888888&' .
					'bgColorHover=dadada&' .
					'bgTextureHover=02_glass.png&' .
					'bgImgOpacityHover=75&' .
					'borderColorHover=999999&' .
					'fcHover=212121&' .
					'iconColorHover=454545&' .
					'bgColorActive=ffffff&' .
					'bgTextureActive=02_glass.png&' .
					'bgImgOpacityActive=65&' .
					'borderColorActive=aaaaaa&' .
					'fcActive=212121&' .
					'iconColorActive=454545&' .
					'bgColorHighlight=fbf9ee&' .
					'bgTextureHighlight=02_glass.png&' .
					'bgImgOpacityHighlight=55&' .
					'borderColorHighlight=fcefa1&' .
					'fcHighlight=363636&' .
					'iconColorHighlight=2e83ff&' .
					'bgColorError=fef1ec&' .
					'bgTextureError=05_inset_soft.png&' .
					'bgImgOpacityError=95&' .
					'borderColorError=cd0a0a&' .
					'fcError=cd0a0a&' .
					'iconColorError=cd0a0a&' .
					'bgColorOverlay=aaaaaa&' .
					'bgTextureOverlay=01_flat.png&' .
					'bgImgOpacityOverlay=0&' .
					'opacityOverlay=30&' .
					'bgColorShadow=aaaaaa&' .
					'bgTextureShadow=01_flat.png&' .
					'bgImgOpacityShadow=0&' .
					'opacityShadow=30&' .
					'thicknessShadow=8px&' .
					'offsetTopShadow=-8px&' .
					'offsetLeftShadow=-8px&' .
					'cornerRadiusShadow=8px',
				'scope' => '',
				't-name' => 'custom-theme',
				'ui-version' => '1.8.5',
			));
			$aFolder = new Folder(dirname($zipPath), true);
			$zipFile = new File($zipPath);
			$zipFile->write($out);
			$this->_exec('cd ' . dirname($zipPath) . ' && unzip ' . $zipPath, $out);
		} else {
			$aFolder = new Folder(dirname($zipPath));
		}

		if (!is_dir(dirname($zipPath))) {
			unlink(TMP . 'theme.lock');
			return false;
		}
		$aFolder->copy(array(
			'from' => TMP . str_replace('/', DS , 'theme/development-bundle/themes/custom-theme'),
			'to' => WWW_ROOT . 'js' . DS . 'theme'
		));
		if (is_dir(WWW_ROOT . 'js' . DS . 'theme')) {
			if (file_exists(WWW_ROOT . 'js' . DS . 'theme' . DS . 'jquery-ui-1.7.2.custom.css')) {
				unlink(WWW_ROOT . 'js' . DS . 'theme' . DS . 'jquery-ui-1.7.2.custom.css');
			}
			if (!empty($downloaded)) {
				$this->_exec('rm -rf ' . $zipPath);
			}
			unlink(TMP . 'theme.lock');
			return true;
		}
		unlink(TMP . 'theme.lock');
		return false;
	}

/**
 * missingImage method
 *
 * @param mixed $message
 * @return void
 * @access protected
 */
	function _missingImage($message) {
		if (Configure::read()) {
			$this->Session->setFlash($message);
		}
		new File(WWW_ROOT . ltrim($this->params['url']['url'], '/'), true);

		$data['path'] = 'uploads' . DS;
		$data['id'] = 'missing.png';
		$data['extension'] = 'png';
		$data['name'] = 'Requested file was not found';
		$this->set($data);
		$this->view = 'Media';
		$this->render();
		Configure::write('debug', 0);
	}

/**
 * exec method
 *
 * @param mixed $cmd
 * @param mixed $out null
 * @return true on success, false on failure
 * @access protected
 */
	protected function _exec($cmd, &$out = null) {
		if (!class_exists('Mi')) {
			APP::import('Vendor', 'Mi.Mi');
		}
		return Mi::exec($cmd, $out);
	}

/**
 * missingAsset method
 *
 * @param mixed $message
 * @return void
 * @access protected
 */
	function _missingAsset($message) {
		if (Configure::read()) {
			$this->Session->setFlash($message);
		}
		if (Configure::read('MiAsset.store')) {
			$File = new File(WWW_ROOT . ltrim($this->params['url']['url'], '/'), true);
			if (Configure::read()) {
					if ($message[0] != '/') {
						$message = "/* $message */";
					}
					$File->write($message);
			}
		}
		$this->_stop();
	}
}
