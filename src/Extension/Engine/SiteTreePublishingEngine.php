<?php

namespace SilverStripe\StaticPublishQueue\Extension\Engine;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Resettable;
use SilverStripe\ORM\DataObject;
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
 *
 * @extends Extension<SiteTree>
 */
class SiteTreePublishingEngine extends Extension implements Resettable
{
    public const ACTION_PUBLISH = 'publish';
    public const ACTION_UNPUBLISH = 'unpublish';

    /**
     * Queued job service injection property
     * Used for unit tests only to cover edge cases where Injector doesn't cover
     *
     * @var QueuedJobService|null
     */
    protected static $queueService = null;

    /**
     * Queues the urls to be flushed into the queue.
     */
    private array $urlsToUpdate = [];

    /**
     * Queues the urls to be deleted as part of a next flush operation.
     */
    private array $urlsToDelete = [];

    public static function reset(): void
    {
        static::$queueService = null;
    }

    /**
     * Force inject queue service
     * Used for unit tests only to cover edge cases where Injector doesn't cover
     *
     * @param QueuedJobService $service
     */
    public static function setQueueService(QueuedJobService $service): void
    {
        static::$queueService = $service;
    }

    private function getUrlsToUpdate(): array
    {
        return $this->urlsToUpdate;
    }

    private function setUrlsToUpdate(array $urlsToUpdate): void
    {
        $this->urlsToUpdate = $urlsToUpdate;
    }

    private function getUrlsToDelete(): array
    {
        return $this->urlsToDelete;
    }

    private function setUrlsToDelete(array $urlsToDelete): void
    {
        $this->urlsToDelete = $urlsToDelete;
    }

    /**
     * @param SiteTree|SiteTreePublishingEngine|null $original
     */
    protected function onBeforePublishRecursive($original)
    {
        // There is no original object. This might be the first time it has been published
        if (!$original?->exists()) {
            return;
        }

        $owner = $this->getOwner();

        // We want to find out if the URL for this page has changed at all. That can happen 2 ways: Either the page is
        // moved in the SiteTree (ParentID changes), or the URLSegment is updated
        // Apparently ParentID can sometimes be string, so make sure we cast to (int) for our comparison
        if ((int) $original->ParentID !== (int) $owner->ParentID
            || $original->URLSegment !== $owner->URLSegment
        ) {
            // We have detected a change to the URL. We need to purge the old URLs for this page and any children
            $context = [
                'action' => SiteTreePublishingEngine::ACTION_UNPUBLISH,
            ];
            // We'll collect these changes now (before the URLs change), but they won't be actioned until the publish
            // action has completed successfully, and onAfterPublishRecursive() has been called. This is because we
            // don't want to queue jobs if the publish action fails
            $this->collectChanges($context);
        }
    }

    /**
     * @param SiteTree|SiteTreePublishingEngine|null $original
     */
    protected function onAfterPublishRecursive($original)
    {
        // Flush any/all changes that we might have collected from onBeforePublishRecursive()
        $this->flushChanges();

        $parentId = $original->ParentID ?? null;
        $urlSegment = $original->URLSegment ?? null;

        $owner = $this->getOwner();

        // Apparently ParentID can sometimes be string, so make sure we cast to (int) for our comparison
        $parentChanged = $parentId && (int) $parentId !== (int) $owner->ParentID;
        $urlSegmentChanged = $urlSegment && $original->URLSegment !== $owner->URLSegment;

        $context = [
            'action' => SiteTreePublishingEngine::ACTION_PUBLISH,
            // If a URL change has been detected, then we need to force the recursive regeneration of all child
            // pages
            'urlSegmentChanged' => $parentChanged || $urlSegmentChanged,
        ];

        // Collect any additional changes (noting that some could already have been added in onBeforePublishRecursive())
        $this->collectChanges($context);
        // Flush any/all changes that we have detected
        $this->flushChanges();
    }

    protected function onBeforeUnpublish()
    {
        $context = [
            'action' => SiteTreePublishingEngine::ACTION_UNPUBLISH,
        ];
        // We'll collect these changes now, but they won't be actioned until onAfterUnpublish()
        $this->collectChanges($context);
    }

    protected function onAfterUnpublish()
    {
        // Flush any/all changes that we have detected
        $this->flushChanges();
    }

    /**
     * Collect all changes for the given context.
     *
     * @param array $context
     * @return void
     */
    public function collectChanges($context)
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        Versioned::withVersionedMode(function () use ($context) {
            $action = $context['action'];
            // Collection of changes needs to happen within LIVE or DRAFT depending on the action context

            // Unpublish actions are called onBefore(), and their purpose is to remove the URLs of previously published
            // pages. As such, we need to find out what the URL was/is in the current LIVE state (before the unpublish()
            // completes)

            // Publish actions are called onAfter(), and they need to retrieve whatever the current URL is in DRAFT.
            // This is purely because if a page has an unpublished parent, then the LIVE URL will be incorrect (it will
            // be missing the parent slug) - we'd prefer to cache to correct URL (with parentage) even though it'll be
            // a cache of a 404
            Versioned::set_stage($action === SiteTreePublishingEngine::ACTION_UNPUBLISH ? Versioned::LIVE: Versioned::DRAFT);

            $owner = $this->getOwner();

            // Re-fetch our page, now within a LIVE context
            $siteTree = DataObject::get($owner->ClassName)->byID($owner->ID);

            // This page isn't LIVE/Published, so there is nothing for us to do here
            if (!$siteTree?->exists()) {
                return;
            }

            // The page does not include the required extension, and it doesn't implement a Trigger
            if (!$siteTree->hasExtension(StaticPublishingTrigger::class) && !($siteTree instanceof StaticPublishingTrigger)) {
                return;
            }

            // Fetch our URLs to be actioned
            $urlsToUpdate = $this->getUrlsFromObjects($siteTree->objectsToUpdate($context));
            $this->setUrlsToUpdate($urlsToUpdate);
            $urlsToUpdate = $this->getUrlsFromObjects($siteTree->objectsToDelete($context));
            $this->setUrlsToDelete($urlsToUpdate);
        });
    }

    /**
     * Execute URL deletions, enqueue URL updates.
     */
    public function flushChanges()
    {
        $queueService = static::$queueService ?? QueuedJobService::singleton();
        $owner = $this->getOwner();
        $urlsToUpdate = $this->getUrlsToUpdate();
        $urlsToDelete = $this->getUrlsToDelete();

        if ($urlsToUpdate) {
            $urlService = Injector::inst()->create(UrlBundleInterface::class);
            $urlService->addUrls($urlsToUpdate);

            $jobs = $urlService->getJobsForUrls(GenerateStaticCacheJob::class, 'Building URLs', $owner);

            foreach ($jobs as $job) {
                $queueService->queueJob($job);
            }

            $this->setUrlsToUpdate([]);
        }

        if ($urlsToDelete) {
            $urlService = Injector::inst()->create(UrlBundleInterface::class);
            $urlService->addUrls($urlsToDelete);

            $jobs = $urlService->getJobsForUrls(DeleteStaticCacheJob::class, 'Purging URLs', $owner);

            foreach ($jobs as $job) {
                $queueService->queueJob($job);
            }

            $this->setUrlsToDelete([]);
        }
    }

    private function getUrlsFromObjects(iterable $objects): iterable
    {
        $urls = [];
        foreach ($objects as $object) {
            $urls = array_merge($urls, array_keys($object->urlsToCache()));
        }
        return $urls;
    }
}
