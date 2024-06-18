<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Core\Resettable;
use SilverStripe\Dev\TestOnly;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class QueuedJobsTestService extends QueuedJobService implements Resettable, TestOnly
{
    private $jobs = [];

    public function flushJobs(): void
    {
        $this->jobs = [];
    }

    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Keep the jobs in-memory for unit test purposes (avoid writes and related actions)
     *
     * @param QueuedJob $job
     * @param null $startAfter
     * @param null $userId
     * @param null $queueName
     * @return int
     */
    public function queueJob(QueuedJob $job, $startAfter = null, $userId = null, $queueName = null)
    {
        $this->jobs[] = $job;

        return 1;
    }

    public static function reset()
    {
        QueuedJobsTestService::singleton()->flushJobs();
    }

    /**
     * Skip shutdown function actions
     */
    public function onShutdown()
    {
    }
}
