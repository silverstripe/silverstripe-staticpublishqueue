<?php

namespace SilverStripe\StaticPublishQueue\Test\Extension\Publishable;

use Page;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Extension\Engine\SiteTreePublishingEngine;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;

class PublishableSiteTreeTest extends SapphireTest
{
    protected static $fixture_file = 'PublishableSiteTreeTest.yml';

    protected static $required_extensions = [
        SiteTree::class => [
            PublishableSiteTree::class,
            SiteTreePublishingEngine::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Set up our base URL so that it's always consistent for our tests
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://example.com/');
    }

    public function testObjectsActionPublishNoInclusion(): void
    {
        // Test that only the actioned page and its virtual page are added
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_NONE);
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_NONE);

        $page = $this->objFromFixture(SiteTree::class, 'page3');
        $virtualPage = $this->objFromFixture(VirtualPage::class, 'page1');

        // We expect the following pages to be updated
        $expectedUpdateIds = [
            $page->ID,
            $virtualPage->ID,
        ];

        $context = [
            'action' => 'publish',
        ];

        $toDelete = $page->objectsToDelete($context);
        $toUpdate = $page->objectsToUpdate($context);

        // There should be no objects being deleted
        $this->assertCount(0, $toDelete);
        // There should be 2 objects being updated (our $page and virtual page)
        $this->assertCount(2, $toUpdate);

