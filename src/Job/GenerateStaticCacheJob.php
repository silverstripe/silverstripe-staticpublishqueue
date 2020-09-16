<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

class GenerateStaticCacheJob extends Job
{
    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Generate a set of static pages from URLs';
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
            $meta = Publisher::singleton()->publishURL($url, true);
            if (!empty($meta['success'])) {
                $this->jobData->ProcessedURLs[$url] = $url;
                unset($this->jobData->URLsToProcess[$url]);
            }
        }
        $this->currentStep++;
        $this->isComplete = empty($this->jobData->URLsToProcess);
    }
}
