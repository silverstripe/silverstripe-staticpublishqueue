<?php

namespace SilverStripe\StaticPublishQueue;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use SilverStripe\StaticPublishQueue\Service\URLSanitisationService;
use stdClass;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

abstract class Job extends AbstractQueuedJob
{
    use Configurable;
    use Extensible;
    use Injectable;

    /**
     * Number of URLs per job allows you to split work into multiple smaller jobs instead of having one large job
     * this is useful if you're running a queue setup will parallel processing
     * if this number is too high you're limiting the parallel processing opportunity
     * if this number is too low you're using your resources inefficiently
     * as every job processing has a fixed overhead which adds up if there are too many jobs
     *
     * in case you project is complex and you are struggling to find the correct number
     * it's possible to move this value to a CMS setting and adjust as needed without the need of changing the code
     * use @see Job::getUrlsPerJob() to override the value lookup
     * you can subclass your jobs and implement your own getUrlsPerJob() method which will look into CMS setting
     *
     * batching capability can be disabled if urls per job is set to 0
     * in such case, all URLs will be put into one job
     *
     * @var int
     * @config
     */
    private static $urls_per_job = 0;

    /**
     * Number of URLs processed during one call of @see AbstractQueuedJob::process
     * this number should be set to a value which represents number of URLs which is reasonable to process in one go
     * this number will vary depending on project, more specifically it depends on:
     * - time to render your pages
     * - infrastructure
     *
     * if this number is too large jobs may experience performance / memory issues
     * if this number is too low the jobs will produce more overhead which may cause inefficiencies
     *
     * in case you project is complex and you are struggling to find the correct number
     * it's possible to move this value to a CMS setting and adjust as needed without the need of changing the code
     * use @see Job::getChunkSize() to override the value lookup
     * you can subclass your jobs and implement your own getChunkSize() method which will look into CMS setting
     *
     * chunking capability can be disabled if chunk size is set to 0
     * in such case, all URLs will be processed in one go
     *
     * @var int
     * @config
     */
    private static $chunk_size = 200;

    public function getRunAsMemberID()
    {
        // static cache manipulation jobs need to run without a user
        // this is because we don't want any session related data to become part of URLs
        // for example stage GET param is injected into URLs when user is logged in
        // this is problematic as stage param must not be present in statically published URLs
        // as they always refer to live content
        // including stage param in visiting URL is meant to bypass static cache and redirect to admin login
        // this is something we definitely don't want for statically cached pages
        return 0;
    }

    /**
     * Set totalSteps to reflect how many URLs need to be processed
     * note that chunk size may change during runtime (if CMS setting override is used)
     * therefore it's much more accurate and useful to keep track of number of completed URLs
     * as opposed to completed chunks
     */
    public function setup()
    {
        parent::setup();
        $this->totalSteps = count($this->jobData->URLsToProcess);
    }

    public function getSignature()
    {
        return md5(implode('-', [static::class, implode('-', array_keys($this->URLsToProcess))]));
    }

    public function process()
    {
        $chunkSize = $this->getChunkSize();
        $count = 0;
        foreach ($this->jobData->URLsToProcess as $url => $priority) {
            $count += 1;
            if ($chunkSize > 0 && $count > $chunkSize) {
                break;
            }

            $this->processUrl($url, $priority);
        }

        $this->updateCompletedState();
    }

    /**
     * Generate static cache related jobs from data
     *
     * @param array $urls URLs to be processed into jobs
     * @param string $message will be stored in job data and it's useful debug information
     * @param int|null $urlsPerJob number of URLs per job, defaults to Job specific configuration
     * @param string|null $jobClass job class used to create jobs, defaults to current class
     * @return array|Job[]
     */
    public function createJobsFromData(
        array $urls,
        $message = '',
        $urlsPerJob = null,
        $jobClass = null
    ) {
        if (count($urls) === 0) {
            return [];
        }

        // remove duplicate URLs
        $urls = array_unique($urls);

        // fall back to current job class if we don't have an explicit value set
        if ($jobClass === null) {
            $jobClass = static::class;
        }

        // validate job class
        $job = singleton($jobClass);
        if (!($job instanceof Job)) {
            throw new ValidationException(
                sprintf('Invalid job class %s, expected instace of %s', get_class($job), Job::class)
            );
        }

        // fall back to current job urls_per_job if we don't have an explicit value set
        if ($urlsPerJob === null) {
            $urlsPerJob = $job->getUrlsPerJob();
        }

        // if no message is provided don't include it
        $message = (strlen($message) > 0) ? $message . ': ' : '';

        // batch URLs
        $batches = ($urlsPerJob > 0) ? array_chunk($urls, $urlsPerJob) : [$urls];

        $jobs = [];
        foreach ($batches as $urls) {
            // sanitise the URLS
            $urlService = Injector::inst()->create(URLSanitisationService::class);
            $urlService->addURLs($urls);
            $urls = $urlService->getURLs(true);

            // create job and populate it with data
            $job = Injector::inst()->create($jobClass);
            $jobData = new stdClass();
            $jobData->URLsToProcess = $urls;

            $job->setJobData(count($jobData->URLsToProcess), 0, false, $jobData, [
                $message . var_export(array_keys($jobData->URLsToProcess), true),
            ]);

            $jobs[] = $job;
        }

        return $jobs;
    }

    /**
     * Generate and queue static cache related jobs from data
     *
     * @param array $urls URLs to be processed into jobs
     * @param string $message will be stored in job data and it's useful debug information
     * @param int|null $urlsPerJob number of URLs per job, defaults to Job specific configuration
     * @param string|null $jobClass job class used to create jobs, defaults to current class
     */
    public function queueJobsFromData(
        array $urls,
        $message = '',
        $urlsPerJob = null,
        $jobClass = null
    ) {
        $jobs = $this->createJobsFromData($urls, $message, $urlsPerJob, $jobClass);

        // default queue process
        $service = QueuedJobService::singleton();

        foreach ($jobs as $job) {
            $service->queueJob($job);
        }
    }

    /**
     * Implement this method to process URL
     *
     * @param string $url
     * @param int $priority
     */
    abstract protected function processUrl($url, $priority);

    /**
     * Move URL to list of processed URLs and update job step to indicate progress
     * indication of progress is important for jobs which take long time to process
     * jobs that do not indicate progress may be identified as stalled by the queue
     * and may end up paused
     *
     * @param string $url
     */
    protected function markUrlAsProcessed($url)
    {
        $this->jobData->ProcessedURLs[$url] = $url;
        unset($this->jobData->URLsToProcess[$url]);
        $this->currentStep += 1;
    }

    /**
     * Check if job is complete and update the job state if needed
     */
    protected function updateCompletedState()
    {
        if (count($this->jobData->URLsToProcess) > 0) {
            return;
        }

        $this->isComplete = true;
    }

    /**
     * @return int
     */
    protected function getUrlsPerJob()
    {
        $urlsPerJob = (int) $this->config()->get('urls_per_job');

        return ($urlsPerJob > 0) ? $urlsPerJob : 0;
    }

    /**
     * @return int
     */
    protected function getChunkSize()
    {
        $chunkSize = (int) $this->config()->get('chunk_size');

        return ($chunkSize > 0) ? $chunkSize : 0;
    }

    /**
     * This function can be overridden to handle the case of failure of specific URL processing
     * such case is not handled by default which results in all such errors being effectively silenced
     *
     * @param string $url
     * @param array $meta
     */
    protected function handleFailedUrl($url, array $meta)
    {
        // no op
    }
}
