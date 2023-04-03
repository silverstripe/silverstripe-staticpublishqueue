<?php

namespace SilverStripe\StaticPublishQueue\Extension\Engine;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Resettable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;
use SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Job\DeleteStaticCacheJob;
use SilverStripe\StaticPublishQueue\Job\GenerateStaticCacheJob;
use SilverStripe\StaticPublishQueue\Service\UrlBundleInterface;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * This extension couples to the StaticallyPublishable and StaticPublishingTrigger implementations
 * on the SiteTree objects and makes sure the actual change to SiteTree is triggered/enqueued.
 *
 * Provides the following information as a context to StaticPublishingTrigger:
 * * action - name of the executed action: publish or unpublish
 *
 * @see PublishableSiteTree
 * @method SiteTree|PublishableSiteTree|$this getOwner()
 */
class SiteTreePublishingEngine extends SiteTreeExtension implements Resettable
{
    public const ACTION_PUBLISH = 'publish';
    public const ACTION_UNPUBLISH = 'unpublish';

    /**
     * Queued job service injection property
     * Used for unit tests only to cover edge cases where Injector doesn't cover
     */
    protected static ?QueuedJobService $queueService = null;

    /**
     * Queues the urls to be flushed into the queue.
     */
    private array|SS_List $toUpdate = [];

    /**
     * Queues the urls to be deleted as part of a next flush operation.
     */
    private array|SS_List $toDelete = [];

    public static function reset(): void
    {
        static::$queueService = null;
    }

    /**
     * Force inject queue service
     * Used for unit tests only to cover edge cases where Injector doesn't cover
     *
     *
     * @param QueuedJobService $service
     */
    public static function setQueueService(QueuedJobService $service): void
    {
        static::$queueService = $service;
    }

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
     * @param array $toDelete
     * @return $this
     */
    public function setToDelete($toDelete)
    {
        $this->toDelete = $toDelete;

        return $this;
    }

    /**
     * @param SiteTree|SiteTreePublishingEngine|null $original
     */
    public function onBeforePublishRecursive($original)
    {
        // There is no original object. This might be the first time it has been published
        if (!$original?->exists()) {
            return;
        }

        // We want to find out if the URL for this page has changed at all. That can happen 2 ways: Either the page is
        // moved in the SiteTree (ParentID changes), or the URLSegment is updated
        // Apparently ParentID can sometimes be string, so make sure we cast to (int) for our comparison
        if ((int) $original->ParentID !== (int) $this->getOwner()->ParentID
            || $original->URLSegment !== $this->getOwner()->URLSegment
        ) {
            // We have detected a change to the URL. We need to purge the old URLs for this page and any children
            $context = [
                'action' => self::ACTION_UNPUBLISH,
            ];
            $original->collectChanges($context);
            // We need to flushChanges() immediately so that Jobs are queued for the Pages while they still have their
            // old URLs
            $original->flushChanges();
        }
    }

    /**
     * @param SiteTree|SiteTreePublishingEngine|null $original
     */
    public function onAfterPublishRecursive($original)
    {
        $parentId = $original->ParentID ?? null;
        $urlSegment = $original->URLSegment ?? null;

        // Apparently ParentID can sometimes be string, so make sure we cast to (int) for our comparison
        $parentChanged = $parentId && (int) $parentId !== (int) $this->getOwner()->ParentID;
        $urlChanged = $urlSegment && $original->URLSegment !== $this->getOwner()->URLSegment;

        $context = [
            'action' => self::ACTION_PUBLISH,
            // If a URL change has been detected, then we need to force the recursive regeneration of all child
            // pages
            'urlChanged' => $parentChanged || $urlChanged,
        ];

        $this->collectChanges($context);
        $this->flushChanges();
    }

    public function onBeforeUnpublish()
    {
        $context = [
            'action' => self::ACTION_UNPUBLISH,
        ];
        $this->collectChanges($context);
        $this->flushChanges();
    }

    /**
     * Collect all changes for the given context.
     */
    public function collectChanges(array $context): void
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        Versioned::withVersionedMode(function () use ($context) {
            // Collection of changes needs to happen within the context of our Published/LIVE state
            Versioned::set_stage(Versioned::LIVE);

            // Re-fetch our page, now within a LIVE context
            $page = DataObject::get($this->getOwner()->ClassName)->byID($this->getOwner()->ID);

            // This page isn't LIVE/Published, so there is nothing for us to do here
            if (!$page?->exists()) {
                return;
            }

            // The page does not include the required extension, and it doesn't implement a Trigger
            if (!$page->hasExtension(PublishableSiteTree::class) && !$page instanceof StaticPublishingTrigger) {
                return;
            }

            $toUpdate = $page->objectsToUpdate($context);
            $this->setToUpdate($toUpdate);

            $toDelete = $page->objectsToDelete($context);
            $this->setToDelete($toDelete);
        });
    }

    /**
     * Execute URL deletions, enqueue URL updates.
     */
    public function flushChanges()
    {
        $queueService = static::$queueService ?? QueuedJobService::singleton();

        if ($this->toUpdate) {
            /** @var UrlBundleInterface $urlService */
            $urlService = Injector::inst()->create(UrlBundleInterface::class);

            foreach ($this->toUpdate as $item) {
                $urls = $item->urlsToCache();
                ksort($urls);
                $urls = array_keys($urls);
                $urlService->addUrls($urls);
            }

            $jobs = $urlService->getJobsForUrls(GenerateStaticCacheJob::class, 'Building URLs', $this->owner);

            foreach ($jobs as $job) {
                $queueService->queueJob($job);
            }

            $this->toUpdate = [];
        }

        if ($this->toDelete) {
            /** @var UrlBundleInterface $urlService */
            $urlService = Injector::inst()->create(UrlBundleInterface::class);

            foreach ($this->toDelete as $item) {
                $urls = $item->urlsToCache();
                ksort($urls);
                $urls = array_keys($urls);
                $urlService->addUrls($urls);
            }

            $jobs = $urlService->getJobsForUrls(DeleteStaticCacheJob::class, 'Purging URLs', $this->owner);

            foreach ($jobs as $job) {
                $queueService->queueJob($job);
            }

            $this->toDelete = [];
        }
    }
}
