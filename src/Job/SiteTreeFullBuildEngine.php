<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\Core\Environment;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Similar to {@link RebuildStaticPagesTask}, but only queues pages for republication
 * in the {@link StaticPagesQueue}. This queue is worked off by an independent task running constantly on the server.
 */
class SiteTreeFullBuildEngine implements QueuedJob
{

    /**
     * @var string
     */
    protected $description = 'Full cache rebuild: adds all pages on the site to the static publishing queue';

    /**
     * @var int - chunk size (set via config)
     */
    private static $records_per_request = 200;

    /**
     * @return bool
     */
    public function process()
    {
        // The following shenanigans are necessary because a simple Page::get()
        // will run out of memory on large data sets. This will take the pages
        // in chunks by running this script multiple times and setting $_GET['start'].
        // Chunk size can be set via yml (SiteTreeFullBuildEngine.records_per_request).
        // To disable this functionality, just set a large chunk size and pass start=0.
        Environment::increaseTimeLimitTo();
        $self = get_class($this);

        if (isset($_GET['start'])) {
            $this->runFrom((int)$_GET['start']);
        } else {
            foreach (array('framework','sapphire') as $dirname) {
                $script = sprintf("%s%s$dirname%scli-script.php", BASE_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
                if (file_exists($script)) {
                    break;
                }
            }

            $total = $this->getAllLivePages()->count();
            echo "Adding all pages to the queue. Total: $total\n\n";
            for ($offset = 0; $offset < $total; $offset += self::config()->records_per_request) {
                echo "$offset..";
                $cmd = "php $script dev/tasks/$self start=$offset";
                if ($verbose) {
                    echo "\n  Running '$cmd'\n";
                }
                $res = $verbose ? passthru($cmd) : `$cmd`;
                if ($verbose) {
                    echo "  ".preg_replace('/\r\n|\n/', '$0  ', $res)."\n";
                }
            }
        }
    }


    /**
     * Process a chunk of pages
     *
     * @param $start
     */
    protected function runFrom($start)
    {
        $chunkSize = (int)self::config()->records_per_request;
        $pages = $this->getAllLivePages()->sort('ID')->limit($chunkSize, $start);
        $count = 0;

        // Collect all URLs into the queue
        foreach ($pages as $page) {
            if (is_callable(array($page, 'urlsToCache'))) {
                $this->getUrlArrayObject()->addUrlsOnBehalf($page->urlsToCache(), $page);
                $count++;
            }
        }

        echo sprintf("SiteTreeFullBuildEngine: Queuing %d pages".PHP_EOL, $count);
    }


    /**
     * Adds an array of urls to the Queue
     *
     * @param  array $urls
     * @return bool - if any pages were queued
     */
    protected function queueURLs($urls = array())
    {
        echo sprintf("SiteTreeFullBuildEngine: Queuing %d pages".PHP_EOL, count($urls));
        if (!count($urls)) {
            return false;
        }
        $this->getUrlArrayObject()->addUrls($urls);
        return true;
    }

    /**
     *
     * @return DataList
     */
    protected function getAllLivePages()
    {
        ini_set('memory_limit', '512M');
        $oldMode = Versioned::get_reading_mode();
        if (class_exists('Subsite')) {
            Subsite::disable_subsite_filter(true);
        }
        if (class_exists('Translatable')) {
            Translatable::disable_locale_filter();
        }
        Versioned::reading_stage('Live');
        $pages = DataObject::get("SiteTree");
        Versioned::set_reading_mode($oldMode);
        return $pages;
    }

    /**
     * Gets a title for the job that can be used in listings
     *
     * @return string
     */
    public function getTitle()
    {
        // TODO: Implement getTitle() method.
    }

    /**
     * Gets a unique signature for this job and its current parameters.
     *
     * This is used so that a job isn't added to a queue multiple times - this for example, an indexing job
     * might be added every time an item is saved, but it isn't processed immediately. We dont NEED to do the indexing
     * more than once (ie the first indexing will still catch any subsequent changes), so we don't need to have
     * it in the queue more than once.
     *
     * If you have a job that absolutely must run multiple times, the AbstractQueuedJob class provides a time sensitive
     * randomSignature() method that can be used for returning a random signature each time
     *
     * @return string
     */
    public function getSignature()
    {
        // TODO: Implement getSignature() method.
    }

    /**
     * Setup this queued job. This is only called the first time this job is executed
     * (ie when currentStep is 0)
     */
    public function setup()
    {
        // TODO: Implement setup() method.
    }

    /**
     * Called whenever a job is restarted for whatever reason.
     *
     * This is a separate method so that broken jobs can do some fixup before restarting.
     */
    public function prepareForRestart()
    {
        // TODO: Implement prepareForRestart() method.
    }

    /**
     * What type of job is this? Options are
     * - QueuedJob::IMMEDIATE
     * - QueuedJob::QUEUED
     * - QueuedJob::LARGE
     */
    public function getJobType()
    {
        // TODO: Implement getJobType() method.
    }

    /**
     * Returns true or false to indicate that this job is finished
     */
    public function jobFinished()
    {
        // TODO: Implement jobFinished() method.
    }

    /**
     * Return the current job state as an object containing data
     *
     * stdClass (
     *      'totalSteps' => the total number of steps in this job - this is relayed to the user as an indicator of time
     *      'currentStep' => the current number of steps done so far.
     *      'isComplete' => whether the job is finished yet
     *      'jobData' => data that the job wants persisted when it is stopped or started
     *      'messages' => a cumulative array of messages that have occurred during this job so far
     * )
     */
    public function getJobData()
    {
        // TODO: Implement getJobData() method.
    }

    /**
     * Sets data about the job
     *
     * is an inverse of the getJobData() method, but being explicit about what data is set
     *
     * @param int       $totalSteps
     * @param int       $currentStep
     * @param boolean   $isComplete
     * @param \stdClass $jobData
     * @param array     $messages
     *
     * @see QueuedJob::getJobData();
     */
    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        // TODO: Implement setJobData() method.
    }

    /**
     * Add an arbitrary text message into a job
     *
     * @param string $message
     */
    public function addMessage($message)
    {
        // TODO: Implement addMessage() method.
    }
}
