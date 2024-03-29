<?php
/**
 * MiCompressor a class used for shrinking CSS and JS files
 *
 * MiCompressor is a utility which serves 2 purposes:
 *     Ask the class to return the request(s) necessary for a set of css/js files
 *     Process a request for css/js file(s)
 *
 * PHP version 5
 *
 * Copyright (c) 2008, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2009, Andy Dawson
 * @link          www.ad7six.com
 * @package       mi_asset
 * @subpackage    mi_asset.vendors
 * @since         v 1.0
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * MiCompressor class
 *
 * Compress and minify multiple css and js files into a single file on demand (or as part of a build process).
 *
 * By default in debug mode it only concatonates, in production mode contents are also runs through a
 * minifying routine. minify means stripping whitespace, rewriting to be less chars etc.
 *
 * Note:
 * 	To avoid stale js/css files being served, ensure you setup your server correctly! enable etags for example
 *
 * @abstract
 * @package       mi_asset
 * @subpackage    mi_asset.vendors
 */
abstract class MiCompressor {

/**
 * map property
 *
 * The main configuration for this class, defines where to look for files and what dependencies
 * each file has.
 *
 * All array keys are optional, the format for the array is:
 * 	type => array(
 * 		name => array(
 * 			'baseDir' =>
 * 			'pattern' =>
 * 			'dependencies' =>
 * 			'virtual' => '
 * 		)
 * 	)
 * 	type:         either js or css
 * 	name:         either the name of a file, or a partial regex pattern
 * 	baseDir:      the relative (to the vendors folder) path where the file(s) are located
 * 	pattern:      used in tandem with the name, and added to the baseDir to translate the request
 * 		to a valid vendor path
 * 	dependencies: defines what other requests are required (before) this one
 * 	virtual:      if this request doesn't have a file of its own - i.e. it's an alias/shortcut for
 * 		other files only
 *
 * 	Note that only css and js files are rewritten - if there's an image or other resource required
 * 	it won't be loaded/made available by MiCompressor
 *
 * @static
 * @var array
 * @access public
 */
	public static $map = array(
		'js' => array(
			'admin_default' => array(
				'dependencies' => array(
					'jquery.form',
					'i18n',
					'jquery.mi.ajax',
				)
			),
			'code' => array(
				'dependencies' => array(
					'jquery',
					'i18n',
				)
			),
			'default' => array(
				'dependencies' => array(
					'jquery.form',
					'i18n',
				)
			),
			'i18n' => array(
				'baseDir' => 'mi_js/',
			),
			'i18n\.*' => array(
				'baseDir' => 'mi_js/',
				'dependencies' => array(
					'i18n',
				),
				'pattern' => 'i18n.lang.js'
			),
			'jquery' => array(
				'baseDir' => 'jquery/dist/',
			),
			'jquery.mi' => array(
				'baseDir' => 'mi_js/',
				'dependencies' => array(
					'jquery',
					'i18n',
				)
			),
			'jquery.mi.ajax' => array(
				'baseDir' => 'mi_js/',
				'dependencies' => array(
					'jquery.mi',
					'jquery.blockUI',
				)
			),
			'jquery.mi.cloner' => array(
				'baseDir' => 'mi_js/',
			),
			'jquery.mi.dialogs' => array(
				'baseDir' => 'mi_js/',
				'dependencies' => array(
					'jquery.mi',
					'jquery.form',
					'jquery-ui',
					'jquery.blockUI',
				)
			),
			'jquery.mi.lookups' => array(
				'baseDir' => 'mi_js/',
				'dependencies' => array(
					'jquery.mi',
					'jquery.tokeninput',
					'lookups'
				)
			),
			'jquery\.mi\.*' => array(
				'baseDir' => 'mi_js/',
				'dependencies' => array(
					'jquery.mi',
				)
			),
			'jquery.blockUI' => array(
				'baseDir' => 'jquery-blockUI/',
				'dependencies' => array(
					'jquery',
				)
			),
			'jquery.form' => array(
				'baseDir' => 'jquery-form/',
				'dependencies' => array(
					'jquery',
				)
			),
			'lookup' => array(
				'dependencies' => array(
					'jquery.tokenInput',
				)
			),
		),
		'css' => array(
		)
	);

/**
 * defaultSettings property
 *
 * Don't edit this.
 * The settings which can be auto-set are set here, these settings are used as a fallback if a requested setting
 * hasn't been set, or the Configure class (if it exists) return null
 *
 * @var array
 * @access protected
 */
	protected static $defaultSettings = array(
		'debug' => null,
		'log' => null,
		'store' => null,
		'timestamp' => null,
		'concat' => null,
		'minify' => null,
		'minify.css' => null,
		'minify.js' => null,
		'minify.params' => null, //' --line-break 150',
		'host' => null,
		'cacheFile' => 'persistent/mi_compressor.php',
	);

/**
 * initialized property
 *
 * Flag to know if the class variables have been initialized yet.
 *
 * @var bool false
 * @access protected
 */
	protected static $initialized = false;

/**
 * loadedFiles property
 *
 * To prevent a valid request which includes the same file twice (either expicitly or through @import logic)
 * this variable holds the names of the files already loaded for the current request
 *
 * @var array
 * @access protected
 */
	protected static $loadedFiles = array();

/**
 * file map property
 *
 * This is the map of requeststring => array(process, these, requests)
 * Used to decypher concatenated filenames to what they contain
 *
 * @var array
 * @access protected
 */
	protected static $requestMap = null;

/**
 * requestStack property
 *
 * An internal stack used when processing requests to prevent duplicate requests and handle dependencies
 *
 * @var array
 * @access protected
 */
	protected static $requestStack = array();

/**
 * settings property
 *
 * Active settings - edit/set/see via self::config()
 *
 * @var array
 * @access protected
 */
	protected static $settings = array();

/**
 * start property
 *
 * Start time
 *
 * @static
 * @var mixed null
 * @access protected
 */
	protected static $start = null;

/**
 * vendorMap property
 *
 * Map of partial/path/to/something => /absolute/path
 *
 * @var mixed null
 * @access protected
 */
	protected static $vendorMap = null;

/**
 * NumberHelper property
 *
 * @var mixed null
 * @access private
 */
	private static $__NumberHelper = null;

/**
 * shell property
 *
 * When called on the command line, this stores a link to the shell so that log messages
 * can be output to show progress
 *
 * @var mixed null
 * @access private
 */
	private static $__Shell = null;

/**
 * c(onfigure)Read method
 *
 * Write default settings as appropriate on first read
 * Use the Configure class if it exists, otherwise use default setting
 * First none-null result encountered wins.
 *
 * Call with multiple paramters naming fallback settings e.g.
 * $debug = self::cRead('minify', 'minifyCss', 'Some.Coreapp.setting');
 *
 * @param string $setting 'debug'
 * @static
 * @return mixed, config value
 * @access public
 */
	public static function cRead($setting = 'debug') {
		if (!self::$initialized) {
			self::$initialized = true;

			$debug = self::cRead('debug');
			if ($debug === null) {
				$debug = Configure::read('debug');
			}
			self::$settings['debug'] = $debug;

			self::cRead('log', 'debug');

			$store = self::cRead('store');
			if ($store === null) {
				self::$settings['store'] = !$debug;
			}

			$minify = self::cRead('minify');
			if ($minify === null) {
				self::$settings['minify'] = !$debug;
			}
			$minify = self::cRead('minify.css', 'minify');
			$minify = self::cRead('minify.js', 'minify');

			if (file_exists(APP . 'Config' . DS . 'mi_asset.php')) {
				// while reading for the first time - allow loading a config file which can modify
				// static public vars
				require_once APP . 'Config' . DS . 'mi_asset.php';
			}
		}
		if (array_key_exists($setting, self::$settings)) {
			return self::$settings[$setting];
		}

		$return = Configure::read('MiCompressor.' . $setting);

		if ($return === null)  {
			$fallbacks = func_get_args();
			array_shift($fallbacks);
			if ($fallbacks) {
				$return = call_user_func_array(array('MiCompressor', 'cRead'), $fallbacks);
				return self::$settings[$setting] = $return;
			}
		}

		if (isset(self::$defaultSettings[$setting])) {
			$return = self::$defaultSettings[$setting];
		}
		return self::$settings[$setting] = $return;
	}

/**
 * config method
 *
 * Set/See settings
 *
 * @param array $settings array()
 * @param bool $reset false - reset to defaults before porcessing $settings?
 * @static
 * @return current settings
 * @access public
 */
	public static function config($settings = array(), $reset = false) {
		if ($reset) {
			self::$settings = array();
		}
		return self::$settings = array_merge(self::$settings, $settings);
	}

/**
 * filename method
 *
 * Return the filename to be used for the passed arg
 *
 * If it's a single request - use the argument name, if it's multiple use the middle of the
 * md5 hash (unlikely to clash with anything, and long enough to be unique)
 *
 * @param mixed $arg array()
 * @static
 * @return string the filename of the passed arg
 * @access protected
 */
	protected static function filename($arg = array()) {
		if (count($arg) === 1) {
			return $arg[0];
		}
		return substr(md5(implode($arg)), 10, 10);
	}

/**
 * listFiles method
 *
 * For the request string check the requestMap property and list which to include
 * If there's more than one file, or it's a concatenated request expand dependencies
 *
 * @param mixed $request
 * @param mixed $type
 * @static
 * @return the files for the request
 * @access public
 */
	static function listFiles($request, $type) {
		self::_populateRequestMap();
		self::_populateVendorMap(true);
		self::$requestStack = array();
		if (preg_match('@\.min$@', $request)) {
			self::$settings['minify.' . $type] = true;
			$request = preg_replace('@\.min$@', '', $request);
		} else {
			self::$settings['minify.' . $type] = false;
		}
		$fingerprint = self::_fingerprint();
		$request = str_replace($fingerprint, '', $request);
		$return = false;
		if (isset(self::$requestMap[$type][$request]['direct'])) {
			$return = self::$requestMap[$type][$request]['direct'];
			$expand = (count($return) > 1 || self::cRead('concat'));
			if ($expand) {
				$return = self::_flatten($return, $type, null, $expand);
			}
		}
		if (!$return) {
			$path = "$type/$request.$type";
			if (isset(self::$vendorMap[$path])) {
				return array($path);
			}
			foreach(self::$vendorMap as $key => $filename) {
				if (strpos($key, $path) && preg_match('@' . $path . '$@', $key)) {
					return array($path);
				}
			}
			return false;
		}
		return self::_flatten($return, $type, null, false);
	}

/**
 * loadRequest method
 *
 * For the requested file, find it and return it.
 *
 * For CSS files, check for @import declarations and auto-correct any url() references in the file
 *
 * @param string $request
 * @param bool $minify
 * @param string $type 'js'
 * @static
 * @return string the file's contents if appropriate
 * @access protected
 */
	protected static function loadRequest($request = '', $minify = false, $type = 'js') {
		self::$requestStack[] = $request;
		if ($type === 'js') {
			$base = JS;
		} elseif($type === 'css') {
			$base = CSS;
		} else {
			var_dump($base);  //@ignore
			die;
		}
		$request .= '.' . $type;
		$return = self::_includeFirst(array(
			WWW_ROOT . $type . DS . ltrim($request, '/'),
			WWW_ROOT . ltrim($request, '/'),
			ltrim($type . '/' . $request, '/'),
			ltrim($request, '/'),
		), $minify, $type);
		if (!$return) {
			if (!$minify) {
				self::log("PROBLEM: $request not found");
			}
			return;
		}
		$concat = self::cRead('concat');
		if ($type === 'css') {
			if (strpos($request, '/', 1)) {
				$baseFolder = self::$requestMap['urlPrefix'] . dirname($request) . '/';
			} else {
				$baseFolder = null;
			}
			if (rtrim($baseFolder, '/') === trim(self::$requestMap['urlPrefix'] . '/' . $type, '/')) {
				$baseFolder = null;
			}
			if (($baseFolder || $concat) && strpos($return, 'import')) {
				preg_match_all('/@import\s*(?:url\()?(?:["\'])([^"\']*)\.css(?:["\'])\)?;/', $return, $matches);
				if ($matches[1]) {
					foreach ($matches[1] as $i => $cssFile) {
						if ($concat) {
							$return = str_replace($matches[0][$i], '', $return);
							if ($cssFile[0] !== '/') {
								$cssFile = $baseFolder . $cssFile;
							}
							self::log("\t\t$cssFile dependency being loaded");
							$return .= self::loadRequest($cssFile, $minify, 'css');
						} elseif ($baseFolder !== dirname($cssFile) . '/') {
							$replace = str_replace($cssFile, $baseFolder . $cssFile, $matches[0][$i]);
							$return = str_replace($matches[0][$i], $replace, $return);
						}
					}
				}
			}
			if ($baseFolder && strpos($return, 'url')) {
				preg_match_all('@url\s*\((?:[\s"\']*)([^\s"\']*)(?:[\s"\']*)\)@', $return, $matches);
				$corrected = false;
				$urls = array_unique($matches[1]);
				foreach ($urls as $url) {
					if (strpos($url, $baseFolder) !== 0 && $url[0] !== '/') {
						$corrected = true;
						$return = str_replace($url, $baseFolder . $url, $return);
					}
				}
				if ($corrected) {
					self::log("\tAuto corrected url paths in $request prepending $baseFolder");
				}
			}
		}
		return $return;
	}

/**
 * log method
 *
 * Record to the log (the head doc block on first load in debug mode) or output the log (call with no params)
 *
 * @param mixed $string
 * @static
 * @return logs contents if requested, otherwise null
 * @access public
 */
	public static function log($string = null, $shellObject = null) {
		if (self::$start === null) {
			self::$start = microtime(true);
		}
		static $log = array();
		if ($shellObject) {
			self::$__Shell =& $shellObject;
		}
		if ($string === null) {
			$settings = self::$settings;
			ksort($settings);
			foreach ($settings as $k => &$v) {
				$v = ' ' . str_pad($k, 15, ' ', STR_PAD_RIGHT) . "\t: " . $v;
			}
			$settings[] = '';
			$head = array_merge(array(
				'MiCompressor log - (only generated on first load) ' . date("D, M jS Y, H:i:s"),
				null), $settings);
			$log = array_merge($head, $log);
			$return = "/**\r\n * " . implode("\r\n * ", $log) . "\r\n */\r\n";
			$log = array();
			self::$start = microtime(true);
			return $return;
		}
		if (strpos($string , 'PROBLEM') !== false && class_exists('Object')) {
			$Object = new Object();
			$Object->log($string, 'mi_compressor');
		}
		$time = microtime(true) - self::$start;
		$msg = str_pad(number_format($time, 3, '.', ''), 6, ' ', STR_PAD_LEFT) . 's ' . $string;
		if (!empty(self::$__Shell)) {
			self::$__Shell->out($msg);
		}
		$log[] = $msg;
	}

/**
 * minify method
 *
 * Use the yui compressor lib to compress files
 *
 * @TODO swtich to using http://code.google.com/intl/es/closure/compiler/ for js compression
 * @param string $string
 * @param mixed $filename null
 * @static
 * @return string the minified input $string
 * @access public
 */
	public static function minify($string, $filename = null) {
		if (self::$__NumberHelper === null) {
			App::import('Core', 'Helper');
			App::import('Helper', 'App');
			App::import('Helper', 'Number');
			self::$__NumberHelper = new NumberHelper();
		}
		if (!$string) {
			return;
		}
		$oLength = strlen($string);
		$lib = dirname(__FILE__) . DS . 'yuicompressor.jar';
		$file = basename($filename);
		self::log("	Minifying $file");
		if (file_exists($filename)) {
			$_filename = $filename;
		} else {
			$File = new File(TMP . DS . rand() . '.' . basename($filename), true);
			$File->write($string);
			$_filename = $File->pwd();
		}
		$minifyParams = self::cRead('minify.params');
		if ($minifyParams === true) {
			$minifyParams = '';
		}
		$cmd = "java -jar $lib $minifyParams " . $_filename;
		if (self::cRead('debug') > 1) {
			self::log("\t" . $cmd);
		}

		$return = self::_exec($cmd, $output);

		if (!$return) {
			self::log("PROBLEM: command failed: \$ $cmd");
			return $string;
		}
		$_cmd = str_replace($_filename, $filename, $cmd);
		$_cmd = str_replace(APP, '', $_cmd);
		$_cmd = str_replace(CAKE_CORE_INCLUDE_PATH, 'ROOT', $_cmd);
		self::log("	\$ $_cmd");
		if (!empty($File)) {
			$File->delete();
		}
		$return = implode($output, "\n");
		$fLength = strlen($return);
		$percent = round((1 - $fLength/$oLength) * 100);
		$oSize = self::$__NumberHelper->toReadableSize($oLength);
		$fSize = self::$__NumberHelper->toReadableSize($fLength);
		self::log("\tReduction: $percent% ($oSize to $fSize)");
		return $return;
	}

/**
 * process method
 *
 * For each of the requested files, find them, concatonate them - if requested minify them - and return
 * For js files, each individual file is minifyed. For css, their combined contents are minifyed
 * Called internally by serve, public to allow other (external) parse logic if necessary
 * As a last step it removes ^M characters (windoze) so all lines will be newline (only) terminated
 *
 * @param mixed $requests
 * @param mixed $type
 * @static
 * @return string the files contents, optionally minifyed, as a string
 * @access public
 */
	public static function process($requests, $type = null) {
		if ($type === null) {
			if (strpos($_GET['url'], 'js/') === 0) {
				$type = 'js';
			} else {
				$type = 'css';
			}
		}
		$minify = self::cRead('minify.' . $type, 'minify');

		if ($minify) {
			$min = '.min';
		} else {
			$min = '';
		}
		$return = '';
		foreach ((array)$requests as $request) {
			if (substr($request, - strlen($type)) === $type) {
				$request = substr($request, 0, - strlen($type) - 1);
			}
			self::log("$request.$type ...");
			$return .= self::loadRequest($request, $minify, $type) . "\r\n";
		}
		if (!$minify && $type === 'css') {
			preg_match_all('/@import.*;/', $return, $matches);
			$importStack = array();
			foreach ($matches[0] as $i => $cssFile) {
				$importStack[] = $cssFile;
				$return = str_replace($cssFile, '', $return);
			}
			$return = implode($importStack) . $return;
		}
		return str_replace(chr(13), '', $return);
	}

/**
 * serve. The main entry point for this class
 *
 * Check the filename map, and get the files to be processed.
 * Write the file to the webroot so that this script is one-time-only
 *
 * This script is intended to be used either in development; with the files generated added
 * to the project repository.
 *
 * The following locations need to be writable to the php user:
 * 	config/mi_compressor.php
 * 	webroot/css
 * 	webroot/js
 *
 * @param string $request
 * @param mixed $type
 * @static
 * @return contents to be served up
 * @access public
 */
	public static function serve($request = '', $type = null) {
		self::$loadedFiles = array();
		self::log('Request String: ' . $request);

		$start = microtime(true);

		if ($type === null) {
			$type = array_pop(explode('.', $_GET['url']));
		}
		$requests = self::listFiles($request, $type);
		if (!$requests) {
			return false;
		}
		$fingerprint = self::_fingerprint($request);
		$_request = str_replace($fingerprint, '', $request);
		$_request = preg_replace('@\.min$@', '', $_request);
		if (count($requests) > 1 && $request != $_request) {
			if ($_request[0] === '/') {
				$path = WWW_ROOT . $_request . '.' . $type;
			} else {
				$path = WWW_ROOT . $type . DS . $_request . '.' . $type;
			}
			if (file_exists($path)) {
				self::log("Uncompressed file exists ($path)");
				self::log("\tbypassing process method and using this file as input");
				$oString = file_get_contents($path);
				$return = self::minify($oString, $path);
			}
		}
		if (empty($return)) {
			self::$requestStack = array();
			$return = self::process($requests, $type);
			if (!isset(self::$requestMap[$type][$_request]['all'])) {
				self::$requestMap[$type][$_request]['all'] = self::$requestStack;
				self::_populateRequestMap(true);
			}
		}
		if (self::cRead('store')) {
			if ($request[0] === '/') {
				$path = WWW_ROOT . ltrim($request, '/') . '.' . $type;
			} else {
				$path = WWW_ROOT . $type . DS . $request . '.' . $type;
			}
			if (count($requests) > 1 && strpos($path, '.min.')) {
				$test = str_replace('.min.', '.', $path);
				if (file_exists($test)) {
					if (self::$__NumberHelper === null) {
						App::import('Core', 'Helper');
						App::import('Helper', 'App');
						App::import('Helper', 'Number');
						self::$__NumberHelper = new NumberHelper();
					}
					$oString = file_get_contents($test);
					$oLength = strlen($oString);
					$fLength = strlen($return);
					$percent = round((1 - $fLength/$oLength) * 100);
					$oSize = self::$__NumberHelper->toReadableSize($oLength);
					$fSize = self::$__NumberHelper->toReadableSize($fLength);
					self::log("Overall Reduction: $percent% ($oSize to $fSize)");
				}
			}
			$File = new File($path, true);
			if (!$File->writable()) {
				self::log("PROBLEM: Couldn't open $path for writing");
			} else {
				$File->delete();
				$bytes = strlen($return);
				self::log("Writing $path {$bytes} bytes");
				$File->write($return);
			}
		}
		if (self::cRead('debug')) {
			$return = self::log() . $return;
		}
		return $return;
	}

/**
 * storePath method
 *
 * Get the path to the webroot where the file is (or will shortly be created);
 *
 * @param mixed $type
 * @param mixed $filename
 * @param bool $relative return a relative or absolute path
 * @static
 * @return string the absolute path to the target file
 * @access protected
 */
	protected static function storePath($type, $filename) {
		$min = self::cRead('minify.' . $type);
		if ($min) {
			$min = '.min';
		} else {
			$min = '';
		}
		return WWW_ROOT . $type . DS . $filename . $min . '.' . $type;
	}

/**
 * url method
 *
 * Generate the url(s) corresponding to the requested files
 *
 * @param array $request
 * @param array $params
 * @static
 * @return mixed string or array of strings correpsonding to the request
 * @access public
 */
	public static function url($request = array(), $params = array()) {
		extract(am(array(
			'type' => 'js',
			'sizeLimit' => false,
		), $params));
		$concat = self::cRead('concat');
		if (!$concat && !self::$requestMap) {
			self::_populateRequestMap();
		}
		$stack = self::_flattenRequest($request, $type, !$concat);
		$return = array();
		foreach ($stack as $files) {
			if ($concat) {
				$url = self::_url($files, $type, $sizeLimit);
				$return = array_merge($return, (array)$url);
			} elseif ($files) {
				foreach ($files as $file) {
					$return[] = self::_url($file, $type, $sizeLimit);
				}
				$filename = self::filename($files);
				if (empty(self::$requestMap[$filename])) {
					self::$requestMap[$type][$filename]['direct'] = $files;
					self::_populateRequestMap(true);
				}
			}
		}
		if (count($return) === 1) {
			return $return[0];
		}
		return $return;
	}

/**
 * addToStack method
 *
 * @param string $request ''
 * @param string $type 'js'
 * @param string $package 'default'
 * @param bool $expandDependencies false
 * @static
 * @return void
 * @access protected
 */
	protected static function _addToStack($request = '', $type = 'js', $package = 'default', $expandDependencies = false) {
		if (!empty(self::$requestStack[$type]['*']) && in_array($request, self::$requestStack[$type]['*'])) {
			return;
		}
		self::$requestStack[$type]['*'][] = $request;
		if ($expandDependencies === true) {
			if (isset(self::$map[$type][$request])) {
				$params = am(array('baseDir' => null, 'pattern' => null, 'dependencies' => null, 'virtual' => null),
					self::$map[$type][$request]);
				self::_handleDependency($request, $type, $package);
			} else {
				$match = false;
				foreach(self::$map[$type] as $regex => $params) {
					if (preg_match('@^' . $regex . '$@', $request)) {
						$match = true;
						self::_handleDependency($request, $type, $package, $regex);
						break;
					}
				}
				if (!$match) {
					unset($params);
				}
			}
			if (!empty($params['virtual'])) {
				return;
			}
		}
		self::$requestStack[$type][$package][] = $request;
	}

/**
 * exec method
 *
 * @param mixed $cmd
 * @param mixed $out null
 * @return void
 * @access protected
 */
	protected function _exec($cmd, &$out = null) {
		if (!class_exists('Mi')) {
			APP::import('Vendor', 'Mi.Mi');
		}
		return Mi::exec($cmd, $out);
	}

/**
 * flatten method
 *
 * If it's a nested array, flatten it
 *
 * @param array $requests
 * @param string $type 'js'
 * @param string $package 'default'
 * @param bool $expandDependencies false
 * @static
 * @return array
 * @access protected
 */
	protected static function _flatten($requests, $type = 'js', $package = 'default', $expandDependencies = false) {
		foreach ((array)$requests as $key => $value) {
			if (is_string($key)) {
				self::_addToStack($key, $type, $package, $expandDependencies);
				if ($value) {
					foreach($value as $i => $file) {
						self::_addToStack("$key.$file", $type, $package, $expandDependencies);
					}
				}
			} else {
				self::_addToStack($value, $type, $package, $expandDependencies);
			}
		}
		if (empty(self::$requestStack[$type][$package])) {
			return false;
		}
		return self::$requestStack[$type][$package];
	}

/**
 * flattenRequest method
 *
 * For each request - flatten it and exclude any (cross stack) duplicates
 *
 * @param array $request array()
 * @param string $type 'js'
 * @param bool $expandDependencies false
 * @static
 * @return array
 * @access protected
 */
	protected static function _flattenRequest($request = array(), $type = 'js', $expandDependencies = false) {
		self::$requestStack[$type] = array();
		if ($expandDependencies) {
			self::_populateRequestMap();
		}
		foreach ($request as $package => &$files) {
			$files = self::_flatten($files, $type, $package, $expandDependencies);
			if (!$files) {
				unset($request[$package]);
				continue;
			}
			$filename = self::filename($files);
			if (isset(self::$requestMap[$type][$filename]['all'])) {
				self::$requestStack[$type]['*'] = array_unique(array_merge(
					self::$requestStack[$type]['*'], self::$requestMap[$type][$filename]['all']
				));
			}
		}
		return $request;
	}

/**
 * handleDependency method
 *
 * @param mixed $request
 * @param mixed $type
 * @param string $package 'default'
 * @param mixed $key null
 * @static
 * @return void
 * @access protected
 */
	protected static function _handleDependency($request, $type, $package = 'default', $key = null) {
		if ($key === null) {
			$key = $request;
		}
		if(empty(self::$map[$type][$key]['dependencies'])) {
			return;
		}
		$params = am(array('baseDir' => null, 'pattern' => null, 'dependencies' => null, 'virtual' => null),
			self::$map[$type][$key]);
		foreach($params['dependencies'] as $i => $dependency) {
			if ($dependency === $request) {
				continue;
			}
			if (!strpos($dependency, '*')) {
				self::_addToStack($dependency, $type, $package, true);
				continue;
			}
			$dKey = str_replace(array('.', '*'), array('\.', '(.*)'), $dependency);
			if (isset(self::$map[$type][$dKey])) {
				$dParams = am(array('baseDir' => null, 'pattern' => null, 'dependencies' => null, 'virtual' => null),
					self::$map[$type][$dKey]);
				if ($dParams['pattern']) {
					$segment = $dParams['baseDir'] . str_replace(array('\1'), array('(.*)'), $dParams['pattern']);
				} else {
					$segment = $dParams['baseDir'] . $dKey . '\.' . $type;
				}
				$replace = str_replace(array('*'), array('\1'), $dependency);
				$regex = '@^' . $segment . '$@';
				foreach(self::$vendorMap as $k => $filename) {
					if (preg_match($regex, $k)) {
						$_dependency = preg_replace($regex, $replace, $k);
						self::_addToStack($_dependency, $type, $package, false);
					}
				}
			} else {
				$replace = str_replace(array('*'), array('\1'), $dependency);
				$segment = $params['baseDir'] . str_replace(array('.', '*'), array('\.', '([^/]*)'), $dependency) . '\.' . $type;
				$regex = '@^' . $segment . '$@';
				foreach(self::$vendorMap as $k => $filename) {
					if (preg_match($regex, $k)) {
						$_dependency = preg_replace($regex, $replace, $k);
						self::_addToStack($_dependency, $type, $package, false);
					}
				}
			}
		}
	}

/**
 * includeFile method
 *
 * Check if the file has already been included, if not if it's in the webroot load with
 * file_get_contents, otherwise include
 *
 * @param string $file absolute file path
 * @static
 * @return string the file's contents
 * @access protected
 */
	protected static function _includeFile($file) {
		if (in_array($file, self::$loadedFiles)) {
			self::log("\t... Skipping Duplicate");
			return;
		}
		$return = ''; //"\r/*$file*/\r";
		self::$loadedFiles[] = $file;
		if (strpos($file, WWW_ROOT)) {
			$return = file_get_contents($file);
		} else {
			ob_start();
			include($file);
			$return = $return . ob_get_clean();
		}
		$bytes = strlen($return);
		self::log("\tAdding $file ($bytes bytes)");
		return $return;
	}

/**
 * includeFirst method
 *
 * For the list of possibilities (absolute file paths or relative vendor paths) include the first
 * one found.
 *
 * Use the map to include dependencies if appropriate
 *
 * @param mixed $possibilities
 * @param bool $minify false
 * @static
 * @return string the file's contents
 * @access protected
 */
	protected static function _includeFirst($possibilities, $minify = false, $type = null) {
		if ($minify) {
			$minPossibilities = $possibilities;
			foreach($minPossibilities as &$path) {
				$path = preg_replace('@\.([^\.]*)$@', '.min.$1', $path);
			}
			$return = self::_includeFirst($minPossibilities, false, $type);
			if ($return) {
				return $return;
			}
		}
		$return = false;
		foreach($possibilities as $i => $path) {
			if (self::cRead('debug') > 1) {
				self::log(' 	Looking for ' . $path);
			}
			if ($path[0] == '/') {
				if (file_exists($path)) {
					$return = self::_includeFile($path);
					break;
				}
				continue;
			} elseif (isset(self::$vendorMap[$path])) {
				$filename = self::$vendorMap[$path];
				$return = self::_includeFile($filename);
				break;
			}
		}
		if (!$return) {
			preg_match('@(\.min)?\.([^\.]*)$@', $path, $matches);
			$file = $request = preg_replace('@(\.min)?(\.[^\.]*)$@', '', $path);
			$file .= '.' . $type;
			$min = $matches[1];
			preg_match('@\.([^\.]*)$@', $path, $matches);

			if (isset(self::$map[$type][$request])) {
				$params = am(array('baseDir' => null, 'pattern' => null, 'dependencies' => null, 'virtual' => null),
					self::$map[$type][$request]);
				if (isset($params['virtual']) && !$params['virtual']) {
					$return = '';
					break;
				}
				$vendorKey = $params['baseDir'] . $file;
				if ($min) {
					$vendorKey = preg_replace('@\.([^\.]*)$@', '.min.$1', $vendorKey);
				}
				if (isset(self::$vendorMap[$vendorKey])) {
					$filename = self::$vendorMap[$vendorKey];
					$return = self::_includeFile($filename);
				}
			} else {
				foreach(self::$map[$type] as $key => $params) {
					$regex = '@^' . str_replace(array('*'), array('[^/]*'), $key) . '$@';
					if (!preg_match($regex, $request)) {
						continue;
					}
					$params = am(
						array('baseDir' => null, 'pattern' => null, 'dependencies' => null, 'virtual' => null),
						$params
					);
					if (!empty($params['virtual'])) {
						$return = '';
						break;
					}
					if (!empty($params['pattern'])) {
						$vendorKey = preg_replace($regex, $params['baseDir'] . $params['pattern'], $file);
					} else {
						$vendorKey = $params['baseDir'] . $file;
					}
					if ($min) {
						$vendorKey = preg_replace('@\.([^\.]*)$@', '.min.$1', $vendorKey);
					}
					if (isset(self::$vendorMap[$vendorKey])) {
						$filename = self::$vendorMap[$vendorKey];
						$return = self::_includeFile($filename);
						break;
					}
				}
			}
		}
		if (empty($return)) {
			$matches = array();
			foreach(self::$vendorMap as $key => $filename) {
				if (strpos($key, $path) && preg_match('@' . $path . '$@', $key)) {
					$matches[strlen($key) . $key] = $filename;
				}
			}
			ksort($matches);
			foreach($matches as $filename) {
				$return = self::_includeFile($filename);
				if ($return) {
					break;
				}
			}
		}

		if (!$return && $minify) {
			if ($return === false) {
				self::log("PROBLEM: No file found for $path");
				return false;
			}
			return;
		}
		if ($minify) {
			return self::minify($return, basename($path));
		}
		return $return;
	}

/**
 * populateRequestMap method
 *
 * @param bool $write false
 * @param bool $load null
 * @return void
 * @access protected
 */
	protected static function _populateRequestMap($write = false, $load = null) {
		$cacheFile = CACHE . self::cRead('cacheFile');
		if (empty(self::$requestMap) || $load) {
			if (file_exists($cacheFile)) {
				include($cacheFile);
				self::$requestMap = $config['requestMap'];
			} else {
				self::$requestMap = array();
			}
		}
		if (empty(self::$requestMap['urlPrefix'])) {
			if (class_exists('Router')) {
				self::$requestMap['urlPrefix'] = Router::url('/');
				if (self::$requestMap['urlPrefix'] === '/') {
					self::$requestMap['urlPrefix'] = '';
				}
			} else {
				self::$requestMap['urlPrefix'] = '';
			}
		}

		if (!$write) {
			return;
		}
		if (file_exists($cacheFile)) {
			$merge = true;
		}
		$fp = fopen($cacheFile, 'a+');
		if (!flock($fp, LOCK_EX | LOCK_NB)) {
			return;
		}
		if (!empty($merge)) {
			include($cacheFile);
			if (!empty($config)) {
				$merged = $config['requestMap'];
				foreach(self::$requestMap as $type => $sets) {
					if (is_array($sets)) {
						foreach($sets as $set => $values) {
							$merged[$type][$set] = $values;
						}
					} else {
						$merged[$type] = $sets;
					}
				}
				self::$requestMap = $merged;
			}
		}

		self::log('Updating config file');
		foreach(self::$requestMap as $type => &$stuff) {
			if (is_array($stuff)) {
				ksort($stuff);
			}
		}
		ksort(self::$requestMap);
		$string = "<?php\n\$config['requestMap'] = " . var_export(self::$requestMap, true) . ';';
		ftruncate($fp, 0);
		fwrite($fp, $string);
		fclose($fp);
		/* Trust in flock
		   $return = self::_exec('php -l ' . escapeshellarg($cacheFile), $_);
		if ($return !== 0) {
			trigger_error('self::_populateRequestMap the written config file contains a parse error and has been deleted');
			unlink($cacheFile);
		}
		*/
	}

/**
 * populateVendorMap method
 *
 * @param bool $reset false
 * @static
 * @return void
 * @access protected
 */
	protected static function _populateVendorMap($reset = false) {
		if ($reset || !self::$vendorMap) {
			App::import('Vendor', 'Mi.MiCache');
			$plugins = MiCache::mi('plugins');
			foreach($plugins as $path => $plugin) {
				if (is_dir($path . DS . 'webroot')) {
					if (!class_exists('Mi')) {
						APP::import('Vendor', 'Mi.Mi');
					}
					$files = Mi::files($path . DS . 'webroot', null, '.*\.(css|js)$');
					foreach($files as $fPath) {
						$fPath = realpath($fPath);
						self::$vendorMap[str_replace(realpath($path . DS . 'webroot') . DS, $plugin . DS, $fPath)] = $fPath;
					}
				}
			}

			self::log("Populating Vendor Map");
			if (!class_exists('MiCache')) {
				self::log("\tMiCache doesn't exist. Skipping.");
				return;
			}
			self::$vendorMap = am(self::$vendorMap, MiCache::mi('vendors',
				null,
				array('shells'),
				array(
					'excludeFolders' => array('shells', 'simpletest'),
					'extension' => array('css', 'js'),
					'excludePattern' => false
				)
			));
			self::log("\tDone.");
		}
	}

/**
 * url method
 *
 * @param mixed $files
 * @param string $type
 * @param bool $sizeLimit
 * @static
 * @return mixed the url or urls corresponding to the requested files
 * @access protected
 */
	protected static function _url($files, $type = 'js', $sizeLimit = false) {
		$filename = self::filename((array)$files);
		if (!isset(self::$requestMap[$type][$filename]['direct'])) {
			self::$requestMap[$type][$filename]['direct'] = $files;
			self::_populateRequestMap(true);
		}
		if ($sizeLimit && count($files) > 1) {
			$storePath = self::storePath($type, $filename);
			if (file_exists($storePath) && filesize($storePath) > ($sizeLimit - 45)) {
				foreach($files as $file) {
					$return[] = self::_url(array($file), $type);
				}
				return $return;
			}
		}
		$min = self::cRead('minify.' . $type);
		if ($min) {
			$min = '.min';
		} else {
			$min = '';
		}
		if (count($files) > 1 || $min) {
			$fingerprint = self::_fingerprint($files);
		} else {
			$fingerprint = '';
		}
		if (substr($filename, 0, 4) === 'http') {
				return $filename;
		} elseif ($filename[0] === '/') {
			$return = "{$filename}{$min}.$type";
		} else {
			$return = "/$type/$filename{$fingerprint}{$min}.$type";
		}
		if (Configure::read() && file_exists(WWW_ROOT . $return) ) {
			$return .= '?v=' . filemtime(WWW_ROOT . $return);
		}
		return $return;
	}

/**
 * fingerprint method
 *
 * Although it accepts a url paramter it isn't at this time used. Use the filemtime of the bootstrap to generate the
 * fingerprint if MiCompressor.fingerprint isn't already defined. This allows asset files to be cached permently on the
 * client (browser) side because any change the the source file will generate a new url, and therefore pull the updated
 * file
 *
 * @param mixed $url
 * @param bool $reset false
 * @return void
 * @access protected
 */
	protected function _fingerprint($url = null, $reset = false) {
		static $return = null;
		if ($return && !$reset) {
			$return;
		}
		$fingerprint = Configure::read('MiCompressor.fingerprint');
		if ($fingerprint) {
			return '.' . $fingerprint;
		}
		return '.' . filemtime(APP . 'Config' . DS . 'bootstrap.php');
	}
}
