<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

/**
 * Class GenerateStaticCacheJob
 * add pages to static cache based on list of URLs
 *
 * @package SilverStripe\StaticPublishQueue\Job
 */
class GenerateStaticCacheJob extends Job
{
    /**
     * @return string
     */
    public function getTitle(): string
    {
        return 'Generate a set of static pages from URLs';
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
}
