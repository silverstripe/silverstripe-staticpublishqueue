<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

class DeleteWholeCache extends Job
{
    public function getTitle(): string
    {
        return 'Remove the entire cache';
    }

    /**
     * Do some processing yourself!
     */
    public function process(): void
    {
        $this->isComplete = Publisher::singleton()->purgeAll();
    }

    public function processUrl(string $url, int $priority): void
    {
        // noop
    }
}
