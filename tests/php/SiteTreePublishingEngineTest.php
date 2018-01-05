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

    public function testDeleteStaleFiles()
    {
        $stub = $this->getMockBuilder(StaticPublishingTriggerPage::class)
            ->setMethods(['deleteFromCacheDir'])
            ->getMock();

        $stub->expects($this->at(0))
            ->method('deleteFromCacheDir')
            ->with($this->equalTo('xyzzy.stale.html'));

        $stub->expects($this->at(1))
            ->method('deleteFromCacheDir')
            ->with($this->equalTo('foo/bar/baz.stale.html'));

        $stub->deleteStaleFiles(['xyzzy.html', 'foo/bar/baz.html']);
    }
}
