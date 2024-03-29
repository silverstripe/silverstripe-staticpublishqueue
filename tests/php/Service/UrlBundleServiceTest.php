<?php

namespace SilverStripe\StaticPublishQueue\Test\Service;

use ReflectionMethod;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Job\DeleteStaticCacheJob;
use SilverStripe\StaticPublishQueue\Job\GenerateStaticCacheJob;
use SilverStripe\StaticPublishQueue\Service\UrlBundleService;

class UrlBundleServiceTest extends SapphireTest
{
    /**
     * @dataProvider jobClasses
     */
    public function testJobsFromDataDefault(string $jobClass): void
    {
        $urls = [
            'http://some-locale/some-page/',
            'http://some-locale/some-other-page/',
        ];
        $message = 'Test message';

        $service = UrlBundleService::create();
        $service->addUrls($urls);
        $jobs = $service->getJobsForUrls($jobClass, $message);

        $this->assertCount(1, $jobs);

        /** @var Job $job */
        $job = array_shift($jobs);

        $this->assertEquals([
            'http://some-locale/some-page/' => 0,
            'http://some-locale/some-other-page/' => 1,

        ], $job->URLsToProcess);

        $messages = $job->getJobData()->messages;
        $this->assertCount(1, $messages);

        $messageData = array_shift($messages);
        $this->assertStringContainsString($message, $messageData);
    }

    /**
     * @dataProvider jobClasses
     */
    public function testJobsFromDataExplicitUrlsPerJob(string $jobClass): void
    {
        Config::modify()->set($jobClass, 'urls_per_job', 1);
        $urls = [
            'http://some-locale/some-page/',
            'http://some-locale/some-other-page/',
        ];

        $service = UrlBundleService::singleton();
        $service->addUrls($urls);
        $jobs = $service->getJobsForUrls($jobClass);

        $this->assertCount(2, $jobs);
    }

    /**
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

    public function jobClasses(): array
    {
        return [
            [GenerateStaticCacheJob::class],
            [DeleteStaticCacheJob::class],
        ];
    }

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

    public function testGetUrls(): void
    {
        UrlBundleService::config()->set('strip_stage_param', true);

        $urls = [
            'http://www.test.com?stage=Stage',
            'https://www.test.com?test1=1&stage=Live&test2=2'
        ];
        $expectedUrls = [
            'http://www.test.com',
            'https://www.test.com?test1=1&test2=2',
        ];

        $urlService = UrlBundleService::create();
        $urlService->addUrls($urls);
        $method = new ReflectionMethod($urlService, 'getUrls');
        $method->setAccessible(true);
        $resultUrls = $method->invoke($urlService);

        $this->assertEqualsCanonicalizing($expectedUrls, $resultUrls);
    }

    public function testGetUrlsDontStripStage(): void
    {
        UrlBundleService::config()->set('strip_stage_param', false);

        $urls = [
            'http://www.test.com?stage=Stage',
            'https://www.test.com?test1=1&stage=Live&test2=2'
        ];

        $urlService = UrlBundleService::create();
        $urlService->addUrls($urls);
        $method = new ReflectionMethod($urlService, 'getUrls');
        $method->setAccessible(true);
        $resultUrls = $method->invoke($urlService);

        $this->assertEqualsCanonicalizing($urls, $resultUrls);
    }

    /**
     * @dataProvider provideStripStageParamUrls
     */
    public function testStripStageParam(string $url, string $expectedUrl): void
    {
        UrlBundleService::config()->set('strip_stage_param', true);

        $urlService = UrlBundleService::create();
        $method = new ReflectionMethod($urlService, 'stripStageParam');
        $method->setAccessible(true);

        $this->assertEquals($expectedUrl, $method->invoke($urlService, $url));
    }

    public function provideStripStageParamUrls(): array
    {
        return [
            // Testing removal of stage=Stage, expect http to remain http
            [
                'http://www.test.com?stage=Stage',
                'http://www.test.com',
            ],
            // Testing removal of stage=Live, expect https to remain https
            [
                'https://www.test.com?stage=Live',
                'https://www.test.com',
            ],
            // Testing removal of stage=Stage with other params
            [
                'https://www.test.com?test1=1&stage=Stage&test2=2',
                'https://www.test.com?test1=1&test2=2',
            ],
            // Testing removal of stage=Live with other params
            [
                'https://www.test.com?test1=1&stage=Live&test2=2',
                'https://www.test.com?test1=1&test2=2',
            ],
        ];
    }
}
