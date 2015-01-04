<?php

/**
 * @TODO This is a legacy code copied from staticpublisher. Would be good to clean it up. Some functions are
 * no longer working, or are simply not used any more.
 *
 * @TODO Also the interface of publishPages and urlsToPaths could be better - specifically see how the
 * SiteTreePublishingEngine::unpublishPagesAndStaleCopies and the BuildStaticCacheFromQueue::createCachedFiles
 * struggle to communicate with FilesystemPublisher - they need to set up various global config parameters to
 * achieve the desired behaviour.
 *
 * @package staticpublisher
 */
class FilesystemPublisher extends DataExtension {

	/**
	 * @var URLArrayObject
	 */
	protected $urlArrayObject;

	static $dependencies = array(
		'urlArrayObject' =>  '%$URLArrayObject'
	);

	/**
	 * @var string
	 */
	protected $destFolder = 'cache';
	
	/**
	 * @var string
	 */
	protected $fileExtension = 'html';
	
	/**
	 *
	 * @var bool
	 */
	private $static_publisher_theme = false;
	
	/**
	 * @var string
	 *
	 * @config
	 */
	private static $static_base_url = null;
	
	/**
	 * @config
	 *
	 * @var Boolean Use domain based cacheing (put cache files into a domain subfolder)
	 * This must be true if you are using this with the "subsites" module.
	 * Please note that this form of caching requires all URLs to be provided absolute
	 * (not relative to the webroot) via {@link SiteTree->AbsoluteLink()}.
	 */
	private static $domain_based_caching = false;
	
	public function setUrlArrayObject($o) {
		$this->urlArrayObject = $o;
	}

	public function getUrlArrayObject() {
		return $this->urlArrayObject;
	}

	/**
	 * Set a different base URL for the static copy of the site.
	 * This can be useful if you are running the CMS on a different domain from the website.
	 *
	 * @deprecated 3.2 Use the "FilesystemPublisher.static_base_url" config setting instead
	 */
	static public function set_static_base_url($url) {
		Deprecation::notice('3.2', 'Use the "FilesystemPublisher.static_base_url" config setting instead');

		Config::inst()->update('FilesystemPublisher', 'static_base_url', $url);
	}
	
	/**
	 * @param $destFolder The folder to save the cached site into.
	 *   This needs to be set in framework/static-main.php as well through the {@link $cacheBaseDir} variable.
	 * @param $fileExtension  The file extension to use, e.g 'html'.  
	 *   If omitted, then each page will be placed in its own directory, 
	 *   with the filename 'index.html'.  If you set the extension to PHP, then a simple PHP script will
	 *   be generated that can do appropriate cache & redirect header negotation.
	 */
	public function __construct($destFolder = 'cache', $fileExtension = null) {
		// Remove trailing slash from folder
		if(substr($destFolder, -1) == '/') {
			$destFolder = substr($destFolder, 0, -1);
		}
		
		$this->destFolder = $destFolder;

		if($fileExtension) {
			$this->fileExtension = $fileExtension;
		}
		
		parent::__construct();
	}

	/**
	 * Transforms relative or absolute URLs to their static path equivalent.
	 * This needs to be the same logic that's used to look up these paths through
	 * framework/static-main.php. Does not include the {@link $destFolder} prefix.
	 * 
	 * URL filtering will have already taken place for direct SiteTree links via SiteTree->generateURLSegment()).
	 * For all other links (e.g. custom controller actions), we assume that they're pre-sanitized
	 * to suit the filesystem needs, as its impossible to sanitize them without risking to break
	 * the underlying naming assumptions in URL routing (e.g. controller method names).
	 * 
	 * Examples (without $domain_based_caching):
	 *  - http://mysite.com/mywebroot/ => /index.html (assuming your webroot is in a subfolder)
	 *  - http://mysite.com/about-us => /about-us.html
	 *  - http://mysite.com/parent/child => /parent/child.html
	 * 
	 * Examples (with $domain_based_caching):
	 *  - http://mysite.com/mywebroot/ => /mysite.com/index.html (assuming your webroot is in a subfolder)
	 *  - http://mysite.com/about-us => /mysite.com/about-us.html
	 *  - http://myothersite.com/about-us => /myothersite.com/about-us.html
	 *  - http://subdomain.mysite.com/parent/child => /subdomain.mysite.com/parent/child.html
	 * 
	 * @param array $urls Absolute or relative URLs
	 * @return array Map of original URLs to filesystem paths (relative to {@link $destFolder}).
	 */
	public function urlsToPaths($urls) {
		$mappedUrls = array();
		
		foreach($urls as $url) {

			// parse_url() is not multibyte safe, see https://bugs.php.net/bug.php?id=52923.
			// We assume that the URL hsa been correctly encoded either on storage (for SiteTree->URLSegment),
			// or through URL collection (for controller method names etc.).
			$urlParts = @parse_url($url);
			
			// Remove base folders from the URL if webroot is hosted in a subfolder (same as static-main.php)
			$path = isset($urlParts['path']) ? $urlParts['path'] : '';
			if(mb_substr(mb_strtolower($path), 0, mb_strlen(BASE_URL)) == mb_strtolower(BASE_URL)) {
				$urlSegment = mb_substr($path, mb_strlen(BASE_URL));
			} else {
				$urlSegment = $path;
			}

			// Normalize URLs
			$urlSegment = trim($urlSegment, '/');

			$filename = $urlSegment ? "$urlSegment.$this->fileExtension" : "index.$this->fileExtension";

			if (Config::inst()->get('FilesystemPublisher', 'domain_based_caching')) {
				if (!$urlParts) continue; // seriously malformed url here...
				if (isset($urlParts['host'])) $filename = $urlParts['host'] . '/' . $filename;
			}
		
			$mappedUrls[$url] = ((dirname($filename) == '/') ? '' :  (dirname($filename).'/')).basename($filename);
		}

		return $mappedUrls;
	}
	
