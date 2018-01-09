<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;
use SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher;
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
        return 'Generate a static pages for all URLs';
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return md5(static::class);
    }

    /**
     * A list of all the published URLs before the task is run
     * @var array
     */
    private $publishedURLs;

    public function setup()
    {
        parent::setup();
        $this->publishedURLs = Publisher::singleton()->getPublishedURLs();
        $this->URLsToProcess = $this->getAllLivePageURLs();
        $this->totalSteps = ceil(count($this->URLsToProcess) / self::config()->get('chunk_size'));
        $this->addMessage(sprintf('Building %s URLS', count($this->URLsToProcess)));
    }

    /**
     * Do some processing yourself!
     */
    public function process()
    {
        $chunkSize = self::config()->get('chunk_size');
        $count = 0;
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
            $publishedURLs = Publisher::singleton()->getPublishedURLs();
            $scraps = array_diff($this->publishedURLs, $publishedURLs);
            foreach ($scraps as $staleURL) {
                Publisher::singleton()->purgeURL($staleURL);
            }
            $this->isComplete = true;
        };
    }

    /**
     *
     * @return array
     */
    protected function getAllLivePageURLs()
    {
        $urls = [];
        $livePages = Versioned::get_by_stage(SiteTree::class, Versioned::LIVE);
        foreach ($livePages as $page) {
            if ($page->hasExtension(PublishableSiteTree::class) || $page instanceof StaticallyPublishable) {
                $urls = array_merge($urls, $page->urlsToCache());
            }
        }
        // @TODO look here when integrating subsites
        // if (class_exists(Subsite::class)) {
        //     Subsite::disable_subsite_filter(true);
        // }

        return array_unique($urls);
    }
}
