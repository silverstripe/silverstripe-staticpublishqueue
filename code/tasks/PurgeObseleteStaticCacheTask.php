<?php
/**
 * Cleans up orphaned cache files that don't belong to any current live SiteTree page.
 * If there are any "special cases" e.g. blog tag pages which are static published but don't exist in SiteTree,
 * these can be excluded from this script using the Configuration system, for example:
 *
 *   PurgeObseleteStaticCacheTask:
 *     exclude:
 *       - '/^blog\/tag\//'
 *       - '/\.backup$/'
 *
 * Note that you need to escape forward slashes in your regular expressions and exclude the file extension (e.g. .html).
 */
class PurgeObseleteStaticCacheTask extends BuildTask {

	protected $description = 'Purge obselete: cleans up obselete/orphaned staticpublisher cache files';

	public function __construct() {
		parent::__construct();
		if ($this->config()->get('disabled') === true) {
			$this->enabled = false ;
		}
	}

	function run($request) {
		ini_set('memory_limit','512M');
		$oldMode = Versioned::get_reading_mode();
		Versioned::reading_stage('Live');

		foreach(Config::inst()->get('SiteTree', 'extensions') as $extension) {
			if(preg_match('/FilesystemPublisher\(\'(\w+)\',\s?\'(\w+)\'\)/', $extension, $matches)) {
				$directory = BASE_PATH . '/' . $matches[1];
				$fileext = $matches[2];
				break;
			}
		}

		if(!isset($directory, $fileext)) die('FilesystemPublisher configuration not found.');

		// Get list of cacheable pages in the live SiteTree
		$pages = singleton('Page')->allPagesToCache();
		foreach($pages as $page_link) {
			$page = SiteTree::get_by_link($page_link);
			if($page && $page->getLiveURLSegment()) {
				if($subpages = $page->subPagesToCache()) {
					$pages = array_merge($pages, $subpages);
					unset($subpages);
				}
				if($affectedpages = $page->pagesAffected()) {
					$pages = array_merge($pages, $affectedpages);
					unset($affectedpages);
				}
			}
		}

		// Get array of custom exclusion regexes from Config system
		$excludes = $this->config()->get('exclude');

		$removeURLs = array();

		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
		$it->rewind();
		while($it->valid()) {

			$file_relative = $it->getSubPathName();

			// Get URL path from filename
			$urlpath = substr($file_relative, 0, strpos($file_relative, '.' . $fileext));

			// Exclude dot-files
			if($it->isDot()) {
				$it->next();
			}

			// Handle homepage special case
			if($file_relative == 'index.html') $urlpath = '';

			// Exclude files that do not end in the file extension specified for FilesystemPublisher
			$length = strlen('.' . $fileext);
			if(substr($file_relative, -$length) != '.' . $fileext) {
				$it->next();
				continue;
			}

			// Exclude stale files (these are automatically deleted alongside the fresh file)
			$length = strlen('.stale.' . $fileext);
			if(substr($file_relative, -$length) == '.stale.' . $fileext) {
				$it->next();
				continue;
			}

			// Exclude files matching regexes supplied in the Config system
			if($excludes && is_array($excludes)) {
				foreach($excludes as $exclude) {
					if(preg_match($exclude, $urlpath)) {
						$it->next();
						continue 2;
					}
				}
			}

			// Exclude files for pages that exist in the SiteTree as well as known cacheable sub-pages
			// This array_intersect checks against any combination of leading and trailing slashes in the $pages values
			if(array_intersect(array($urlpath, $urlpath.'/', '/'.$urlpath, '/'.$urlpath.'/'), $pages)) {
				$it->next();
				continue;
			}

			$removeURLs[$urlpath] = $file_relative;
			echo $file_relative . "\n";

			$it->next();

		}

		echo sprintf("PurgeObseleteStaticCacheTask: Deleting %d obselete pages from cache\n", count($removeURLs));

		// Remove current and stale cache files
		singleton('SiteTree')->unpublishPagesAndStaleCopies($removeURLs);

		Versioned::set_reading_mode($oldMode);
	}

}