<?php

namespace SilverStripe\StaticPublishQueue\Test;

use Page;
use PHPUnit\Framework\MockObject\Stub\ReturnCallback;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\StaticPublishQueue\Extension\Engine\SiteTreePublishingEngine;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Test\PublishableSiteTreeTest\Model\PublishablePage;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Director;

class PublishableSiteTreeTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $required_extensions = [
        SiteTree::class => [
            PublishableSiteTree::class,
        ],
    ];

    protected static $extra_dataobjects = [
        PublishablePage::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://example.com/');
    }

    public function testObjectsToUpdateOnURLSegmentChange(): void
    {
        $this->setExpectedFlushChangesOutput([
            [[], ['stub']],
            [['stub'], []],
            [[], ['stub-a-lub-a-dub-dub']],
        ]);

        $page = new PublishablePage;
        $page->URLSegment = 'stub';

        // publish the page
        $page->write();
        $page->publishRecursive();

        // change the URL and go again
        $page->URLSegment = 'stub-a-lub-a-dub-dub';
        $page->write();
        $page->publishRecursive();
    }

    public function testObjectsToUpdateOnURLSegmentChangeWithParents(): void
    {
        $this->setExpectedFlushChangesOutput([
            [[], ['parent']],
            [[], ['parent/stub', 'parent']],
            [['parent/stub'], ['parent']],
            [[], ['parent/stub-a-lub-a-dub-dub', 'parent']]
        ]);

        $parent = new PublishablePage;
        $parent->URLSegment = 'parent';
        $parent->write();
        $parent->publishRecursive();

        $page = new PublishablePage;
        $page->URLSegment = 'stub';
        $page->ParentID = $parent->ID;

        // publish the page
        $page->write();
        $page->publishRecursive();

        // change the URL and go again
        $page->URLSegment = 'stub-a-lub-a-dub-dub';
        $page->write();
        $page->publishRecursive();
    }

    public function testObjectsToUpdateOnSiteTreeRearrange(): void
    {
        $this->setExpectedFlushChangesOutput([
            [[], ['parent']],
            [[], ['parent/stub', 'parent']],
            [['parent/stub'], ['parent']],
            [[], ['stub']],
            [['stub'], []],
            [[], ['parent/stub', 'parent']],
        ]);

        $parent = new PublishablePage;

        $parent->URLSegment = 'parent';
        $parent->write();
        $parent->publishRecursive();

        $page = new PublishablePage;

        $page->URLSegment = 'stub';
        $page->ParentID = $parent->ID;

        // publish the page
        $page->write();
        $page->publishRecursive();

        // move to root
        $page->ParentID = 0;
        $page->write();
        $page->publishRecursive();

        // move back
        $page->ParentID = $parent->ID;
        $page->write();
        $page->publishRecursive();
    }

    public function testObjectsToUpdateOnPublish(): void
    {
        $parent = new PublishablePage;

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

    public function testObjectsToUpdateOnUnpublish(): void
    {
        $parent = new PublishablePage;

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

    public function testObjectsToDeleteOnPublish(): void
    {
        $stub = new PublishablePage;
        $objects = $stub->objectsToDelete(['action' => 'publish']);
        $this->assertEmpty($objects);
    }

    public function testObjectsToDeleteOnUnpublish(): void
    {
        $stub = new PublishablePage;
        $stub->Title = 'stub';
        $objects = $stub->objectsToDelete(['action' => 'unpublish']);
        $this->assertContains($stub, $objects);
        $this->assertCount(1, $objects);
    }

    public function testObjectsToUpdateOnPublishIfVirtualExists(): void
    {
        $redir = new PublishablePage;

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

    public function testObjectsToDeleteOnUnpublishIfVirtualExists(): void
    {
        $redir = new PublishablePage;

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

    /**
     * Takes in a map of urls we expect to be deleted and updated on each successive flushChanges call
     * [
     *   [['deleted'], ['updated']], // first time its called
     *   [['deleted'], ['updated']], // second time its called
     * ]
     * @param $map
     */
    protected function setExpectedFlushChangesOutput($map)
    {
        // build a mock of the extension overriding flushChanges to prevent writing to the queue
        $mockExtension = $this->getMockBuilder(SiteTreePublishingEngine::class)
            ->setMethods(['flushChanges'])
            ->getMock();

        // IF YOU'RE OF A NERVOUS DISPOSITION, LOOK AWAY NOW
        // stub the flushChanges method and make sure that each call is able to assert the correct items are in the
        $mockExtension
            ->expects($this->exactly(count($map)))
            ->method('flushChanges')
            ->willReturnOnConsecutiveCalls(...$this->transformMapToCallback($map, $mockExtension));

        // register our extension instance so it's applied to all SiteTree objects
        Injector::inst()->registerService($mockExtension, SiteTreePublishingEngine::class);
    }

    /**
     * Transforms the array [['deleted'], ['updated']] into callbacks with assertions
     * @param $map
     * @param $mockExtension
     * @return array
     */
    protected function transformMapToCallback($map, $mockExtension)
    {
        $getURL = function ($value) {
            return $value->RelativeLink();
        };

        $callbacks = [];
        $count = 0;
        foreach ($map as $urls) {
            ++$count;
            list($toDelete, $toUpdate) = $urls;
            $callbacks[] = new ReturnCallback(
                function () use ($toDelete, $toUpdate, $mockExtension, $getURL, $count) {
                    $this->assertSame(
                        $toDelete,
                        array_map($getURL, $mockExtension->getToDelete()),
                        'Failed on delete, iteration ' . $count
                    );
                    $mockExtension->setToDelete([]);
                    $this->assertSame(
                        $toUpdate,
                        array_map($getURL, $mockExtension->getToUpdate()),
                        'Failed on update, iteration ' . $count
                    );
                    $mockExtension->setToUpdate([]);
                }
            );
        }
        return $callbacks;
    }

    public function testUrlsToCache()
    {
        // Page class is required because RedirectorPage extends Page
        if (!class_exists(Page::class)) {
            $this->markTestSkipped('This unit test requires the Page class');
        }
        $page = new Page(['Title' => 'MyPage']);
        $id = $page->write();
        $this->assertSame(['http://example.com/mypage' => 0], $page->urlsToCache());
        $redirectorPage = new RedirectorPage(['Title' => 'MyRedirectorPage']);
        $redirectorPage->LinkToID = $id;
        $redirectorPage->write();
        $this->assertSame(['http://example.com/myredirectorpage' => 0], $redirectorPage->urlsToCache());
    }
}
