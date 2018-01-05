<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Assets\Filesystem;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher;
use SilverStripe\StaticPublishQueue\Test\StaticPublisherTest\Model\StaticPublisherTestPage;
use SilverStripe\View\SSViewer;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Tests for the {@link FilesystemPublisher} class.
 *
 * @package staticpublisher
 */
class FilesystemPublisherTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp()
    {
        parent::setUp();

        SiteTree::add_extension(PublishableSiteTree::class);

        Config::modify()->set(FilesystemPublisher::class, 'domain_based_caching', false);
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://foo/');
        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);
    }

    protected function tearDown()
    {
        SiteTree::remove_extension(PublishableSiteTree::class);

        parent::tearDown();
    }

    public function testUrlToPathWithRelativeUrls()
    {
        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $fsp = FilesystemPublisher::create()->setFileExtension('html');

        $this->assertEquals(
            'index.html',
            $urlToPath->invokeArgs($fsp, ['/'])
        );

        $this->assertEquals(
            'about-us.html',
            $urlToPath->invokeArgs($fsp, ['about-us'])
        );

        $this->assertEquals(
            'parent/child.html',
            $urlToPath->invokeArgs($fsp, ['parent/child'])
        );
    }

    public function testUrlToPathWithAbsoluteUrls()
    {
        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $fsp = FilesystemPublisher::create()->setFileExtension('html');

        $url = Director::absoluteBaseUrl();
        $this->assertEquals(
            'index.html',
            $urlToPath->invokeArgs($fsp, [$url])
        );

        $url = Director::absoluteBaseUrl() . 'about-us';
        $this->assertEquals(
            'about-us.html',
            $urlToPath->invokeArgs($fsp, [$url])
        );

        $url = Director::absoluteBaseUrl() . 'parent/child';
        $this->assertEquals(
            'parent/child.html',
            $urlToPath->invokeArgs($fsp, [$url])
        );
    }

    public function testUrlToPathWithDomainBasedCaching()
    {
        Config::modify()->set(FilesystemPublisher::class, 'domain_based_caching', true);

        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $fsp = FilesystemPublisher::create()->setFileExtension('html');

        $url = 'http://domain1.com/';
        $this->assertEquals(
            'domain1.com/index.html',
            $urlToPath->invokeArgs($fsp, [$url])
        );

        $url = 'http://domain1.com/about-us';
        $this->assertEquals(
            'domain1.com/about-us.html',
            $urlToPath->invokeArgs($fsp, [$url])
        );

        $url = 'http://domain2.com/parent/child';
        $this->assertEquals(
            'domain2.com/parent/child.html',
            $urlToPath->invokeArgs($fsp, [$url])
        );
    }

    public function testMenu2LinkingMode()
    {
        $this->logInWithPermission('ADMIN');

        SSViewer::set_themes(null);

        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $fsp = FilesystemPublisher::create()
            ->setDestFolder('cache/testing/');

        $level1 = StaticPublisherTestPage::create();
        $level1->URLSegment = 'test-level-1';
        $level1->write();
        $level1->publishRecursive();

        $level2_1 = StaticPublisherTestPage::create();
        $level2_1->URLSegment = 'test-level-2-1';
        $level2_1->ParentID = $level1->ID;
        $level2_1->write();
        $level2_1->publishRecursive();

        $fsp->publishURL($level1->Link(), true);
        $fsp->publishURL($level2_1->Link(), true);
        $static2_1FilePath = $fsp->getDestPath().$urlToPath->invokeArgs($fsp, [$level2_1->Link()]);

        $this->assertFileExists($static2_1FilePath);
        $this->assertContains(
            'current',
            file_get_contents($static2_1FilePath)
        );

        $level2_2 = new StaticPublisherTestPage();
        $level2_2->URLSegment = 'test-level-2-2';
        $level2_2->ParentID = $level1->ID;
        $level2_2->write();
        $level2_2->publishRecursive();

        $fsp->publishURL($level2_2->Link(), true);
        $static2_2FilePath = $fsp->getDestPath().$urlToPath->invokeArgs($fsp, [$level2_2->Link()]);

        $this->assertFileExists($static2_2FilePath);
        $this->assertContains(
            'linkcurrent',
            file_get_contents($static2_2FilePath)
        );

        if (file_exists($fsp->getDestPath())) {
            Filesystem::removeFolder($fsp->getDestPath());
        }
    }

    public function testContentTypeHTML()
    {
        $this->logInWithPermission('ADMIN');

        $fsp = FilesystemPublisher::create()
            ->setFileExtension('html')
            ->setDestFolder('cache/testing/');
        $level1 = new StaticPublisherTestPage();
        $level1->URLSegment = 'mimetype';
        $level1->write();
        $level1->publishRecursive();

        $fsp->publishURL($level1->Link(), true);
        $staticFilePath = $fsp->getDestPath().'mimetype.html';

        $this->assertFileExists($staticFilePath);
        $this->assertEquals(
            "<div class=\"statically-published\" style=\"display: none\"></div>",
            trim(file_get_contents($staticFilePath))
        );
        if (file_exists($fsp->getDestPath())) {
            Filesystem::removeFolder($fsp->getDestPath());
        }
    }
}
