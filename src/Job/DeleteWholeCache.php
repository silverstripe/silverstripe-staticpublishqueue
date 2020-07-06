<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\StaticPublishQueue\Job;
use SilverStripe\StaticPublishQueue\Publisher;

class DeleteWholeCache extends Job
{
    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Remove the entire cache';
    }

    /**
     * Do some processing yourself!
     */
    public function process()
    {
        $this->isComplete = Publisher::singleton()->purgeAll();
    }
}
