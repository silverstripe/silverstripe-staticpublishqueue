<?php

namespace SilverStripe\StaticPublishQueue\Test;

use ReflectionException;
use ReflectionMethod;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Job\DeleteStaticCacheJob;
use SilverStripe\StaticPublishQueue\Job\GenerateStaticCacheJob;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Class JobTest
 *
 * @package SilverStripe\StaticPublishQueue\Test
 */
class JobTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    public function testJobsFromDataDefault(): void
    {
        $urls = [
            'http://some-locale/some-page/',
            'http://some-locale/some-other-page/',
        ];

        GenerateStaticCacheJob::singleton()->queueJobsFromData($urls);

        $jobs = QueuedJobDescriptor::get()->filter(['Implementation' => GenerateStaticCacheJob::class]);
        $this->assertCount(1, $jobs);

        /** @var QueuedJobDescriptor $jobDescriptor */
        $jobDescriptor = $jobs->first();
        $savedJobData = unserialize($jobDescriptor->SavedJobData);

        $this->assertEquals([
            'http://some-locale/some-page/' => 0,
            'http://some-locale/some-other-page/' => 1,

        ], $savedJobData->URLsToProcess);
    }

    /**
     * @param string $jobClass
     * @dataProvider jobClasses
     */
    public function testJobsFromDataJobClass(string $jobClass): void
    {
        $urls = [
            'http://some-locale/some-page/',
            'http://some-locale/some-other-page/',
        ];

        GenerateStaticCacheJob::singleton()->queueJobsFromData($urls, '', null, $jobClass);

        $jobs = QueuedJobDescriptor::get()->filter(['Implementation' => $jobClass]);
        $this->assertCount(1, $jobs);
    }

    public function testJobsFromDataMessage(): void
    {
        $urls = [
            'http://some-locale/some-page/',
            'http://some-locale/some-other-page/',
        ];

        $message = 'test message';

        GenerateStaticCacheJob::singleton()->queueJobsFromData($urls, $message);

        $jobs = QueuedJobDescriptor::get()->filter(['Implementation' => GenerateStaticCacheJob::class]);
        $this->assertCount(1, $jobs);

        /** @var QueuedJobDescriptor $jobDescriptor */
        $jobDescriptor = $jobs->first();
        $savedJobMessages = unserialize($jobDescriptor->SavedJobMessages);
        $this->assertCount(1, $savedJobMessages);

        $messageData = array_shift($savedJobMessages);
        $this->assertContains($message, $messageData);
    }

    public function testJobsFromDataExplicitUrlsPerJob(): void
    {
        $urls = [
            'http://some-locale/some-page/',
            'http://some-locale/some-other-page/',
        ];

        GenerateStaticCacheJob::singleton()->queueJobsFromData($urls, '', 1);

        $jobs = QueuedJobDescriptor::get()->filter(['Implementation' => GenerateStaticCacheJob::class]);
        $this->assertCount(2, $jobs);
    }

    public function testJobsFromDataImplicitUrlsPerJob(): void
    {
        Config::modify()->set(GenerateStaticCacheJob::class, 'urls_per_job', 1);
        $urls = [
            'http://some-locale/some-page/',
            'http://some-locale/some-other-page/',
        ];

        GenerateStaticCacheJob::singleton()->queueJobsFromData($urls);

        $jobs = QueuedJobDescriptor::get()->filter(['Implementation' => GenerateStaticCacheJob::class]);
        $this->assertCount(2, $jobs);
    }

    public function testJobsFromDataQueueCallback(): void
    {
        $urls = [
            'http://some-locale/some-page/',
            'http://some-locale/some-other-page/',
        ];

        $jobs = GenerateStaticCacheJob::singleton()->createJobsFromData($urls);

        $this->assertCount(1, $jobs);

        $job = array_shift($jobs);
        $this->assertInstanceOf(GenerateStaticCacheJob::class, $job);

        $this->assertCount(
            0,
            QueuedJobDescriptor::get()->filter(['Implementation' => GenerateStaticCacheJob::class])
        );
    }

    /**
     * @param string $jobClass
     * @param int $urlsPerJob
     * @throws ReflectionException
     * @dataProvider urlsPerJobCases
     */
    public function testUrlsPerJob(string $jobClass, int $urlsPerJob): void
    {
        Config::modify()->set($jobClass, 'urls_per_job', $urlsPerJob);

        /** @var Job $job */
        $job = singleton($jobClass);

        $method = new ReflectionMethod(Job::class, 'getUrlsPerJob');
        $method->setAccessible(true);
        $this->assertEquals($urlsPerJob, $method->invoke($job));
    }

    /**
     * @param string $jobClass
     * @param int $chunkSize
     * @throws ReflectionException
     * @dataProvider chunkCases
     */
    public function testChunkSize(string $jobClass, int $chunkSize): void
    {
        Config::modify()->set($jobClass, 'chunk_size', $chunkSize);

        /** @var Job $job */
        $job = singleton($jobClass);

        $method = new ReflectionMethod(Job::class, 'getChunkSize');
        $method->setAccessible(true);
        $this->assertEquals($chunkSize, $method->invoke($job));
    }

    /**
     * @return array
     */
    public function jobClasses(): array
    {
        return [
            [GenerateStaticCacheJob::class],
            [DeleteStaticCacheJob::class],
        ];
    }

    /**
     * @return array
     */
    public function urlsPerJobCases(): array
    {
        return [
            [
                GenerateStaticCacheJob::class,
                8,
            ],
            [
                DeleteStaticCacheJob::class,
                9,
            ],
        ];
    }

    /**
     * @return array
     */
    public function chunkCases(): array
    {
        return [
            [
                GenerateStaticCacheJob::class,
                10,
            ],
            [
                DeleteStaticCacheJob::class,
                15,
            ],
        ];
    }
}
