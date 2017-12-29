<?php

namespace SilverStripe\StaticPublishQueue\Extension\Engine;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Job\DeleteStaticCacheJob;
use SilverStripe\StaticPublishQueue\Job\GenerateStaticCacheJob;
use SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * This extension couples to the StaticallyPublishable and StaticPublishingTrigger implementations
 * on the SiteTree objects and makes sure the actual change to SiteTree is triggered/enqueued.
 *
 * Provides the following information as a context to StaticPublishingTrigger:
 * * action - name of the executed action: publish or unpublish
 *
 * @see PublishableSiteTree
 */
class SiteTreePublishingEngine extends DataExtension
{
    /**
     * Queues the urls to be flushed into the queue.
     *
     * @var array
     */
    private $toUpdate = array();

    /**
     * Queues the urls to be deleted as part of a next flush operation.
     *
     * @var array
     */
    private $toDelete = array();

    /**
     * @return array
     */
    public function getToUpdate()
    {
        return $this->toUpdate;
    }

    /**
     * @return array
     */
    public function getToDelete()
    {
        return $this->toDelete;
    }

    /**
     * @param array $toUpdate
     * @return $this
     */
    public function setToUpdate($toUpdate)
    {
        $this->toUpdate = $toUpdate;
        return $this;
    }

    /**
     * @param $toDelete
     * @return $this
     */
    public function setToDelete($toDelete)
    {
        $this->toDelete = $toDelete;
        return $this;
    }

    public function onAfterPublish()
    {
        $context = array(
            'action' => 'publish'
        );
        $this->collectChanges($context);
        $this->flushChanges();
    }

    public function onBeforeUnpublish()
    {
        $context = array(
            'action' => 'unpublish'
        );
        $this->collectChanges($context);
    }

    public function onAfterUnpublish()
    {
        $this->flushChanges();
    }

    /**
     * Collect all changes for the given context.
     */
    public function collectChanges($context)
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        if (
            $this->getOwner()->hasExtension(PublishableSiteTree::class)
            || $this->getOwner() instanceof StaticPublishingTrigger
        ) {
            $toUpdate = $this->getOwner()->objectsToUpdate($context);
            $this->setToUpdate($toUpdate);

            $toDelete = $this->getOwner()->objectsToDelete($context);
            $this->setToDelete($toDelete);
        }
    }

    /**
     * Execute URL deletions, enqueue URL updates.
     */
    public function flushChanges()
    {
        $queue = QueuedJobService::singleton();
        if (!empty($this->toUpdate)) {
            foreach ($this->toUpdate as $queueItem) {
                $job = new GenerateStaticCacheJob();
                $job->setObject($queueItem);
                $queue->queueJob($job);
            }
            $this->toUpdate = array();
        }

        if (!empty($this->toDelete)) {
            foreach ($this->toDelete as $queueItem) {
                $job = new DeleteStaticCacheJob();
                $job->setObject($queueItem);
                $queue->queueJob($job);
            }
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
     * @param array $queue An array of URLs to generate paths for.
     *
     * @returns StaticPagesQueue[] Map of url => path
     */
    public function convertUrlsToPathMap($queue)
    {

        // Inject static objects.
        $director = Injector::inst()->get(Director::class);

        $pathMap = array();
        foreach ($queue as $item) {
            /** @var DataObject $obj */
            $obj = $item->CacheObject();
            if (!$obj || !$obj->exists() || !$obj->hasExtension('SiteTreeSubsites')) {
                // Normal processing for files directly in the cache folder.
                $pathMap = array_merge($pathMap, $this->getOwner()->urlsToPaths(array($item->URLSegment)));
            } else {
                // Subsites support detected: figure out all files to delete in subdirectories.

                Config::nest();

                // Subsite page requested. Change behaviour to publish into directory.
                // @todo - avoid modifying config at run time
                Config::modify()->set(FilesystemPublisher::class, 'domain_based_caching', true);

                // Pop the base-url segment from the url.
                if (strpos($item->URLSegment, '/') === 0) {
                    $cleanUrl = $director::makeRelative($item->URLSegment);
                } else {
                    $cleanUrl = $director::makeRelative('/' . $item->URLSegment);
                }

                // @todo all subsite integration
                $subsite = $obj->Subsite();
                if (!$subsite || !$subsite->ID) {
                    // Main site page - but publishing into subdirectory.
                    $staticBaseUrl = Config::inst()->get(FilesystemPublisher::class, 'static_base_url');
                    $pathMap = array_merge(
                        $pathMap,
                        $this->owner->urlsToPaths(array($staticBaseUrl . '/' . $cleanUrl))
                    );
                } else {
                    // Subsite page. Generate all domain variants registered with the subsite.
                    foreach ($subsite->Domains() as $domain) {
                        $pathMap = array_merge(
                            $pathMap,
                            $this->owner->urlsToPaths(
                                array('http://' . $domain->Domain . $director::baseURL() . $cleanUrl)
                            )
                        );
                    }
                }

                Config::unnest();
            }
        }

        return $pathMap;
    }

    /**
     * Remove the stale variants of the cache files.
     *
     * @param array $paths List of paths relative to the cache root.
     */
    public function deleteStaleFiles($paths)
    {
        foreach ($paths as $path) {
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
    public function deleteRegularFiles($paths)
    {
        foreach ($paths as $path) {
            $this->owner->deleteFromCacheDir($path);
        }
    }

    /**
     * Helper method for deleting existing files in the cache directory.
     *
     * @param string $path Path relative to the cache root.
     */
    public function deleteFromCacheDir($path)
    {
        $cacheBaseDir = $this->owner->getDestDir();
        if (file_exists($cacheBaseDir . '/' . $path)) {
            @unlink($cacheBaseDir . '/' . $path);
        }
    }
}
