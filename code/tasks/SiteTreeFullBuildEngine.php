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
	 */
	public function run($request) {

		if($request->getVar('urls') && is_array($request->getVar('urls'))) {
			return $this->queueURLs($request->getVar('urls'));
		}
		if($request->getVar('urls')) {
			return $this->queueURLs(explode(',', $request->getVar('urls')));
		}
		$pages = $this->getAllLivePages();

		// Collect all URLs into associative array.
		foreach($pages as $page) {
			if (is_callable(array($page, 'urlsToCache'))) {
				$this->getUrlArrayObject()->addUrlsOnBehalf($page->urlsToCache(), $page);
			}
		}

	}

	/**
	 * Adds an array of urls to the Queue
	 *  
	 * @param array $count
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
		Versioned::reading_stage('Live');
		$pages = DataObject::get("SiteTree");
		Versioned::set_reading_mode($oldMode);
		return $pages;
	}

}
