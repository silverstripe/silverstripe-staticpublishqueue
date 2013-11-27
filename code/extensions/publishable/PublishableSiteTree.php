<?php
/**
 * Bare-bones impelmentation of a publishable page.
 *
 * You can override this either by implementing one of the interfaces the class directly, or by applying
 * an extension via the config system using: `After: 'staticpublishqueue/*'`, which will overwrite the dynamic
 * methods.
 *
 * @see SiteTreePublishingEngine
 */

class PublishableSiteTree extends Extension implements StaticallyPublishable, StaticPublishingTrigger {

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
				return new ArrayList(array($this->owner));

		}

	}

	/**
	 * The only URL belonging to this object is it's own URL.
	 */
	public function urlsToCache() {
		return array($this->owner->Link() => 0);
	}

}

