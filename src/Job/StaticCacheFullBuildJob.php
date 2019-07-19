<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;
use SilverStripe\StaticPublishQueue\Service\URLSanitisationService;
use SilverStripe\Versioned\Versioned;

/**
 * Class StaticCacheFullBuildJob
 * Adds all live pages to the queue for caching. Best implemented on a cron via StaticCacheFullBuildTask.
 *
 * WARNING: this job is completely unsuited for large websites as it's collecting URLs from all live pages
 * this will either eat up all available memory and / or stall on timeout
 * if your site has thousands of pages you need to consider a different static cache refresh solution
 * ideally, the whole site re-cache would be segmented into smaller chunks and spread across different times of the day
 *
 * @package SilverStripe\StaticPublishQueue\Job
 */
class StaticCacheFullBuildJob extends Job
{
    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Generate static pages for all URLs';
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return md5(static::class);
    }

    public function setup()
    {
        parent::setup();

        $urls = $this->getAllLivePageURLs();
        $urls = array_keys($urls);
        $urlService = URLSanitisationService::create();
        $urlService->addURL($urls);

        $this->URLsToProcess = $urlService->getURLs(true);
        $this->URLsToCleanUp = [];

        // update total steps as we changed the URLs data since the parent call
        $this->totalSteps = count($this->jobData->URLsToProcess);

        $this->addMessage('Building all URLs');
        $this->addMessage(var_export(array_keys($this->URLsToProcess), true));
    }

    public function process()
    {
        // Remove any URLs which have already been processed
        if ($this->jobData->ProcessedURLs) {
            $this->jobData->URLsToProcess = array_diff_key(
                $this->jobData->URLsToProcess,
                $this->jobData->ProcessedURLs
            );
        }

        $chunkSize = $this->getChunkSize();

        // generate static cache for all live pages
        $count = 0;
        foreach ($this->jobData->URLsToProcess as $url => $priority) {
            $count += 1;
            if ($chunkSize > 0 && $count > $chunkSize) {
                break;
            }

            $this->processUrl($url, $priority);
        }

        // cleanup unused static cache files
        if (count($this->jobData->URLsToProcess) === 0) {
            $trimSlashes = function ($value) {
                return trim($value, '/');
            };

            // list of all URLs which have a static cache file
            $this->jobData->publishedURLs = array_map($trimSlashes, Publisher::singleton()->getPublishedURLs());

            // list of all URLs which were published as a part of this job
            $this->jobData->ProcessedURLs = array_map($trimSlashes, $this->jobData->ProcessedURLs);

            // determine stale URLs - those which were not published as a part of this job
            //but still have a static cache file
            $this->jobData->URLsToCleanUp = array_diff($this->jobData->publishedURLs, $this->jobData->ProcessedURLs);

            foreach ($this->jobData->URLsToCleanUp as $staleURL) {
                $purgeMeta = Publisher::singleton()->purgeURL($staleURL);
                $purgeMeta = (is_array($purgeMeta)) ? $purgeMeta : [];

                if (array_key_exists('success', $purgeMeta) && $purgeMeta['success']) {
                    unset($this->jobData->URLsToCleanUp[$staleURL]);

                    continue;
                }

                $this->handleFailedUrl($staleURL, $purgeMeta);
            }
        };

        $this->updateCompletedState();
    }

    /**
     * @param string $url
     * @param int $priority
     */
    protected function processUrl($url, $priority)
    {
        $meta = Publisher::singleton()->publishURL($url, true);
        $meta = (is_array($meta)) ? $meta : [];
        if (array_key_exists('success', $meta) && $meta['success']) {
            $this->markUrlAsProcessed($url);

            return;
        }

        $this->handleFailedUrl($url, $meta);
    }

    protected function updateCompletedState()
    {
        if (count($this->jobData->URLsToProcess) > 0) {
            return;
        }

        if (count($this->jobData->URLsToCleanUp) > 0) {
            return;
        }

        $this->isComplete = true;
    }

    /**
     *
     * @return array
     */
    protected function getAllLivePageURLs()
    {
        $urls = [];
        $this->extend('beforeGetAllLivePageURLs', $urls);
        $livePages = Versioned::get_by_stage(SiteTree::class, Versioned::LIVE);
        foreach ($livePages as $page) {
            if ($page->hasExtension(PublishableSiteTree::class) || $page instanceof StaticallyPublishable) {
                $urls = array_merge($urls, $page->urlsToCache());
            }
        }

        $this->extend('afterGetAllLivePageURLs', $urls);
        // @TODO look here when integrating subsites
        // if (class_exists(Subsite::class)) {
        //     Subsite::disable_subsite_filter(true);
        // }
        return $urls;
    }
}
