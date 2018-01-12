<?php

namespace SilverStripe\StaticPublishQueue\Test\SiteTreePublishingEngineTest\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\ArrayList;
use SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger;

class StaticPublishingTriggerPage extends SiteTree implements TestOnly, StaticPublishingTrigger
{

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
                return new ArrayList(array($this->generatePublishable('/updateOnPublish', 10)));
            case 'unpublish':
                return new ArrayList(array($this->generatePublishable('/updateOnUnpublish', 10)));
        }
    }

    /**
     * Remove the object on unpublishing (the parent will get updated via objectsToUpdate).
     */
    public function objectsToDelete($context)
    {
        switch ($context['action']) {
            case 'publish':
                return new ArrayList(array($this->generatePublishable('/deleteOnPublish', 10)));
            case 'unpublish':
                return new ArrayList(array($this->generatePublishable('/deleteOnUnpublish', 10)));
        }
    }
}
