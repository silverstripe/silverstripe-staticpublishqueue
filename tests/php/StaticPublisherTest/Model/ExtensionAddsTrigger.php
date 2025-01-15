<?php

namespace SilverStripe\StaticPublishQueue\Test\StaticPublisherTest\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Core\Extension;
use SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;

class ExtensionAddsTrigger extends Extension implements StaticallyPublishable, StaticPublishingTrigger, TestOnly
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

