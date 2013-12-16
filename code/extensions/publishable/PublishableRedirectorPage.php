<?php
/**
 * RedirectorPage-specific implementation.
 */

class PublishableRedirectorPage extends Extension implements StaticallyPublishable {

	public function urlsToCache() {
		return array($this->owner->regularLink() => 0);
	}

}


