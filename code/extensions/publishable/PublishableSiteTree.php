<?php
/**
 * Bare-bones impelmentation of a publishable page.
 *
 * You can override this either by implementing one of the interfaces the class directly, or by applying
 * an extension via the config system ordering (inject your extension "before" the PublishableSiteTree).
 *
 * @see SiteTreePublishingEngine
 */

class PublishableSiteTree extends Extension implements StaticallyPublishable, StaticPublishingTrigger {

	public function getMyRedirectorPages() {
		return RedirectorPage::get()->filter(array('LinkToID' => $this->owner->ID));
	}

	/**
	 * Update both the object and it's parent on publishing. Update parent on unpublishing.
	 */
	public function objectsToUpdate($context) {

		switch ($context['action']) {

			case 'publish':
				$list = new ArrayList(array($this->owner));
				if ($this->owner->ParentID) $list->push($this->owner->Parent());
				return $list;

			case 'unpublish':
				$list = new ArrayList(array());
				if ($this->owner->ParentID) $list->push($this->owner->Parent());
				return $list;

		}

	}

	/**
	 * Remove the object on unpublishing (the parent will get updated via objectsToUpdate).
	 */
	public function objectsToDelete($context) {

		switch ($context['action']) {

			case 'publish':
				return new ArrayList(array());

			case 'unpublish':
				$list = new ArrayList(array($this->owner));

				// Trigger deletion of all cached redirectors pointing here.
				$redirectors = $this->owner->getMyRedirectorPages();
				if ($redirectors->count()>0) {
					foreach ($redirectors as $redirector) {
						$list->push($redirector);
					}
				}

				return $list;

		}

	}

	/**
	 * The only URL belonging to this object is it's own URL.
	 */
	public function urlsToCache() {
		return array($this->owner->Link() => 0);
	}

}

