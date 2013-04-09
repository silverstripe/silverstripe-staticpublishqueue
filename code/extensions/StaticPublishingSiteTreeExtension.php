<?php
class StaticPublishingSiteTreeExtension extends DataExtension {

	//include all ancestor pages in static publishing queue build, or just one level of parent
	public static $includeAncestors = true;

	function onAfterPublish() {
		$urls = $this->pagesAffected();
		if(!empty($urls)) URLArrayObject::add_urls($urls);
	}

	function onAfterUnpublish() {
		$urls = $this->pagesAffected(true);
		if(!empty($urls)) URLArrayObject::add_urls($urls);
	}

	function pagesAffected($unpublish = false) {
		$urls = array();
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
			$urls = $thisPage->subPagesToCache();
			if($thisPage instanceof RedirectorPage){
				$urls = array_merge((array)$urls, (array)$thisPage->regularLink());
			}
		}

		Versioned::set_reading_mode($oldMode);
		$this->owner->extend('extraPagesAffected',$this->owner, $urls);

		return $urls;
	}


	/**
	 * Regenerate the parent page only - or at least try. Do this right away, don't wait for the queue
	 * @return array Of relative URLs
	 */
	function pagesAffectedByUnpublishing() {
		return $this->owner->pagesAffected(true);
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
		if($this->owner instanceof RedirectorPage) $urls[$this->owner->regularLink()] = 60;
		else $urls[$this->owner->Link()] = 60;  //higher priority for the actual page, not others

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