<?php
/**
 * Interface for statically publishable objects. It does not define how change is triggered,
 * just that the implementing class has a family of URLs that it needs to maintain (urlsToCache).
 *
 * It is expected that a full cache can be rebuilt by finding all objects that implement
 * this interface, and calling urlsToCache on these. This implies that any URL should belong
 * to just one object.
 */
interface StaticallyPublishable {

	/**
	 * Get a list of URLs that this object wishes to maintain. URLs should not overlap with other objects.
	 *
	 * Note: include the URL of the object itself!
	 *
	 * @return array associative array of URL (string) => Priority (int)
	 */
	public function urlsToCache();

}
