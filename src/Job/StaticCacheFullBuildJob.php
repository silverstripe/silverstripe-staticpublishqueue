<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;
use SilverStripe\Versioned\Versioned;

/**
 * Adds all pages to the queue for caching. Best implemented on a cron via StaticCacheFullBuildTask.
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
        $this->URLsToProcess = $this->getAllLivePageURLs();
        $this->URLsToCleanUp = [];
        $this->totalSteps = ceil(count($this->URLsToProcess) / self::config()->get('chunk_size'));
        $this->addMessage(sprintf('Building %s URLS', count($this->URLsToProcess)));
        $this->addMessage(var_export(array_keys($this->URLsToProcess), true));
    }

    /**
     * Do some processing yourself!
     */
    public function process()
    {
        $chunkSize = self::config()->get('chunk_size');
        $count = 0;

        // Remove any URLs which have already been processed
        if ($this->jobData->ProcessedURLs) {
            $this->jobData->URLsToProcess = array_diff_key(
                $this->jobData->URLsToProcess,
                $this->jobData->ProcessedURLs
            );
        }

        foreach ($this->jobData->URLsToProcess as $url => $priority) {
            if (++$count > $chunkSize) {
                break;
            }
            $meta = Publisher::singleton()->publishURL($url, true);
            if (!empty($meta['success'])) {
                $this->jobData->ProcessedURLs[$url] = $url;
                unset($this->jobData->URLsToProcess[$url]);
            }
        }
        if (empty($this->jobData->URLsToProcess)) {
            $trimSlashes = function ($value) {
                return trim($value, '/');
            };
            $this->jobData->publishedURLs = array_map($trimSlashes, Publisher::singleton()->getPublishedURLs());
            $this->jobData->ProcessedURLs = array_map($trimSlashes, $this->jobData->ProcessedURLs);
            $this->jobData->URLsToCleanUp = array_diff($this->jobData->publishedURLs, $this->jobData->ProcessedURLs);

            foreach ($this->jobData->URLsToCleanUp as $staleURL) {
                $purgeMeta = Publisher::singleton()->purgeURL($staleURL);
                if (!empty($purgeMeta['success'])) {
                    unset($this->jobData->URLsToCleanUp[$staleURL]);
                }
            }
        };
        $this->isComplete = empty($this->jobData->URLsToProcess) && empty($this->jobData->URLsToCleanUp);
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
