<?php

/**
 * Similar to {@link RebuildStaticPagesTask}, but only queues pages for republication
 * in the {@link StaticPagesQueue}. This queue is worked off by an independent task running constantly on the server.
 */
class SiteTreeFullBuildEngine extends BuildTask {

	/**
	 * @var URLArrayObject
	 */
	protected $urlArrayObject;

	private static $dependencies = array(
		'urlArrayObject' =>  '%$URLArrayObject'
	);

	/**
	 *
	 * @var string
	 */
	protected $description = 'Full cache rebuild: adds all pages on the site to the static publishing queue';

	/** @var int - chunk size (set via config) */
	private static $records_per_request = 200;


	/**
	 * Checks if this task is enabled / disabled via the config setting
	 */
	public function __construct() {
		parent::__construct();
		if($this->config()->get('disabled') === true) {
			$this->enabled = false;
		}
	}

	public function setUrlArrayObject($o) {
		$this->urlArrayObject = $o;
	}

	public function getUrlArrayObject() {
		return $this->urlArrayObject;
	}

	/**
	 * 
	 * @param SS_HTTPRequest $request
	 * @return bool
	 */
	public function run($request) {

		if($request->getVar('urls') && is_array($request->getVar('urls'))) {
			return $this->queueURLs($request->getVar('urls'));
		}
		if($request->getVar('urls')) {
			return $this->queueURLs(explode(',', $request->getVar('urls')));
		}

		// The following shenanigans are necessary because a simple Page::get()
		// will run out of memory on large data sets. This will take the pages
		// in chunks by running this script multiple times and setting $_GET['start'].
		// Chunk size can be set via yml (SiteTreeFullBuildEngine.records_per_request).
		// To disable this functionality, just set a large chunk size and pass start=0.
		increase_time_limit_to();
		$self = get_class($this);
		$verbose = isset($_GET['verbose']);

		if (isset($_GET['start'])) {
			$this->runFrom((int)$_GET['start']);
		} else {
			foreach(array('framework','sapphire') as $dirname) {
				$script = sprintf("%s%s$dirname%scli-script.php", BASE_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
				if (file_exists($script)) break;
			}

			$total = $this->getAllLivePages()->count();
			echo "Adding all pages to the queue. Total: $total\n\n";
			for ($offset = 0; $offset < $total; $offset += self::config()->records_per_request) {
				echo "$offset..";
				$cmd = "php $script dev/tasks/$self start=$offset";
				if($verbose) echo "\n  Running '$cmd'\n";
				$res = $verbose ? passthru($cmd) : `$cmd`;
				if($verbose) echo "  ".preg_replace('/\r\n|\n/', '$0  ', $res)."\n";
			}
		}
	}


	/**
	 * Process a chunk of pages
	 * @param $start
	 */
	protected function runFrom($start) {
		$chunkSize = (int)self::config()->records_per_request;
		$pages = $this->getAllLivePages()->sort('ID')->limit($chunkSize, $start);
		$count = 0;

		// Collect all URLs into the queue
		foreach($pages as $page) {
			if (is_callable(array($page, 'urlsToCache'))) {
				$this->getUrlArrayObject()->addUrlsOnBehalf($page->urlsToCache(), $page);
				$count++;
			}
		}

		echo sprintf("SiteTreeFullBuildEngine: Queuing %d pages".PHP_EOL, $count);
	}


	/**
	 * Adds an array of urls to the Queue
	 *
	 * @param array $urls
	 * @return bool - if any pages were queued
	 */
	protected function queueURLs($urls = array()) {
		echo sprintf("SiteTreeFullBuildEngine: Queuing %d pages".PHP_EOL, count($urls));
		if(!count($urls)) {
			return false;
		}
		$this->getUrlArrayObject()->addUrls($urls);
		return true;
	}
	
	/**
	 * 
	 * @return DataList
	 */
	protected function getAllLivePages() {
		ini_set('memory_limit', '512M');
		$oldMode = Versioned::get_reading_mode();
		if(class_exists('Subsite')) {
			Subsite::disable_subsite_filter(true);
		}
		if(class_exists('Translatable')) {
			Translatable::disable_locale_filter();
		}		
		Versioned::reading_stage('Live');
		$pages = DataObject::get("SiteTree");
		Versioned::set_reading_mode($oldMode);
		return $pages;
	}

}
