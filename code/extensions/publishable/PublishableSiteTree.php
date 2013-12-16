<?php
/**
 * Bare-bones impelmentation of a publishable page.
 *
 * You can override this either by implementing one of the interfaces the class directly, or by applying
 * an extension via the config system ordering (inject your extension "before" the PublishableSiteTree).
 *
 * @TODO: re-implement optional publishing of all the ancestors up to the root? Currently it only republishes the parent
 *
 * @see SiteTreePublishingEngine
 */

class PublishableSiteTree extends DataExtension implements StaticallyPublishable, StaticPublishingTrigger {

	public function getMyVirtualPages() {
		return VirtualPage::get()->filter(array('CopyContentFromID' => $this->owner->ID));
	}

	public function objectsToUpdate($context) {

		switch ($context['action']) {

			case 'publish':
				// Trigger refresh of the page itself.
				$list = new ArrayList(array($this->owner));

				// Refresh the parent.
				if ($this->owner->ParentID) $list->push($this->owner->Parent());

				// Refresh related virtual pages.
				$virtuals = $this->owner->getMyVirtualPages();
				if ($virtuals->count()>0) {
					foreach ($virtuals as $virtual) {
						$list->push($virtual);
					}
				}

				return $list;

			case 'unpublish':
				$list = new ArrayList(array());

				// Refresh the parent
				if ($this->owner->ParentID) $list->push($this->owner->Parent());

				return $list;

		}

	}

	public function objectsToDelete($context) {

		switch ($context['action']) {

			case 'publish':
				return new ArrayList(array());

			case 'unpublish':
				// Trigger cache removal for this page.
				$list = new ArrayList(array($this->owner));

				// Trigger removal of the related virtual pages.
				$virtuals = $this->owner->getMyVirtualPages();
				if ($virtuals->count()>0) {
					foreach ($virtuals as $virtual) {
						$list->push($virtual);
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