	/**
 	 * Uses {@link Director::test()} to perform in-memory HTTP requests
 	 * on the passed-in URLs.
 	 * 
 	 * @param  array $urls Relative URLs 
 	 * @return array Result, keyed by URL. Keys: 
 	 *               - "statuscode": The HTTP status code
 	 *               - "redirect": A redirect location (if applicable)
 	 *               - "path": The filesystem path where the cache has been written
 	 */
	public function publishPages($urls) { 
		$result = array();

		// Do we need to map these?
		// Detect a numerically indexed arrays
		if (is_numeric(join('', array_keys($urls)))) $urls = $this->urlsToPaths($urls);
		
		// This can be quite memory hungry and time-consuming
		// @todo - Make a more memory efficient publisher
		increase_time_limit_to();
		increase_memory_limit_to();
		
		Config::inst()->nest();

		// Set the appropriate theme for this publication batch.
		// This may have been set explicitly via StaticPublisher::static_publisher_theme,
		// or we can use the last non-null theme.
		$customTheme = Config::inst()->get('FilesystemPublisher', 'static_publisher_theme');
		if($customTheme) {
			Config::inst()->update('SSViewer', 'theme', $customTheme);
		}

		// Ensure that the theme that is set gets used.
		Config::inst()->update('SSViewer', 'theme_enabled', true);
			
		$currentBaseURL = Director::baseURL();
		$staticBaseUrl = Config::inst()->get('FilesystemPublisher', 'static_base_url');
		
		if($this->fileExtension == 'php') {
			Config::inst()->update('SSViewer', 'rewrite_hash_links', 'php'); 
		}
		
		if(Config::inst()->get('FilesystemPublisher', 'echo_progress')) {
			echo $this->class.": Publishing to " . $staticBaseUrl . "\n";		
		}
		
		$files = array();
		$i = 0;
		$totalURLs = sizeof($urls);

		foreach($urls as $url => $path) {
			$origUrl = $url;
			$result[$origUrl] = array(
				'statuscode' => null, 
				'redirect' => null, 
				'path' => null
			);

			$i++;

			if($url && !is_string($url)) {
				user_error("Bad url:" . var_export($url,true), E_USER_WARNING);
				continue;
			}
			
			if(Config::inst()->get('FilesystemPublisher', 'echo_progress')) {
				echo " * Publishing page $i/$totalURLs: $url\n";
				flush();
			}

			Requirements::clear();
			
			if($url == "") $url = "/";
			if(Director::is_relative_url($url)) $url = Director::absoluteURL($url);
			$response = Director::test(str_replace('+', ' ', $url));

			if (!$response) continue;

			if($response) {
				$result[$origUrl]['statuscode'] = $response->getStatusCode();
			}
			Requirements::clear();
			
			singleton('DataObject')->flushCache();

			// Check for ErrorPages generating output - we want to handle this in a special way below.
			$isErrorPage = false;
			$pageObject = null;
			if ($response && is_object($response) && ((int)$response->getStatusCode())>=400) {
				$obj = $this->owner->getUrlArrayObject()->getObject($url);
				if ($obj && $obj instanceof ErrorPage) $isErrorPage = true;
			}

			// Skip any responses with a 404 status code unless it's the ErrorPage itself.
			if (!$isErrorPage && is_object($response) && $response->getStatusCode()=='404') continue;

			// Generate file content.
			// PHP file caching will generate a simple script from a template
			if($this->fileExtension == 'php') {
				if(is_object($response)) {
					if($response->getStatusCode() == '301' || $response->getStatusCode() == '302') {
						$content = $this->generatePHPCacheRedirection($response->getHeader('Location'));
					} else {
						$content = $this->generatePHPCacheFile($response->getBody(), HTTP::get_cache_age(), date('Y-m-d H:i:s'), $response->getHeader('Content-Type'));
					}
				} else {
					$content = $this->generatePHPCacheFile($response . '', HTTP::get_cache_age(), date('Y-m-d H:i:s'), $response->getHeader('Content-Type'));
				}
				
			// HTML file caching generally just creates a simple file
			} else {
				if(is_object($response)) {
					if($response->getStatusCode() == '301' || $response->getStatusCode() == '302') {
						$absoluteURL = Director::absoluteURL($response->getHeader('Location'));
						$result[$origUrl]['redirect'] = $response->getHeader('Location');
						$content = "<meta http-equiv=\"refresh\" content=\"2; URL=$absoluteURL\">";
					} else {
						$content = $response->getBody();
					}
				} else {
					$content = $response . '';
				}
			}
			
			if(Config::inst()->get('FilesystemPublisher', 'include_caching_metadata')) {
				$content = str_replace(
					'</html>', 
					sprintf("</html>\n\n<!-- %s -->", implode(" ", $this->getMetadata($url))),
					$content
				);
			}

			if (!$isErrorPage) {

				$files[$origUrl] = array(
					'Content' => $content,
					'Folder' => dirname($path).'/',
					'Filename' => basename($path),
				);

			} else {

				// Generate a static version of the error page with a standardised name, so they can be plugged
				// into catch-all webserver statements such as Apache's ErrorDocument.
				$code = (int)$response->getStatusCode();
				$files[$origUrl] = array(
					'Content' => $content,
					'Folder' => dirname($path).'/',
					'Filename' => "error-$code.html",
				);

			}
			
			// Add externals
			/*
			$externals = $this->externalReferencesFor($content);
			if($externals) foreach($externals as $external) {
				// Skip absolute URLs
				if(preg_match('/^[a-zA-Z]+:\/\//', $external)) continue;
				// Drop querystring parameters
				$external = strtok($external, '?');
				
				if(file_exists("../" . $external)) {
					// Break into folder and filename
					if(preg_match('/^(.*\/)([^\/]+)$/', $external, $matches)) {
						$files[$external] = array(
							"Copy" => "../$external",
							"Folder" => $matches[1],
							"Filename" => $matches[2],
						);
					
					} else {
						user_error("Can't parse external: $external", E_USER_WARNING);
					}
				} else {
					$missingFiles[$external] = true;
				}
			}*/
		}

		if($this->fileExtension == 'php') {
			Config::inst()->update('SSViewer', 'rewrite_hash_links', true); 
		}

		$base = BASE_PATH . "/$this->destFolder";
		
		foreach($files as $origUrl => $file) {
			Filesystem::makeFolder("$base/$file[Folder]");
			
			$path = "$base/$file[Folder]$file[Filename]";
			$result[$origUrl]['path'] = $path;
			
			if(isset($file['Content'])) {
				$fh = fopen($path, "w");
				fwrite($fh, $file['Content']);
				fclose($fh);
			} else if(isset($file['Copy'])) {
				copy($file['Copy'], $path);
			}
		}

		Config::inst()->unnest();

		return $result;
	}
	
