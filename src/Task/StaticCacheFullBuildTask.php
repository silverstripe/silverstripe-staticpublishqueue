<?php

namespace SilverStripe\StaticPublishQueue\Task;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\StaticPublishQueue\Job\StaticCacheFullBuildJob;

class StaticCacheFullBuildTask extends BuildTask
{

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return
     */
    public function run($request)
    {
        $job = new StaticCacheFullBuildJob();
    }
}
