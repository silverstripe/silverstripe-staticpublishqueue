<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Subsites\State\SubsiteState;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class RunDeleteCacheJobTest extends FunctionalTest
{
    public function testHydrationAffectsSignature()
    {
        $job = new DeleteStaticCacheJob();
        $data = $job->getJobData();
        $signature = $job->getSignature();
        if (class_exists(SubsiteState::class)) {
            // The subsite ID is added via `AbstractQueuedJob::getJobData()`
            // Without accounting for that, this test fails in the kitchen sink.
            $expected = ['SubsiteID' => SubsiteState::singleton()->getSubsiteId()];
            $this->assertSame($expected, json_decode(json_encode($data->jobData), true));
        } else {
            $this->assertEmpty($data->jobData);
        }
        $this->assertFalse($data->isComplete);

        $job->hydrate(['/' => 1], null);
        $data = $job->getJobData();
        $this->assertIsObject($data->jobData);
        $this->assertFalse($data->isComplete);
        $hydratedSignature = $job->getSignature();

        // hydrating the job should affect the signature
        $this->assertNotEquals($signature, $hydratedSignature);
    }

    // test that the job can process regardless of URLsToProcess
    public function testJobCanComplete()
    {
        $job = new DeleteStaticCacheJob();
        $job->process();
        $data = $job->getJobData();
        $this->assertTrue($data->isComplete);

        $job = new DeleteStaticCacheJob();
        $job->hydrate(['/' => 1], null);
        $job->process();
        $data = $job->getJobData();
        $this->assertTrue($data->isComplete);
    }
}
