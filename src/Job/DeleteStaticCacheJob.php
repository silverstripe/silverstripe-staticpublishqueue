<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

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
    public function getTitle()
    {
        return 'Remove a set of static pages from the cache';
    }

    /**
     * Do some processing yourself!
     */
    public function process()
    {
        $chunkSize = self::config()->get('chunk_size');
        $count = 0;
        foreach (array_keys($this->jobData->URLsToProcess) as $url) {
            if (++$count > $chunkSize) {
                break;
            }
            $meta = Publisher::singleton()->purgeURL($url);
            if (! empty($meta['success'])) {
                $this->jobData->ProcessedURLs[$url] = $url;
                unset($this->jobData->URLsToProcess[$url]);
            }
        }
        $this->isComplete = empty($this->jobData->URLsToProcess);
    }
}
