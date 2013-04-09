<?php
/**
 * Similar to {@link RebuildStaticPagesTask}, but only queues pages for republication
 * in the {@link StaticPagesQueue}. This queue is worked off by an independent task running constantly on the server.
 */
class RebuildStaticCacheTask extends BuildTask {

	protected $description = 'Full cache rebuild: adds all pages on the site to the static publishing queue';

	public function __construct() {
		parent::__construct();
		$this->enabled = $this->config()->get('enabled');
	}

	function run($request) {
		ini_set('memory_limit','512M');
		$oldMode = Versioned::get_reading_mode();
		Versioned::reading_stage('Live');

		$urls = array();
		$page = singleton('SiteTree');
		if(!empty($_GET['urls'])) {
			$urls = (is_array($_GET['urls'])) ? $_GET['urls'] : explode(',', $_GET['urls']);
		} else {
			// memory intensive depending on number of pages
			$pages = DataObject::get("SiteTree");

			if ($pages) foreach($pages as $page) {
				if($page instanceof RedirectorPage){
					$link = $page->regularLink();
				}else{
					$link = $page->Link();
				}
				//sub-pages are not necessary, since this will already include every page on the site
				$urls = array_merge($urls, (array)$link);
			}
		}

		echo sprintf("StaticPagesQueueAllTask: Queuing %d pages\n", count($urls));
		URLArrayObject::add_urls($urls);

		Versioned::set_reading_mode($oldMode);
	}

}
