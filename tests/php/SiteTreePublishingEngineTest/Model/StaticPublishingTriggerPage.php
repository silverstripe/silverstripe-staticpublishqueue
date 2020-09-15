<?php

namespace SilverStripe\StaticPublishQueue\Test\SiteTreePublishingEngineTest\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\ArrayList;
use SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger;

class StaticPublishingTriggerPage extends SiteTree implements TestOnly, StaticPublishingTrigger
{
    private static $table_name = 'SPQ_StaticPublishingTriggerPage';

    public function generatePublishable($url, $prio)
    {
        $obj = StaticallyPublishablePage::create();
        $obj->url = $url;
        $obj->prio = $prio;

        return $obj;
    }

    public function objectsToUpdate($context)
    {
        switch ($context['action']) {
            case 'publish':
                return new ArrayList([$this->generatePublishable('/updateOnPublish', 10)]);
            case 'unpublish':
                return new ArrayList([$this->generatePublishable('/updateOnUnpublish', 10)]);
        }
    }

    /**
     * Remove the object on unpublishing (the parent will get updated via objectsToUpdate).
     */
    public function objectsToDelete($context)
    {
        switch ($context['action']) {
            case 'publish':
                return new ArrayList([$this->generatePublishable('/deleteOnPublish', 10)]);
            case 'unpublish':
                return new ArrayList([$this->generatePublishable('/deleteOnUnpublish', 10)]);
        }
    }
}
