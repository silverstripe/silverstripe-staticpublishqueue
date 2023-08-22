<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

/**
 * Add pages to static cache based on list of URLs
 */
class GenerateStaticCacheJob extends Job
{
    public function getTitle(): string
    {
        return 'Generate a set of static pages from URLs';
    }

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
