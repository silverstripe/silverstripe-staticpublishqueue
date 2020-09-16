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

        $this->assertSame(
            '/updateOnPublish',
            $obj->getToUpdate()->first()->url
        );
        $this->assertSame(
            '/deleteOnPublish',
            $obj->getToDelete()->first()->url
        );
    }

    public function testCollectChangesForUnpublishing()
    {
        $obj = StaticPublishingTriggerPage::create();
        $obj->collectChanges(['action' => 'unpublish']);

        $this->assertSame(
            '/updateOnUnpublish',
            $obj->getToUpdate()->first()->url
        );
        $this->assertSame(
            '/deleteOnUnpublish',
            $obj->getToDelete()->first()->url
        );
    }
}
