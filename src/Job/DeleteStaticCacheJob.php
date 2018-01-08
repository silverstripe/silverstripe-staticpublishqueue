<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

class DeleteStaticCacheJob extends Job
{
    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Remove a set of static pages from the cache';
    }

    public function setup()
    {
        parent::setup();
        $this->totalSteps = ceil(count($this->URLsToProcess) / self::config()->get('chunk_size'));
        $this->addMessage('Purging URLS ' . var_export(array_keys($this->URLsToProcess), true));
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
            $meta = Publisher::singleton()->purgeURL($url);
            if (!empty($meta['success'])) {
                $this->jobData->ProcessedURLs[$url] = $url;
                unset($this->jobData->URLsToProcess[$url]);
            }
        }
        $this->isComplete = empty($this->jobData->URLsToProcess);
    }
}
