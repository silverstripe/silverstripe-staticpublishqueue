<?php
/**
 * This extension couples to the StaticallyPublishable and StaticPublishingTrigger implementations
 * on the SiteTree objects and makes sure the actual change to SiteTree is triggered/enqueued.
 *
 * Provides the following information as a context to StaticPublishingTrigger:
 * * action - name of the executed action: publish or unpublish
 *
 * @see PublishableSiteTree
 */

class SiteTreePublishingEngine extends DataExtension {

	/**
	 * @var URLArrayObject
	 */
	protected $urlArrayObject;

	private static $dependencies = array(
		'urlArrayObject' =>  '%$URLArrayObject'
	);

	/**
	 * Queues the urls to be flushed into the queue.
	 */
	private $toUpdate = array();

	/**
	 * Queues the urls to be deleted as part of a next flush operation.
	 */
	private $toDelete = array();

	public function setUrlArrayObject($o) {
		$this->urlArrayObject = $o;
	}

	public function getUrlArrayObject() {
		return $this->urlArrayObject;
	}

	public function getToUpdate() {
		return $this->toUpdate;
	}

	public function getToDelete() {
		return $this->toDelete;
	}

	public function setToUpdate($toUpdate) {
		$this->toUpdate = $toUpdate;
	}

	public function setToDelete($toDelete) {
		$this->toDelete = $toDelete;
	}

	public function onAfterPublish() {
		$context = array(
			'action' => 'publish'
		);
		$this->collectChanges($context);
		$this->flushChanges();
	}

	public function onBeforeUnpublish() {
		$context = array(
			'action' => 'unpublish'
		);
		$this->collectChanges($context);
	}

	public function onAfterUnpublish() {
		$this->flushChanges();
	}

	/**
	 * Collect all changes for the given context.
	 */
	public function collectChanges($context) {
		increase_time_limit_to();
		increase_memory_limit_to();

		if (is_callable(array($this->owner, 'objectsToUpdate'))) {

			$toUpdate = $this->owner->objectsToUpdate($context);

			if ($toUpdate) foreach ($toUpdate as $object) {
				if (!is_callable(array($this->owner, 'urlsToCache'))) continue;

				$urls = $object->urlsToCache();
				if(!empty($urls)) {
					$this->toUpdate = array_merge(
						$this->toUpdate,
						$this->owner->getUrlArrayObject()->addObjects($urls, $object)
					);
				}

			}
		}

		if (is_callable(array($this->owner, 'objectsToDelete'))) {

			$toDelete = $this->owner->objectsToDelete($context);

			if ($toDelete) foreach ($toDelete as $object) {
				if (!is_callable(array($this->owner, 'urlsToCache'))) continue;

				$urls = $object->urlsToCache();
				if(!empty($urls)) {
					$this->toDelete = array_merge(
						$this->toDelete,
						$this->owner->getUrlArrayObject()->addObjects($urls, $object)
					);
				}
			}

		}

	}

	/**
	 * Execute URL deletions, enqueue URL updates.
	 */
	public function flushChanges() {
		if(!empty($this->toUpdate)) {
			// Enqueue for republishing.
			$this->owner->getUrlArrayObject()->addUrls($this->toUpdate);
			// Meanwhile, remove the regular cache files leaving behind only the stale variants.
			$pathMap = $this->owner->convertUrlsToPathMap(array_keys($this->toUpdate));
			$this->owner->deleteRegularFiles(array_values($pathMap));
			$this->toUpdate = array();
		}

		if(!empty($this->toDelete)) {
			$pathMap = $this->owner->convertUrlsToPathMap(array_keys($this->toDelete));
			$this->owner->deleteRegularFiles(array_values($pathMap));
			$this->owner->deleteStaleFiles(array_values($pathMap));
			$this->toDelete = array();
		}
	}

	/**
	 * Converts the array of urls into an array of paths.
	 *
	 * This function is subsite-aware: these files could either sit in the top-level cache (no subsites),
	 * or sit in the subdirectories (main site and subsites).
	 *
	 * See BuildStaticCacheFromQueue::createCachedFiles for similar subsite-specific conditional handling.
	 *
	 * @TODO: this function tries to interface with dowstream FilesystemPublisher::urlsToPaths and fool it
	 * to work with Subsites. Ideally these two would be merged together to reduce complexity.
	 *
	 * @param array $urls An array of URLs to generate paths for.
	 *
	 * @returns array Map of url => path
	 */
	public function convertUrlsToPathMap($urls) {

		// Inject static objects.
		$director = Injector::inst()->get('Director');

		$pathMap = array();
		foreach($urls as $url) {
			$obj = $this->owner->getUrlArrayObject()->getObject($url);

			if (!$obj || !$obj->hasExtension('SiteTreeSubsites')) {
				// Normal processing for files directly in the cache folder.
				$pathMap = array_merge($pathMap, $this->owner->urlsToPaths(array($url)));

			} else {
				// Subsites support detected: figure out all files to delete in subdirectories.

				Config::inst()->nest();

				// Subsite page requested. Change behaviour to publish into directory.
				Config::inst()->update('FilesystemPublisher', 'domain_based_caching', true);

				// Pop the base-url segment from the url.
				if (strpos($url, '/')===0) {
					$cleanUrl = $director::makeRelative($url);
				} else {
					$cleanUrl = $director::makeRelative('/' . $url);
				}

				$subsite = $obj->Subsite();
				if (!$subsite || !$subsite->ID) {
					// Main site page - but publishing into subdirectory.
					$staticBaseUrl = Config::inst()->get('FilesystemPublisher', 'static_base_url');
					$pathMap = array_merge($pathMap, $this->owner->urlsToPaths(array($staticBaseUrl . '/' . $cleanUrl)));
				} else {
					// Subsite page. Generate all domain variants registered with the subsite.
					foreach($subsite->Domains() as $domain) {
						$pathMap = array_merge($pathMap, $this->owner->urlsToPaths(
							array('http://'.$domain->Domain . $director::baseURL() . $cleanUrl)
						));
					}
				}

				Config::inst()->unnest();
			}

		}

		return $pathMap;

	}

	/**
	 * Remove the stale variants of the cache files.
	 *
	 * @param array $paths List of paths relative to the cache root.
	 */
	public function deleteStaleFiles($paths) {
		foreach($paths as $path) {
			// Delete the "stale" file.
			$lastDot = strrpos($path, '.'); //find last dot
			if ($lastDot !== false) {
				$stalePath = substr($path, 0, $lastDot) . '.stale' . substr($path, $lastDot);
				$this->owner->deleteFromCacheDir($stalePath);
			}
		}
	}

	/**
	 * Remove the cache files.
	 *
	 * @param array $paths List of paths relative to the cache root.
	 */
	public function deleteRegularFiles($paths) {
		foreach($paths as $path) {
			$this->owner->deleteFromCacheDir($path);
		}
	}

	/**
	 * Helper method for deleting existing files in the cache directory.
	 *
	 * @param string $path Path relative to the cache root.
	 */
	public function deleteFromCacheDir($path) {
		$cacheBaseDir = $this->owner->getDestDir();
		if (file_exists($cacheBaseDir.'/'.$path)) {
			@unlink($cacheBaseDir.'/'.$path);
		}
	}


}
