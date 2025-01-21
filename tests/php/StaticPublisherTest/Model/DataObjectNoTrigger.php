<?php

namespace SilverStripe\StaticPublishQueue\Test\StaticPublisherTest\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class DataObjectNoTrigger extends DataObject implements TestOnly
{
    public function AbsoluteLink()
    {
        return 'http://example.com/subpage/dataobject-1';
    }
}

