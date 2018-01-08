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

        if ($this->getOwner()->hasExtension(PublishableSiteTree::class)
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

                $jobData = new \stdClass();
                $jobData->URLsToProcess = $job->findAffectedURLs();

                $job->setJobData(0, 0, false, $jobData, []);

                $queue->queueJob($job);
            }
            $this->toDelete = array();
        }
    }
}
