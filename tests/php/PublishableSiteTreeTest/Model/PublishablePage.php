<?php

namespace SilverStripe\StaticPublishQueue\Test\PublishableSiteTreeTest\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

class PublishablePage extends SiteTree implements TestOnly
{
    private static $table_name = 'SPQ_PublishablePage';
}
