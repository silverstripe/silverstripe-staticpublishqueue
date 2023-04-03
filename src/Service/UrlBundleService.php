<?php

namespace SilverStripe\StaticPublishQueue\Service;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\StaticPublishQueue\Job;

/**
 * Class UrlBundleService
 *
 * This service is responsible for bundling URLs which to static cache jobs
 * Several extension points are available to allow further customisation
 */
class UrlBundleService implements UrlBundleInterface
{
    use Extensible;
    use Injectable;

    protected array $urls = [];

    public function addUrls(array $urls): void
    {
        foreach ($urls as $url) {
            $safeUrl = $this->stripStageParam($url);

            $this->urls[$safeUrl] = $safeUrl;
        }
    }

    public function getJobsForUrls(string $jobClass, ?string $message = null, ?DataObject $contextModel = null): array
    {
        $singleton = singleton($jobClass);

        if (!$singleton instanceof Job) {
            return [];
        }

        $urls = $this->getUrls();
        $urlsPerJob = $singleton->getUrlsPerJob();
        $batches = $urlsPerJob > 0 ? array_chunk($urls, $urlsPerJob) : [$urls];
        $jobs = [];

        foreach ($batches as $urlBatch) {
            $priorityUrls = $this->assignPriorityToUrls($urlBatch);

            /** @var Job $job */
            $job = Injector::inst()->create($jobClass);
            $job->hydrate($priorityUrls, $message);

            // Use this extension point to inject some additional data into the job
            $this->extend('updateHydratedJob', $job, $contextModel);

            $jobs[] = $job;
        }

        return $jobs;
    }

    /**
     * Any URL that we attempt to process through static publisher should always have any stage=* param removed
     */
    protected function stripStageParam(string $url): string
    {
        // This will safely remove "stage" params, but keep any others. It doesn't matter where in the string "stage="
        // exists
        $url = preg_replace('/([?&])stage=[^&]+(&|$)/', '$1', $url);
        // Trim any trailing "?" or "&".
        $url = rtrim($url, '&');
        $url = rtrim($url, '?');

        return $url;
    }

    /**
     * Get URLs for further processing
     */
    protected function getUrls(): array
    {
        $urls = [];

        foreach ($this->urls as $url) {
            $url = $this->formatUrl($url);

            if (!$url) {
                continue;
            }

            $urls[] = $url;
        }

        $urls = array_unique($urls);

        // Use this extension point to change the order of the URLs if needed
        $this->extend('updateGetUrls', $urls);

        return $urls;
    }

    /**
     * Extensibility function which allows to handle custom formatting / encoding needs for URLs
     * Returning "falsy" value will make the URL to be skipped
     */
    protected function formatUrl(string $url): ?string
    {
        // Use this extension point to reformat URLs, for example encode special characters
        $this->extend('updateFormatUrl', $url);

        return $url;
    }

    /**
     * Add priority data to URLs
     */
    protected function assignPriorityToUrls(array $urls): array
    {
        $priority = 0;
        $priorityUrls = [];

        foreach ($urls as $url) {
            $priorityUrls[$url] = $priority;
            ++$priority;
        }

        return $priorityUrls;
    }
}
