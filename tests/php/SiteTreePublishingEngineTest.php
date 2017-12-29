<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\StaticPublishQueue\Extension\Engine\SiteTreePublishingEngine;
use SilverStripe\StaticPublishQueue\Extension\Publisher\FilesystemPublisher;
use SilverStripe\StaticPublishQueue\Model\StaticPagesQueue;
use SilverStripe\StaticPublishQueue\Model\URLArrayObject;
use SilverStripe\StaticPublishQueue\Test\SiteTreePublishingEngineTest\Model\StaticallyPublishablePage;
use SilverStripe\StaticPublishQueue\Test\SiteTreePublishingEngineTest\Model\StaticPublishingTriggerPage;

class SiteTreePublishingEngineTest extends SapphireTest
{
    protected static $required_extensions = array(
        StaticallyPublishablePage::class => array(
            SiteTreePublishingEngine::class,
            FilesystemPublisher::class,
        ),
        StaticPublishingTriggerPage::class => array(
            SiteTreePublishingEngine::class,
            FilesystemPublisher::class,
        )
    );

    protected function setUp()
    {
        parent::setUp();
        Config::modify()->set(StaticPagesQueue::class, 'realtime', true);
    }

    public function testCollectChangesForPublishing()
    {
        $obj = StaticPublishingTriggerPage::create();
        $obj->collectChanges(array('action' => 'publish'));

        $this->assertEquals(
            array('/updateOnPublish?_ID=1&_ClassName=StaticallyPublishableTest' => 10),
            $obj->getToUpdate()
        );
        $this->assertEquals(
            array('/deleteOnPublish?_ID=1&_ClassName=StaticallyPublishableTest' => 10),
            $obj->getToDelete()
        );
    }

    public function testCollectChangesForUnpublishing()
    {
        $obj = StaticPublishingTriggerPage::create();
        $obj->collectChanges(array('action' => 'unpublish'));

        $this->assertEquals(
            array('/updateOnUnpublish?_ID=1&_ClassName=StaticallyPublishableTest' => 10),
            $obj->getToUpdate()
        );
        $this->assertEquals(
            array('/deleteOnUnpublish?_ID=1&_ClassName=StaticallyPublishableTest' => 10),
            $obj->getToDelete()
        );
    }

    public function testFlushChangesToUpdateEnqueuesAndDeletesRegular()
    {

        $toUpdate = array('/toUpdate?_ID=1&_ClassName=StaticallyPublishableTest' => 10);

        $stub = $this->getMockBuilder(StaticPublishingTriggerPage::class)
            ->setMethods(array('convertUrlsToPathMap', 'deleteRegularFiles', 'deleteStaleFiles'))
            ->getMock();

        $stub->expects($this->once())
            ->method('convertUrlsToPathMap')
            ->will($this->returnValue(array('url' => 'file')));

        // Test: enqueues the updated URLs
        $stub->setUrlArrayObject($this->getMockBuilder(URLArrayObject::class)->setMethods(array('addUrls'))->getMock());
        $stub->getUrlArrayObject()->expects($this->once())
            ->method('addUrls')
            ->with($this->equalTo($toUpdate));

        // Test: deletes just the regular files
        $stub->expects($this->never())
            ->method('deleteStaleFiles');
        $stub->expects($this->once())
            ->method('deleteRegularFiles')
            ->with($this->equalTo(array('file')));

        // Test: clears the update queue
        $stub->setToUpdate($toUpdate);
        $stub->flushChanges();
        $this->assertEmpty($stub->getToUpdate());
    }

    public function testFlushChangesToDeleteDeletesRegularAndStale()
    {
        $toDelete = array('/toDelete?_ID=1&_ClassName=StaticallyPublishableTest' => 10);

        $stub = $this->getMockBuilder(StaticPublishingTriggerPage::class)
            ->setMethods(array('convertUrlsToPathMap', 'deleteRegularFiles', 'deleteStaleFiles'))
            ->getMock();

        $stub->expects($this->once())
            ->method('convertUrlsToPathMap')
            ->will($this->returnValue(array('url' => 'file')));

        // Test: deletes both regular and stale files
        $stub->expects($this->once())
            ->method('deleteRegularFiles')
            ->with($this->equalTo(array('file')));
        $stub->expects($this->once())
            ->method('deleteStaleFiles')
            ->with($this->equalTo(array('file')));

        // Test: clears the queue
        $stub->setToDelete($toDelete);
        $stub->flushChanges();
        $this->assertEmpty($stub->getToDelete());
    }