	/**
	 * Generate the templated content for a PHP script that can serve up the 
	 * given piece of content with the given age and expiry.
	 *
	 * @param string $content
	 * @param string $age
	 * @param string $lastModified
	 * @param string $contentType
	 *
	 * @return string
	 */
	protected function generatePHPCacheFile($content, $age, $lastModified, $contentType) {
		$template = file_get_contents(STATIC_MODULE_DIR . '/code/CachedPHPPage.tmpl');
		return str_replace(
			array('**MAX_AGE**', '**LAST_MODIFIED**', '**CONTENT**', '**CONTENT_TYPE**'),
			array((int)$age, $lastModified, $content, $contentType),
			$template
		);
	}

	/**
	 * Generate the templated content for a PHP script that can serve up a 301 
	 * redirect to the given destination.
	 *
	 * @param string $destination
	 *
	 * @return string
	 */
	protected function generatePHPCacheRedirection($destination) {
		$template = file_get_contents(STATIC_MODULE_DIR . '/code/CachedPHPRedirection.tmpl');

		return str_replace(
			array('**DESTINATION**'),
			array($destination),
			$template
		);
	}
	
	/**
	 * @return string
	 */
	public function getDestDir() {
		return BASE_PATH . '/' . $this->destFolder;
	}
	
	/**
	 * Return an array of all the existing static cache files, as a map of 
	 * URL => file. Only returns cache files that will actually map to a URL, 
	 * based on urlsToPaths.
	 *
	 * @return array
	 */
	public function getExistingStaticCacheFiles() {
		$cacheDir = BASE_PATH . '/' . $this->destFolder;

		$urlMapper = array_flip($this->urlsToPaths($this->owner->allPagesToCache()));
		
		$output = array();
		
		// Glob each dir, then glob each one of those
		foreach(glob("$cacheDir/*", GLOB_ONLYDIR) as $cacheDir) {
			foreach(glob($cacheDir.'/*') as $cacheFile) {
				$mapKey = str_replace(BASE_PATH . "/cache/","",$cacheFile);
				if(isset($urlMapper[$mapKey])) {
					$url = $urlMapper[$mapKey];
					$output[$url] = $cacheFile;
				}
			}
		}
		
		return $output;
	}

	/**
	 * @param string $url
	 *
	 * @return array
	 */
	public function getMetadata($url) {
		return array(
			'Cache generated on ' . date('Y-m-d H:i:s T (O)')
		);
	}
}
