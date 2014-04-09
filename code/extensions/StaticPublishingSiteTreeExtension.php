<?php
class StaticPublishingSiteTreeExtension extends DataExtension {

	//include all ancestor pages in static publishing queue build, or just one level of parent
	public static $includeAncestors = true;

	function onAfterPublish() {
		$urls = $this->pagesAffected();
		if(!empty($urls)) URLArrayObject::add_urls($urls, get_class($this) .', ID:'. $this->owner->ID .', URL:'. $this->owner->Link());
	}

	function onAfterUnpublish() {
		//get all pages that should be removed
		$removePages = $this->owner->pagesToRemoveAfterUnpublish();
		$updateURLs = array();  //urls to republish
		$removeURLs = array();  //urls to delete the static cache from
		foreach($removePages as $page) {
			if ($page instanceof RedirectorPage) $removeURLs[] = $page->regularLink();
			else $removeURLs[] = $page->Link();

			//and update any pages that might have been linking to those pages
			$updateURLs = array_merge((array)$updateURLs, (array)$page->pagesAffected(true));
		}

		increase_time_limit_to();
		increase_memory_limit_to();
		singleton("SiteTree")->unpublishPagesAndStaleCopies($removeURLs); //remove those pages (right now)

		if(!empty($updateURLs)) URLArrayObject::add_urls($updateURLs, get_class($this) .', ID:'. $this->owner->ID .', URL:'. $this->owner->Link());
	}

	/**
	 * Removes the unpublished page's static cache file as well as its 'stale.html' copy.
	 * Copied from: FilesystemPublisher->unpublishPages($urls)
	 */
	public function unpublishPagesAndStaleCopies($urls) {
		// Detect a numerically indexed arrays
		if (is_numeric(join('', array_keys($urls)))) $urls = $this->owner->urlsToPaths($urls);

		$cacheBaseDir = $this->owner->getDestDir();

		foreach($urls as $url => $path) {
			if (file_exists($cacheBaseDir.'/'.$path)) {
				@unlink($cacheBaseDir.'/'.$path);
			}
			$lastDot = strrpos($path, '.'); //find last dot
			if ($lastDot !== false) {
				$stalePath = substr($path, 0, $lastDot) . '.stale' . substr($path, $lastDot);
				if (file_exists($cacheBaseDir.'/'.$stalePath)) {
					@unlink($cacheBaseDir.'/'.$stalePath);
				}
			}
		}
	}

	function pagesToRemoveAfterUnpublish() {
		$pages = array();
		$pages[] = $this->owner;

		// Including VirtualPages with reference this page
		$virtualPages = VirtualPage::get()->filter(array('CopyContentFromID' => $this->owner->ID));
		if ($virtualPages->Count() > 0) {
			foreach($virtualPages as $virtualPage) {
				$pages[] = $virtualPage;
			}
		}

		// Including RedirectorPages with reference this page
		$redirectorPages = RedirectorPage::get()->filter(array('LinkToID' => $this->owner->ID));
		if($redirectorPages->Count() > 0) {
			foreach($redirectorPages as $redirectorPage) {
				$pages[] = $redirectorPage;
			}
		}

		$this->owner->extend('extraPagesToRemove',$this->owner, $pages);

		return $pages;
	}

	function pagesAffected($unpublish = false) {
		$urls = array();
		if ($this->owner->hasMethod('pagesAffectedByChanges')) {
			$urls = $this->owner->pagesAffectedByChanges();
		}

		$oldMode = Versioned::get_reading_mode();
		Versioned::reading_stage('Live');

		//the the live version of the current page
		if ($unpublish) {
			//We no longer have access to the live page, so can just try to grab the ParentID.
			$thisPage = SiteTree::get()->byID($this->owner->ParentID);
		} else {
			$thisPage = SiteTree::get()->byID($this->owner->ID);
		}

		if ($thisPage) {
			//include any related pages (redirector pages and virtual pages)
			$urls = array_merge((array)$urls, (array)$thisPage->subPagesToCache());
			if($thisPage instanceof RedirectorPage){
				$urls = array_merge((array)$urls, (array)$thisPage->regularLink());
			}
		}

		Versioned::set_reading_mode($oldMode);
		$this->owner->extend('extraPagesAffected',$this->owner, $urls);

		return $urls;
	}

	/**
	 * Get a list of URLs to cache related to this page,
	 * e.g. through custom controller actions or views like paginated lists.
	 *
	 * @return array Of relative URLs
	 */
	function subPagesToCache() {
		$urls = array();

		// Add redirector page (if required) or just include the current page
		if($this->owner instanceof RedirectorPage) $urls[] = $this->owner->regularLink();
		else $urls[] = $this->owner->Link();  //higher priority for the actual page, not others

		//include the parent and the parent's parents, etc
		$parent = $this->owner->Parent();
		if(!empty($parent) && $parent->ID > 0) {
			if (self::$includeAncestors) {
				$urls = array_merge((array)$urls, (array)$parent->subPagesToCache());
			} else {
				$urls = array_merge((array)$urls, (array)$parent->Link());
			}
		}

		// Including VirtualPages with this page as an original
		$virtualPages = VirtualPage::get()->filter(array('CopyContentFromID' => $this->owner->ID));
		if ($virtualPages->Count() > 0) {
			foreach($virtualPages as $virtualPage) {
				$urls = array_merge((array)$urls, (array)$virtualPage->subPagesToCache());
				if($p = $virtualPage->Parent) $urls = array_merge((array)$urls, (array)$p->subPagesToCache());
			}
		}

		// Including RedirectorPage
		$redirectorPages = RedirectorPage::get()->filter(array('LinkToID' => $this->owner->ID));
		if($redirectorPages->Count() > 0) {
			foreach($redirectorPages as $redirectorPage) {
				$urls[] = $redirectorPage->regularLink();
			}
		}

		$this->owner->extend('extraSubPagesToCache',$this->owner, $urls);

		return $urls;
	}
}