        // Check that the expected Pages are represented in our objectsToUpdate() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedUpdateIds, $this->getIdsFromArray($toUpdate));
    }

    public function testObjectsActionPublishDirectInclusion(): void
    {
        // Check that direct only parent/child are added
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_DIRECT);
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_DIRECT);

        $page = $this->objFromFixture(SiteTree::class, 'page3');
        $virtualPage = $this->objFromFixture(VirtualPage::class, 'page1');
        $parentPage = $this->objFromFixture(SiteTree::class, 'page2');
        $childPage = $this->objFromFixture(SiteTree::class, 'page4');

        // We expect the following pages to be updated
        $expectedUpdateIds = [
            $page->ID,
            $virtualPage->ID,
            $parentPage->ID,
            $childPage->ID,
        ];

        $context = [
            'action' => 'publish',
        ];

        $toDelete = $page->objectsToDelete($context);
        $toUpdate = $page->objectsToUpdate($context);

        // There should be no objects being deleted
        $this->assertCount(0, $toDelete);
        // There should be 4 object being updated (added direct child, and direct parent)
        $this->assertCount(4, $toUpdate);

        // Check that the expected Pages are represented in our objectsToUpdate() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedUpdateIds, $this->getIdsFromArray($toUpdate));
    }

    public function testObjectsActionPublishRecursiveInclusion(): void
    {
        // Check that recursive parents and children are added
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_RECURSIVE);
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_RECURSIVE);

        $page = $this->objFromFixture(SiteTree::class, 'page3');
        $virtualPage = $this->objFromFixture(VirtualPage::class, 'page1');
        $parentPage = $this->objFromFixture(SiteTree::class, 'page2');
        $childPage = $this->objFromFixture(SiteTree::class, 'page4');
        $grandparentPage = $this->objFromFixture(SiteTree::class, 'page1');
        $grandchildPage = $this->objFromFixture(SiteTree::class, 'page5');

        // We expect the following pages to be updated
        $expectedUpdateIds = [
            $page->ID,
            $virtualPage->ID,
            $parentPage->ID,
            $childPage->ID,
            $grandparentPage->ID,
            $grandchildPage->ID,
        ];

        $context = [
            'action' => 'publish',
        ];

        $toDelete = $page->objectsToDelete($context);
        $toUpdate = $page->objectsToUpdate($context);

        // There should be no objects being deleted
        $this->assertCount(0, $toDelete);
        // There should be 6 object being updated (added grandchild and grandparent)
        $this->assertCount(6, $toUpdate);

        // Check that the expected Pages are represented in our objectsToUpdate() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedUpdateIds, $this->getIdsFromArray($toUpdate));
    }

    public function testObjectsActionPublishUrlChangeNoInclusion(): void
    {
        // Test that only the actioned page and its virtual page are added
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_NONE);
        // Given the context of a URL change, we will expect all children to be added regardless of config
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_NONE);

        $page = $this->objFromFixture(SiteTree::class, 'page3');
        $virtualPage = $this->objFromFixture(VirtualPage::class, 'page1');
        $childPage = $this->objFromFixture(SiteTree::class, 'page4');
        $grandchildPage = $this->objFromFixture(SiteTree::class, 'page5');

        // We expect the following pages to be updated
        $expectedUpdateIds = [
            $page->ID,
            $virtualPage->ID,
            $childPage->ID,
            $grandchildPage->ID,
        ];

        $context = [
            'action' => 'publish',
            'urlChanged' => true,
        ];

        $toDelete = $page->objectsToDelete($context);
        $toUpdate = $page->objectsToUpdate($context);

        // There should be no objects being deleted
        $this->assertCount(0, $toDelete);
        // There should be 2 objects being updated (our $page and virtual page)
        $this->assertCount(4, $toUpdate);

        // Check that the expected Pages are represented in our objectsToUpdate() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedUpdateIds, $this->getIdsFromArray($toUpdate));
    }

    public function testObjectsActionUnpublishNoInclusion(): void
    {
        // Test that only the actioned page and its virtual page are added
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_NONE);
        // Regardless of configuration, we expect children to be added in objectsToDelete() on unpublish action
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_NONE);

        $page = $this->objFromFixture(SiteTree::class, 'page3');
        $childPage = $this->objFromFixture(SiteTree::class, 'page4');
        $grandchildPage = $this->objFromFixture(SiteTree::class, 'page5');
        $virtualPage = $this->objFromFixture(VirtualPage::class, 'page1');

        // We expect the following pages to be deleted
        $expectedDeleteIds = [
            $page->ID,
            $childPage->ID,
            $grandchildPage->ID,
            $virtualPage->ID,
        ];

        $context = [
            'action' => 'unpublish',
        ];

        $toDelete = $page->objectsToDelete($context);
        $toUpdate = $page->objectsToUpdate($context);

        // There should be 4 objects being deleted (our $page, its virtual page, child, and grandchild)
        $this->assertCount(4, $toDelete);
        // There should be no objects being updates
        $this->assertCount(0, $toUpdate);

        // Check that the expected Pages are represented in our objectsToDelete() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedDeleteIds, $this->getIdsFromArray($toDelete));
    }

    public function testObjectsActionUnpublishDirectInclusion(): void
    {
        // Test that direct parent is added
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_DIRECT);
        // Regardless of configuration, we expect children to be added in objectsToDelete() on unpublish action
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_DIRECT);

        $page = $this->objFromFixture(SiteTree::class, 'page3');
        $childPage = $this->objFromFixture(SiteTree::class, 'page4');
        $grandchildPage = $this->objFromFixture(SiteTree::class, 'page5');
        $virtualPage = $this->objFromFixture(VirtualPage::class, 'page1');
        $parentPage = $this->objFromFixture(SiteTree::class, 'page2');

        // We expect the following pages to be deleted
        $expectedDeleteIds = [
            $page->ID,
            $childPage->ID,
            $grandchildPage->ID,
            $virtualPage->ID,
        ];

        // We expect the following pages to be updated
        $expectedUpdateIds = [
            $parentPage->ID,
        ];

        $context = [
            'action' => 'unpublish',
        ];

        $toDelete = $page->objectsToDelete($context);
        $toUpdate = $page->objectsToUpdate($context);

        // There should be the same 4 pages being deleted
        $this->assertCount(4, $toDelete);
        // There should be 1 object being updated (our parent page)
        $this->assertCount(1, $toUpdate);

        // Check that the expected Pages are represented in our objectsToDelete() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedDeleteIds, $this->getIdsFromArray($toDelete));
        // Check that the expected Pages are represented in our objectsToUpdate() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedUpdateIds, $this->getIdsFromArray($toUpdate));
    }

    public function testObjectsActionUnpublishRecursiveInclusion(): void
    {
        // Test that recursive parents and children
        SiteTree::config()->set('regenerate_parents', PublishableSiteTree::RELATION_INCLUDE_RECURSIVE);
        // Regardless of configuration, we expect children to be added in objectsToDelete() on unpublish action
        SiteTree::config()->set('regenerate_children', PublishableSiteTree::RELATION_INCLUDE_RECURSIVE);

        $page = $this->objFromFixture(SiteTree::class, 'page3');
        $childPage = $this->objFromFixture(SiteTree::class, 'page4');
        $grandchildPage = $this->objFromFixture(SiteTree::class, 'page5');
        $virtualPage = $this->objFromFixture(VirtualPage::class, 'page1');
        $parentPage = $this->objFromFixture(SiteTree::class, 'page2');
        $grandparentPage = $this->objFromFixture(SiteTree::class, 'page1');

        // We expect the following pages to be deleted
        $expectedDeleteIds = [
            $page->ID,
            $childPage->ID,
            $grandchildPage->ID,
            $virtualPage->ID,
        ];

        // We expect the following pages to be updated
        $expectedUpdateIds = [
            $parentPage->ID,
            $grandparentPage->ID,
        ];

        $context = [
            'action' => 'unpublish',
        ];

        $toDelete = $page->objectsToDelete($context);
        $toUpdate = $page->objectsToUpdate($context);

        // There should be the same 4 pages being deleted
        $this->assertCount(4, $toDelete);
        // There should be 2 object being updated (our parent and grandparent pages)
        $this->assertCount(2, $toUpdate);

        // Check that the expected Pages are represented in our objectsToDelete() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedDeleteIds, $this->getIdsFromArray($toDelete));
        // Check that the expected Pages are represented in our objectsToUpdate() (order doesn't matter)
        $this->assertEqualsCanonicalizing($expectedUpdateIds, $this->getIdsFromArray($toUpdate));
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

    /**
     * @param SiteTree[] $pages
     */
    protected function getIdsFromArray(array $pages): array
    {
        $ids = [];

        foreach ($pages as $page) {
            $ids[] = $page->ID;
        }

        return $ids;
    }
}
