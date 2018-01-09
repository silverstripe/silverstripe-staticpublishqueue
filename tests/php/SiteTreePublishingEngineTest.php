<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Test\SiteTreePublishingEngineTest\Model\StaticPublishingTriggerPage;

class SiteTreePublishingEngineTest extends SapphireTest
{

    public function testCollectChangesForPublishing()
    {
        $obj = StaticPublishingTriggerPage::create();
        $obj->collectChanges(['action' => 'publish']);

        $this->assertEquals(
            '/updateOnPublish',
            $obj->getToUpdate()->first()->url
        );
        $this->assertEquals(
            '/deleteOnPublish',
            $obj->getToDelete()->first()->url
        );
    }

    public function testCollectChangesForUnpublishing()
    {
        $obj = StaticPublishingTriggerPage::create();
        $obj->collectChanges(['action' => 'unpublish']);

        $this->assertEquals(
            '/updateOnUnpublish',
            $obj->getToUpdate()->first()->url
        );
        $this->assertEquals(
            '/deleteOnUnpublish',
            $obj->getToDelete()->first()->url
        );
    }
}
