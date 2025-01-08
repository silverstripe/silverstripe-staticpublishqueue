<?php

namespace SilverStripe\StaticPublishQueue\Dev;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataExtension;
use SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;

class DataExtensionAddsTrigger extends DataExtension implements StaticallyPublishable, StaticPublishingTrigger, TestOnly
{
    public function urlsToCache()
    {
        return [$this->owner->AbsoluteLink() => 0];
    }

    public function objectsToUpdate($context)
    {
        return $this->owner;
    }

    public function objectsToDelete($context)
    {
        return [];
    }
}

