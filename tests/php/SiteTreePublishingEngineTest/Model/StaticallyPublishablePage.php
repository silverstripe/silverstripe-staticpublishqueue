<?php

namespace SilverStripe\StaticPublishQueue\Test\SiteTreePublishingEngineTest\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;

class StaticallyPublishablePage extends SiteTree implements TestOnly, StaticallyPublishable
{
    public $url;

    public $prio;

    private static $table_name = 'SPQ_StaticallyPublishablePage';

    public function getID()
    {
        return '1';
    }

    public function urlsToCache()
    {
        return [$this->url => $this->prio];
    }
}
