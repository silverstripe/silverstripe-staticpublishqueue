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
	 * Queues the urls to be flushed into the queue.
	 */
	private $toUpdate = array();

	/**
	 * Queues the urls to be deleted as part of a next flush operation.
	 */
	private $toDelete = array();

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
		$urlArrayObject = Injector::inst()->get('URLArrayObject');

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
						$urlArrayObject::add_objects($urls, $object)
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
						$urlArrayObject::add_objects($urls, $object)
					);
				}
			}

		}

	}

	/**
	 * Execute URL deletions, enqueue URL updates.
	 */
	public function flushChanges() {
		$urlArrayObject = Injector::inst()->get('URLArrayObject');

		if(!empty($this->toUpdate)) {
			$urlArrayObject::add_urls($this->toUpdate);
			$this->toUpdate = array();
		}

		if(!empty($this->toDelete)) {
			$this->owner->unpublishPagesAndStaleCopies($this->toDelete);
			$this->toDelete = array();
		}
	}

	/**
	 * Removes the unpublished page's static cache file as well as its 'stale.html' copy.
	 * Copied from: FilesystemPublisher->unpublishPages($urls)
	 *
	 * TODO: doesn't work for subsites - does not respect domain_based_caching.
	 *
	 * @param $urls array associative array of url => priority
	 */
	public function unpublishPagesAndStaleCopies($urls) {
		$urls = $this->owner->urlsToPaths(array_keys($urls));

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

}
