<?php


// Configuration

// Set to false to disable site caching for all new requests.
// USE SPARINGLY as doing this on high volume site could cause a database crash
define('CACHE_ENABLED', true);
//****************************

define('CACHE_DEBUG', true);
define('CACHE_BASE_DIR', 'silverstripe-cache'.DIRECTORY_SEPARATOR . 'cache'); // Should point to the same folder as FilesystemPublisher->destFolder

define('CACHE_CLIENTSIDE_EXPIRY', 5); // How long the client should be allowed to cache for before re-checking
//TODO explain unit


// Calculated constants
if(!defined('BASE_PATH'))
	define('BASE_PATH', rtrim(dirname(dirname(__FILE__))), DIRECTORY_SEPARATOR);



if (CACHE_ENABLED
	&& $bbypassStaticCache = empty($_COOKIE['bypassStaticCache'])
	&& $bNoGETparams = count(array_diff(array_keys($_GET), array('url', 'cacheSubdir'))) == 0
	&& $bRequestisnotPOST  = count($_POST) == 0
) {
	// Define system paths (copied from framework/core/Core.php)
	if(!defined('BASE_URL')) {
		// Determine the base URL by comparing SCRIPT_NAME to SCRIPT_FILENAME and getting the common elements
		$genaratePath = substr($_SERVER['SCRIPT_FILENAME'],0,strlen(BASE_PATH));
		$genaratePath = str_replace('/', DIRECTORY_SEPARATOR, $genaratePath);
		$genaratePath = str_replace('\\', DIRECTORY_SEPARATOR,$genaratePath);
		if($genaratePath == BASE_PATH) {
			$urlSegmentToRemove = substr($_SERVER['SCRIPT_FILENAME'],strlen(BASE_PATH));
			if(substr($_SERVER['SCRIPT_NAME'],-strlen($urlSegmentToRemove)) == $urlSegmentToRemove) {
				$baseURL = substr($_SERVER['SCRIPT_NAME'], 0, -strlen($urlSegmentToRemove));
				define('BASE_URL', rtrim($baseURL, DIRECTORY_SEPARATOR));
			}
		}
	}

	$url = $_GET['url'];

	// Remove base folders from the URL if webroot is hosted in a subfolder
	if (substr(strtolower($url), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) {
		$url = substr($url, strlen(BASE_URL));
	}

	$host = str_replace('www.', '', $_SERVER['HTTP_HOST']);

	if (isset($_GET['cacheSubdir']) && !preg_match('/[^a-zA-Z0-9\-_]/', $_GET['cacheSubdir'])) {
		$cacheDir = $_GET['cacheSubdir'].'/'; // Custom cache dir for debugging purposes
	} else {
		$cacheDir = '';
	}

	//TODO we DO use
	// Look for the file in the cachedir
	$file = trim($url, '/');
	$file = $file ? $file : 'index';


	// Route to the 'correct' index file (if applicable)
	/*
	if ($file == 'index' && file_exists(CACHE_HOMEPAGE_MAP_LOCATION)) {
		include_once CACHE_HOMEPAGE_MAP_LOCATION;
		$file = isset($homepageMap[$_SERVER['HTTP_HOST']]) ? $homepageMap[$_SERVER['HTTP_HOST']] : $file;
	}*/

	// Find file by extension (either *.html or *.php)
	$path = BASE_PATH . '/' . CACHE_BASE_DIR . DIRECTORY_SEPARATOR . $cacheDir . $file;


	$respondWith = null;

	/*if (file_exists($path)) {
		if(is_dir($path)){
			$respondWith = array('html', $path . '.html');
		}else{
			$respondWith = array('html', $path);
		}*/
	if (file_exists($path.'.html')) {
		$respondWith = array('html', $path.'.html');
	} elseif (file_exists(strtolower($path).'.html')) {
		$respondWith = array('html', strtolower($path).'.html');
	} elseif (file_exists($path.'.stale.html')) {
		$respondWith = array('stale.html', $path.'.stale.html');
	} elseif (file_exists(strtolower($path).'.stale.html')) {
		$respondWith = array('stale.html', strtolower($path).'.stale.html');
	} elseif (file_exists($path.'.php')) {
		$respondWith = array('php', $path.'.php');
	}

	//echo '<pre>'; print_r($respondWith); die();
	if ($respondWith) {
		// Cache hit. Spit out some headers and then the cache file
		if (CACHE_DEBUG) header('X-SilverStripe-Cache: hit at '.@date('r').' returning '.$file.'.'.$respondWith[0]);

		header('Expires: '.gmdate('D, d M Y H:i:s', time() + CACHE_CLIENTSIDE_EXPIRY * 60).' GMT');
		header('Cache-Control: max-age='. CACHE_CLIENTSIDE_EXPIRY .", must-revalidate");
		header('Pragma:');
		if ($respondWith[0] == 'php') include_once($respondWith[1]);
		else readfile($respondWith[1]);


	} else {
		// No cache hit... fallback to dynamic routing
		if (CACHE_DEBUG) header('X-SilverStripe-Cache: miss at '.@date('r').' on '.$cacheDir.$file);
		include BASE_PATH.DIRECTORY_SEPARATOR.'framework/main.php';

	}
} else {
	// Fall back to dynamic generation via normal routing if caching has been explicitly disabled
	if (CACHE_DEBUG) header('X-SilverStripe-Cache: cache avoided at '.@date('r'));
	include BASE_PATH.DIRECTORY_SEPARATOR.'framework/main.php';
}

?>
