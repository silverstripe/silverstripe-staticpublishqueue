<?php

namespace SilverStripe\StaticPublishQueue\Dev;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use SilverStripe\StaticPublishQueue\Extension\Engine\SiteTreePublishingEngine;
use SilverStripe\StaticPublishQueue\Test\QueuedJobsTestService;
use Symbiote\QueuedJobs\Services\QueuedJobHandler;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\QueuedJobsTest_Handler;

class StaticPublisherState implements TestState
{
    public function setUp(SapphireTest $test)
    {
        // Prepare queued jobs related functionality for unit test run
        // Disable queue logging
        Injector::inst()->registerService(new QueuedJobsTest_Handler(), QueuedJobHandler::class);

        // Disable special actions of the queue service
        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);

        // It seems Injector doesn't cover all cases so we force-inject a test service which is suitable for unit tests
        Injector::inst()->registerService(new QueuedJobsTestService(), QueuedJobService::class);
        SiteTreePublishingEngine::setQueueService(QueuedJobService::singleton());
    }

    public function tearDown(SapphireTest $test)
    {
    }

    public function setUpOnce($class)
    {
    }

    public function tearDownOnce($class)
    {
    }
}
