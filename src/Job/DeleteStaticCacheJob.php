<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

/**
 * Class DeleteStaticCacheJob
 * remove pages from static cache based on list of URLs
 *
 * @package SilverStripe\StaticPublishQueue\Job
 */
class DeleteStaticCacheJob extends Job
{
    /**
     * @var int
     * @config
     */
    private static $chunk_size = 2000;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return 'Remove a set of static pages from the cache';
    }

    /**
     * @param string $url
     * @param int $priority
     */
    protected function processUrl(string $url, int $priority): void
    {
        $meta = Publisher::singleton()->purgeURL($url);
        $meta = is_array($meta) ? $meta : [];

        if (array_key_exists('success', $meta) && $meta['success']) {
            $this->markUrlAsProcessed($url);

            return;
        }

        $this->handleFailedUrl($url, $meta);
    }
}
