<?php

namespace SilverStripe\StaticPublishQueue\Test\URLArrayObjectTest\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class URLArrayObjectTestPage extends SiteTree implements TestOnly
{
    private static $db = array(
        'excludeFromCache' => 'Boolean'
    );
}
