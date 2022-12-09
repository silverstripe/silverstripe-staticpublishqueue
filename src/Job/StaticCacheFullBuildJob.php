<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;
use SilverStripe\Versioned\Versioned;

/**
 * Class StaticCacheFullBuildJob
 *
 * Adds all live pages to the queue for caching. Best implemented on a cron via StaticCacheFullBuildTask.
 * WARNING: this job assumes that there are not too many pages to process (dozens are fine, thousands are not)
 * as collecting URLs from all live pages will either eat up all available memory and / or stall
 * If your site has thousands of pages you need to consider a different static cache refresh solution
 * Ideally, the whole site re-cache would be segmented into smaller chunks and spread across different times of the day
 *
 * @property array $URLsToCleanUp
 * @property array $publishedURLs
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
    public function getSignature(): string
    {
        return md5(static::class);
    }

    public function setup(): void
    {
        $this->URLsToProcess = $this->getAllLivePageURLs();
        $this->URLsToCleanUp = [];

        parent::setup();

        Publisher::singleton()->purgeAll();
        $this->addMessage(sprintf('Building %s URLS', count($this->URLsToProcess)));
        $this->addMessage(var_export(array_keys($this->URLsToProcess), true));
    }

    /**
     * Do some processing yourself!
     */
    public function process(): void
    {
        // Remove any URLs which have already been processed
        if ($this->ProcessedURLs) {
            $this->URLsToProcess = array_diff_key(
                $this->URLsToProcess,
                $this->ProcessedURLs
            );
        }

        $chunkSize = $this->getChunkSize();
        $count = 0;

        // Generate static cache for all live pages
        foreach ($this->URLsToProcess as $url => $priority) {
            $count += 1;

            if ($chunkSize > 0 && $count > $chunkSize) {
                return;
            }

            $this->processUrl($url, $priority);
        }

        if (count($this->URLsToProcess) === 0) {
            $cleanUrl = function ($value) {

                // We want to trim the schema from the beginning as they map to the same place
                // anyway.
                $value = ltrim($value, 'http://');
                $value = ltrim($value, 'https://');
                $value = preg_replace('#[\?&]stage=' . Versioned::LIVE . '#', '', $value);
                $value = trim($value, '/');

                return $value;
            };

            // List of all URLs which have a static cache file
            $this->publishedURLs = array_map($cleanUrl, Publisher::singleton()->getPublishedURLs());

            // List of all URLs which were published as a part of this job
            $this->ProcessedURLs = array_map($cleanUrl, $this->ProcessedURLs);

            // Determine stale URLs - those which were not published as a part of this job

            // but still have a static cache file
            $this->URLsToCleanUp = array_diff($this->publishedURLs, $this->ProcessedURLs);

            foreach ($this->URLsToCleanUp as $staleURL) {
                $purgeMeta = Publisher::singleton()->purgeURL($staleURL);
                $purgeMeta = is_array($purgeMeta) ? $purgeMeta : [];

                if (array_key_exists('success', $purgeMeta) && $purgeMeta['success']) {
                    unset($this->jobData->URLsToCleanUp[$staleURL]);

                    continue;
                }

                $this->handleFailedUrl($staleURL, $purgeMeta);
            }
        }

        $this->updateCompletedState();
    }

    /**
     * @return array
     */
    protected function getAllLivePageURLs(): array
    {
        $urls = [];
        $this->extend('beforeGetAllLivePageURLs', $urls);
        $livePages = Versioned::get_by_stage(SiteTree::class, Versioned::LIVE);
        foreach ($livePages as $page) {
            if ($page->hasExtension(PublishableSiteTree::class) || $page instanceof StaticallyPublishable) {
                if ($page instanceof RedirectorPage && !Publisher::config()->get('cache_redirector_pages')) {
                    continue;
                }
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

    /**
     * @param string $url
     * @param int $priority
     */
    protected function processUrl(string $url, int $priority): void
    {
        $meta = Publisher::singleton()->publishURL($url, true);
        $meta = is_array($meta) ? $meta : [];

        if (array_key_exists('success', $meta) && $meta['success']) {
            $this->markUrlAsProcessed($url);

            return;
        }

        $this->handleFailedUrl($url, $meta);
    }

    protected function updateCompletedState(): void
    {
        if (count($this->URLsToProcess) > 0) {
            return;
        }

        if (count($this->URLsToCleanUp) > 0) {
            return;
        }

        $this->isComplete = true;
    }
}