    public function testConvertUrlsToPathMapNoObject()
    {
        Config::modify()->set('FilesystemPublisher', 'static_base_url', 'http://foo');
        Config::modify()->set('Director', 'alternate_base_url', '/');

        $urls = array('/xyzzy');

        $stub = StaticPublishingTriggerPage::create();

        // Test (inclusively with urlsToPaths, these interfaces should be refactored together)
        $result = $stub->convertUrlsToPathMap($urls);
        $this->assertEquals(
            $result,
            array(
                '/xyzzy' => './xyzzy.html'
            )
        );
    }

    public function testConvertUrlsToPathMapMainSite()
    {
        Config::modify()->set(FilesystemPublisher::class, 'static_base_url', 'http://foo');
        Config::modify()->set(Director::class, 'alternate_base_url', '/');
        $urls = array('/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable');

        // Pretend this object supports subsites, and is from the main site.
        $page = $this->getMockBuilder(StaticallyPublishablePage::class)
            ->setMethods(array('Subsite', 'hasExtension'))
            ->getMock();

        $page->expects($this->any())
            ->method('Subsite')
            ->will($this->returnValue(null));
        $page->expects($this->any())
            ->method('hasExtension')
            ->will($this->returnValue(true));

        $stub = StaticPublishingTriggerPage::create();
        $stub->setUrlArrayObject($this->getMockBuilder(URLArrayObject::class)->setMethods(array('getObject'))->getMock());
        $stub->getUrlArrayObject()->expects($this->any())
            ->method('getObject')
            ->will($this->returnValue($page));

        // Test (inclusively with urlsToPaths, these interfaces should be refactored together)
        $result = $stub->convertUrlsToPathMap($urls);
        $this->assertEquals(
            array('http://foo/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable' =>
                'foo/xyzzy.html'),
            $result
        );
    }

    public function testConvertUrlsToPathMapSubsite()
    {
        Config::modify()->set(FilesystemPublisher::class, 'static_base_url', 'http://foo');
        Config::modify()->set(Director::class, 'alternate_base_url', '/');
        $urls = array('/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable');

        // Mock a set of objects pretending to support Subsites. Subsites might not be installed.
        $domain1 = $this->createMock('SubsiteDomain_mock');
        $domain1->Domain = 'subiste1.domain.org';
        $domain2 = $this->createMock('SubsiteDomain_mock');
        $domain2->Domain = 'subiste2.domain.org';

        $domains = new ArrayList(array($domain1, $domain2));

        $subsite = $this->createMock('Subsite_mock');
        $subsite->expects($this->any())
            ->method('Domains')
            ->will($this->returnValue($domains));
        $subsite->ID = 1;

        $stub = $this->getMockBuilder(StaticallyPublishablePage::class)
            ->setMethods(array('Subsite', 'hasExtension'))
            ->getMock();

        $stub->expects($this->any())
            ->method('Subsite')
            ->will($this->returnValue($subsite));
        $stub->expects($this->any())
            ->method('hasExtension')
            ->will($this->returnValue(true));

        // Prepare static mocks.
        $stub->setUrlArrayObject($this->getMockBuilder(URLArrayObject::class)->setMethods(array('getObject'))->getMock());

        $stub->getUrlArrayObject()->expects($this->any())
            ->method('getObject')
            ->will($this->returnValue($stub));

        // Test (inclusively with urlsToPaths, these interfaces should be refactored together)
        $result = $stub->convertUrlsToPathMap($urls);
        $this->assertEquals(
            array(
                'http://subiste1.domain.org/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable' =>
                    "subiste1.domain.org/xyzzy.html",
                'http://subiste2.domain.org/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable' =>
                    "subiste2.domain.org/xyzzy.html"
            ),
            $result
        );
    }

    public function testDeleteStaleFiles()
    {
        $stub = $this->getMockBuilder(StaticPublishingTriggerPage::class)
            ->setMethods(array('deleteFromCacheDir'))
            ->getMock();

        $stub->expects($this->at(0))
            ->method('deleteFromCacheDir')
            ->with($this->equalTo('xyzzy.stale.html'));

        $stub->expects($this->at(1))
            ->method('deleteFromCacheDir')
            ->with($this->equalTo('foo/bar/baz.stale.html'));

        $stub->deleteStaleFiles(array('xyzzy.html', 'foo/bar/baz.html'));
    }
}
