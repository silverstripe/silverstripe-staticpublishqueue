<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Test\PublishableSiteTreeTest\Model\PublishablePage;

class PublishableSiteTreeTest extends SapphireTest
{
    protected static $required_extensions = [
        PublishablePage::class => [PublishableSiteTree::class]
    ];

    public function testObjectsToUpdateOnPublish()
    {
        $parent = PublishablePage::create();

        $stub = $this->getMockBuilder(PublishablePage::class)
            ->setMethods(
                [
                    'getParentID',
                    'Parent',
                ]
            )->getMock();

        $stub->expects($this->once())
            ->method('getParentID')
            ->will($this->returnValue('2'));

        $stub->expects($this->once())
            ->method('Parent')
            ->will($this->returnValue($parent));

        $objects = $stub->objectsToUpdate(['action' => 'publish']);
        $this->assertContains($stub, $objects);
        $this->assertContains($parent, $objects);
        $this->assertCount(2, $objects);
    }

    public function testObjectsToUpdateOnUnpublish()
    {
        $parent = PublishablePage::create();

        $stub = $this->getMockBuilder(PublishablePage::class)
            ->setMethods(
                [
                    'getParentID',
                    'Parent',
                ]
            )->getMock();

        $stub->expects($this->once())
            ->method('getParentID')
            ->will($this->returnValue('2'));

        $stub->expects($this->once())
            ->method('Parent')
            ->will($this->returnValue($parent));

        $updates = $stub->objectsToUpdate(['action' => 'unpublish']);
        $deletions = $stub->objectsToDelete(['action' => 'unpublish']);
        $this->assertContains($stub, $deletions);
        $this->assertNotContains($parent, $deletions);
        $this->assertContains($parent, $updates);
        $this->assertNotContains($stub, $updates);
        $this->assertCount(1, $deletions);
        $this->assertCount(1, $updates);
    }

    public function testObjectsToDeleteOnPublish()
    {
        $stub = PublishablePage::create();
        $objects = $stub->objectsToDelete(['action' => 'publish']);
        $this->assertEmpty($objects);
    }

    public function testObjectsToDeleteOnUnpublish()
    {
        $stub = PublishablePage::create();
        $stub->Title = 'stub';
        $objects = $stub->objectsToDelete(['action' => 'unpublish']);
        $this->assertContains($stub, $objects);
        $this->assertCount(1, $objects);
    }

    public function testObjectsToUpdateOnPublishIfVirtualExists()
    {
        $redir = PublishablePage::create();

        $stub = $this->getMockBuilder(PublishablePage::class)
            ->setMethods(['getMyVirtualPages'])
            ->getMock();

        $stub->expects($this->once())
            ->method('getMyVirtualPages')
            ->will(
                $this->returnValue(
                    new ArrayList([$redir])
                )
            );

        $objects = $stub->objectsToUpdate(['action' => 'publish']);
        $this->assertContains($stub, $objects);
        $this->assertContains($redir, $objects);
        $this->assertCount(2, $objects);
    }

    public function testObjectsToDeleteOnUnpublishIfVirtualExists()
    {
        $redir = PublishablePage::create();

        $stub = $this->getMockBuilder(PublishablePage::class)
            ->setMethods(['getMyVirtualPages'])
            ->getMock();

        $stub->Title = 'stub';

        $stub->expects($this->once())
            ->method('getMyVirtualPages')
            ->will(
                $this->returnValue(
                    new ArrayList([$redir])
                )
            );

        $objects = $stub->objectsToDelete(['action' => 'unpublish']);
        $this->assertContains($stub, $objects);
        $this->assertContains($redir, $objects);
        $this->assertCount(2, $objects);
    }
}
