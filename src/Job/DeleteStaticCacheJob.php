<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

/**
 * Remove pages from static cache based on list of URLs
 */
class DeleteStaticCacheJob extends Job
{
    private static int $chunk_size = 2000;

    public function getTitle(): string
    {
        return 'Remove a set of static pages from the cache';
    }

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
