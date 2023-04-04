<?php

namespace SilverStripe\StaticPublishQueue\Test\Extension\Engine;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Extension\Engine\SiteTreePublishingEngine;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Job\DeleteStaticCacheJob;
use SilverStripe\StaticPublishQueue\Job\GenerateStaticCacheJob;
use SilverStripe\StaticPublishQueue\Test\QueuedJobsTestService;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobHandler;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\QueuedJobsTest_Handler;

class SiteTreePublishingEngineTest extends SapphireTest
{
    protected static $fixture_file = 'SiteTreePublishingEngineTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [
            PublishableSiteTree::class,
            SiteTreePublishingEngine::class,
        ],
    ];

    public function testPublishRecursive(): void
    {
        // Inclusion of parent/child is tested in PublishableSiteTreeTest
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_NONE);
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_NONE);

        /** @var QueuedJobsTestService $service */
        $service = QueuedJobService::singleton();

        $page = $this->objFromFixture(SiteTree::class, 'page4');
        $page->publishRecursive();

        $jobs = $service->getJobs();

        // We should only have 1 job queued
        $this->assertCount(1, $jobs);

        // Let's grab the job and inspect the contents
        /** @var GenerateStaticCacheJob $updateJob */
        $updateJob = $this->getJobByClassName($jobs, GenerateStaticCacheJob::class);

        $expectedUrls = [
            'http://example.com/page-1/page-2/page-4',
        ];
        $resultUrls = array_keys($updateJob->getJobData()->jobData->URLsToProcess);

        $this->assertInstanceOf(GenerateStaticCacheJob::class, $updateJob);
        $this->assertEqualsCanonicalizing($expectedUrls, $resultUrls);
    }

    public function testPublishRecursiveUrlChange(): void
    {
        // Inclusion of parent/child is tested in PublishableSiteTreeTest
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_NONE);
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_NONE);

        /** @var QueuedJobsTestService $service */
        $service = Injector::inst()->get(QueuedJobService::class);

        $page = $this->objFromFixture(SiteTree::class, 'page4');
        $page->URLSegment = 'page-4-v2';
        $page->write();
        $page->publishRecursive();

        $jobs = $service->getJobs();

        // There should be 2 jobs queued, one to clear old caches, and one to generate new caches
        $this->assertCount(2, $jobs);

        // Grab our two expected jobs
        /** @var GenerateStaticCacheJob $updateJob */
        $updateJob = $this->getJobByClassName($jobs, GenerateStaticCacheJob::class);
        /** @var DeleteStaticCacheJob $deleteJob */
        $deleteJob = $this->getJobByClassName($jobs, DeleteStaticCacheJob::class);

        // Set up our expected URLs
        $expectedUpdateUrls = [
            'http://example.com/page-1/page-2/page-4-v2',
            'http://example.com/page-1/page-2/page-4-v2/page-5',
            'http://example.com/page-1/page-2/page-4-v2/page-5/page-6',
        ];
        $expectedPurgeUrls = [
            'http://example.com/page-1/page-2/page-4',
            'http://example.com/page-1/page-2/page-4/page-5',
            'http://example.com/page-1/page-2/page-4/page-5/page-6',
        ];

        $resultUpdateUrls = array_keys($updateJob->getJobData()->jobData->URLsToProcess);
        $resultPurgeUrls = array_keys($deleteJob->getJobData()->jobData->URLsToProcess);

        // Test our update job
        $this->assertInstanceOf(GenerateStaticCacheJob::class, $updateJob);
        $this->assertEqualsCanonicalizing($expectedUpdateUrls, $resultUpdateUrls);
        // Test our delete job
        $this->assertInstanceOf(DeleteStaticCacheJob::class, $deleteJob);
        $this->assertEqualsCanonicalizing($expectedPurgeUrls, $resultPurgeUrls);
    }

    public function testPublishRecursiveParentChange(): void
    {
        // Inclusion of parent/child is tested in PublishableSiteTreeTest
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_NONE);
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_NONE);

        /** @var QueuedJobsTestService $service */
        $service = Injector::inst()->get(QueuedJobService::class);

        $page = $this->objFromFixture(SiteTree::class, 'page4');
        $newParentPage = $this->objFromFixture(SiteTree::class, 'page3');

        $page->ParentID = $newParentPage->ID;
        $page->write();
        $page->publishRecursive();

        $jobs = $service->getJobs();

        // There should be 2 jobs queued, one to clear old caches, and one to generate new caches
        $this->assertCount(2, $jobs);

        // Grab our two expected jobs
        /** @var GenerateStaticCacheJob $updateJob */
        $updateJob = $this->getJobByClassName($jobs, GenerateStaticCacheJob::class);
        /** @var DeleteStaticCacheJob $deleteJob */
        $deleteJob = $this->getJobByClassName($jobs, DeleteStaticCacheJob::class);

        // Set up our expected URLs
        $expectedUpdateUrls = [
            'http://example.com/page-1/page-3/page-4',
            'http://example.com/page-1/page-3/page-4/page-5',
            'http://example.com/page-1/page-3/page-4/page-5/page-6',
        ];
        $expectedPurgeUrls = [
            'http://example.com/page-1/page-2/page-4',
            'http://example.com/page-1/page-2/page-4/page-5',
            'http://example.com/page-1/page-2/page-4/page-5/page-6',
        ];

        $resultUpdateUrls = array_keys($updateJob->getJobData()->jobData->URLsToProcess);
        $resultPurgeUrls = array_keys($deleteJob->getJobData()->jobData->URLsToProcess);

        // Test our update job
        $this->assertInstanceOf(GenerateStaticCacheJob::class, $updateJob);
        $this->assertEqualsCanonicalizing($expectedUpdateUrls, $resultUpdateUrls);
        // Test our delete job
        $this->assertInstanceOf(DeleteStaticCacheJob::class, $deleteJob);
        $this->assertEqualsCanonicalizing($expectedPurgeUrls, $resultPurgeUrls);
    }

    public function testDoUnpublish(): void
    {
        // Inclusion of parent/child is tested in PublishableSiteTreeTest
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_NONE);
        // Because this is an unpublish, we'll expect all the children to be present as well (regardless of config)
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_NONE);

        /** @var QueuedJobsTestService $service */
        $service = Injector::inst()->get(QueuedJobService::class);

        $page = $this->objFromFixture(SiteTree::class, 'page4');
        $page->doUnpublish();

        $jobs = $service->getJobs();

        // We should only have 1 job queued
        $this->assertCount(1, $jobs);

        // Let's grab the job and inspect the contents
        /** @var DeleteStaticCacheJob $updateJob */
        $deleteJob = $this->getJobByClassName($jobs, DeleteStaticCacheJob::class);

        $expectedUrls = [
            'http://example.com/page-1/page-2/page-4',
            'http://example.com/page-1/page-2/page-4/page-5',
            'http://example.com/page-1/page-2/page-4/page-5/page-6',
        ];

        $resultUrls = array_keys($deleteJob->getJobData()->jobData->URLsToProcess);

        $this->assertInstanceOf(DeleteStaticCacheJob::class, $deleteJob);
        $this->assertEqualsCanonicalizing($expectedUrls, $resultUrls);
    }

    protected function getJobByClassName(array $jobs, string $className): ?QueuedJob
    {
        foreach ($jobs as $job) {
            if ($job instanceof $className) {
                return $job;
            }
        }

        return null;
    }

    protected function setUp(): void
    {
        // Prepare queued jobs related functionality for unit test run
        // Disable queue logging
        Injector::inst()->registerService(new QueuedJobsTest_Handler(), QueuedJobHandler::class);

        // Disable special actions of the queue service
        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);

        // It seems Injector doesn't cover all cases so we force-inject a test service which is suitable for unit tests
        Injector::inst()->registerService(new QueuedJobsTestService(), QueuedJobService::class);
        SiteTreePublishingEngine::setQueueService(QueuedJobService::singleton());

        // Set up our base URL so that it's always consistent for our tests
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://example.com/');

        parent::setUp();

        $pages = [
            'page1',
            'page2',
            'page3',
            'page4',
            'page5',
            'page6',
        ];

        // Publish all of our pages before we start testing with them
        foreach ($pages as $pageId) {
            $page = $this->objFromFixture(SiteTree::class, $pageId);
            $page->publishRecursive();
        }

        /** @var QueuedJobsTestService $service */
        $service = Injector::inst()->get(QueuedJobService::class);
        // Remove any jobs that were queued as part of the publishing above
        $service->flushJobs();
    }
}
