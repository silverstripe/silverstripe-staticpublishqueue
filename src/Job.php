<?php

namespace SilverStripe\StaticPublishQueue;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * @property array $URLsToProcess
 * @property array $ProcessedURLs
 */
abstract class Job extends AbstractQueuedJob
{
    use Configurable;
    use Extensible;

    /**
     * Number of URLs processed during one call of @see AbstractQueuedJob::process()
     * This number should be set to a value which represents number of URLs which is reasonable to process in one go
     * This number will vary depending on project, more specifically it depends on:
     * - time to render your pages
     * - infrastructure
     *
     * If this number is too large jobs may experience performance / memory issues
     * If this number is too low the jobs will produce more overhead which may cause inefficiencies
     *
     * In case your project is complex and you are struggling to find the correct number
     * It's possible to move this value to a CMS setting and adjust as needed without the need of changing the code
     * Use @see Job::getChunkSize() to override the value lookup
     * You can subclass your jobs and implement your own getChunkSize() method which will look into CMS setting
     *
     * Chunking capability can be disabled if chunk size is set to 0
     * In such case, all URLs will be processed in one go
     *
     * @var int
     * @config
     */
    private static $chunk_size = 200;

    /**
     * Number of URLs per job allows you to split work into multiple smaller jobs instead of having one large job
     * This is useful if you're running a queue setup will parallel processing or if you have too many URLs in general
     * If this number is too high you're limiting the parallel processing opportunity
     * If this number is too low you're using your resources inefficiently
     * as every job processing has a fixed overhead which adds up if there are too many jobs
     *
     * In case your project is complex, and you are struggling to find the correct number
     * it's possible to move this value to a CMS setting and adjust as needed without the need to change the code
     * Use @see Job::getUrlsPerJob() to override the value lookup
     * You can subclass your jobs and implement your own getUrlsPerJob() method which can read from the CMS setting
     *
     * Batching capability can be disabled if urls per job is set to 0
     * In this case, all URLs will be processed in a single job
     *
     * @var int
     * @config
     */
    private static $urls_per_job = 0;

    /**
     * Use this method to populate newly created job with data
     */
    public function hydrate(array $urls, ?string $message): void
    {
        $this->URLsToProcess = $urls;

        if (!$message) {
            return;
        }

        $this->messages = [
            sprintf('%s: %s', $message, var_export(array_keys($urls), true)),
        ];
    }

    /**
     * Static cache manipulation jobs need to run without a user
     * this is because we don't want any session related data to become part of URLs
     * For example stage GET param is injected into URLs when user is logged in
     * This is problematic as stage param must not be present in statically published URLs
     * as they always refer to live content
     * Including stage param in visiting URL is meant to bypass static cache and redirect to admin login
     * this is something we definitely don't want for statically cached pages
     */
    public function getRunAsMemberID(): ?int
    {
        return 0;
    }

    public function setup(): void
    {
        parent::setup();
        $this->totalSteps = count($this->URLsToProcess);
    }

    public function getSignature(): string
    {
        return md5(implode('-', [static::class, implode('-', array_keys($this->URLsToProcess))]));
    }

    public function process(): void
    {
        $chunkSize = $this->getChunkSize();
        $count = 0;

        foreach ($this->URLsToProcess as $url => $priority) {
            $count += 1;

            if ($chunkSize > 0 && $count > $chunkSize) {
                return;
            }

            $this->processUrl($url, $priority);
        }

        $this->updateCompletedState();
    }

    public function getUrlsPerJob(): int
    {
        $urlsPerJob = (int) $this->config()->get('urls_per_job');

        return ($urlsPerJob > 0) ? $urlsPerJob : 0;
    }

    /**
     * Implement this method to process URL
     */
    abstract protected function processUrl(string $url, int $priority): void;

    /**
     * Move URL to list of processed URLs and update job step to indicate progress
     * indication of progress is important for jobs which take long time to process
     * jobs that do not indicate progress may be identified as stalled by the queue
     * and may end up paused
     */
    protected function markUrlAsProcessed(string $url): void
    {
        // These operation has to be done directly on the job data properties
        // as the magic methods won't cover array access write
        $this->jobData->ProcessedURLs[$url] = $url;
        unset($this->jobData->URLsToProcess[$url]);
        $this->currentStep += 1;
    }

    /**
     * Check if job is complete and update the job state if needed
     */
    protected function updateCompletedState(): void
    {
        if (count($this->URLsToProcess) > 0) {
            return;
        }

        $this->isComplete = true;
    }

    protected function getChunkSize(): int
    {
        $chunkSize = (int) $this->config()->get('chunk_size');

        return $chunkSize > 0 ? $chunkSize : 0;
    }

    /**
     * This function can be overridden to handle the case of failure of specific URL processing
     * such case is not handled by default which results in all such errors being effectively silenced
     */
    protected function handleFailedUrl(string $url, array $meta)
    {
        // no op - override this on your job classes if needed
    }
}
