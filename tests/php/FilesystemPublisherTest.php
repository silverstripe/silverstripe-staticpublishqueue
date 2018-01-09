<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Assets\Filesystem;
use SilverStripe\CMS\Model\RedirectorPage;
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
 * @package staticpublishqueue
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

        $fsp = FilesystemPublisher::create();

        $this->assertEquals(
            'index',
            $urlToPath->invokeArgs($fsp, ['/'])
        );

        $this->assertEquals(
            'about-us',
            $urlToPath->invokeArgs($fsp, ['about-us'])
        );

        $this->assertEquals(
            'parent/child',
            $urlToPath->invokeArgs($fsp, ['parent/child'])
        );
    }

    public function testUrlToPathWithAbsoluteUrls()
    {
        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $fsp = FilesystemPublisher::create();

        $url = Director::absoluteBaseUrl();
        $this->assertEquals(
            'index',
            $urlToPath->invokeArgs($fsp, [$url])
        );

        $url = Director::absoluteBaseUrl() . 'about-us';
        $this->assertEquals(
            'about-us',
            $urlToPath->invokeArgs($fsp, [$url])
        );

        $url = Director::absoluteBaseUrl() . 'parent/child';
        $this->assertEquals(
            'parent/child',
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
            'domain1.com/index',
            $urlToPath->invokeArgs($fsp, [$url])
        );

        $url = 'http://domain1.com/about-us';
        $this->assertEquals(
            'domain1.com/about-us',
            $urlToPath->invokeArgs($fsp, [$url])
        );

        $url = 'http://domain2.com/parent/child';
        $this->assertEquals(
            'domain2.com/parent/child',
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

        $this->assertFileExists($static2_1FilePath.'.html');
        $this->assertFileExists($static2_1FilePath.'.php');
        $this->assertContains(
            'current',
            file_get_contents($static2_1FilePath.'.html')
        );

        $level2_2 = StaticPublisherTestPage::create();
        $level2_2->URLSegment = 'test-level-2-2';
        $level2_2->ParentID = $level1->ID;
        $level2_2->write();
        $level2_2->publishRecursive();

        $fsp->publishURL($level2_2->Link(), true);
        $static2_2FilePath = $fsp->getDestPath().$urlToPath->invokeArgs($fsp, [$level2_2->Link()]);

        $this->assertFileExists($static2_2FilePath.'.html');
        $this->assertFileExists($static2_2FilePath.'.php');
        $this->assertContains(
            'linkcurrent',
            file_get_contents($static2_2FilePath.'.html')
        );

        if (file_exists($fsp->getDestPath())) {
            Filesystem::removeFolder($fsp->getDestPath());
        }
    }

    public function testOnlyHTML()
    {
        $this->logInWithPermission('ADMIN');

        $fsp = FilesystemPublisher::create()
            ->setFileExtension('html')
            ->setDestFolder('cache/testing/');
        $level1 = StaticPublisherTestPage::create();
        $level1->URLSegment = 'mimetype';
        $level1->write();
        $level1->publishRecursive();

        $fsp->publishURL($level1->Link(), true);
        $staticFilePath = $fsp->getDestPath().'mimetype';

        $this->assertFileExists($staticFilePath.'.html');
        $this->assertFileNotExists($staticFilePath.'.php');
        $this->assertEquals(
            "<div class=\"statically-published\" style=\"display: none\"></div>",
            trim(file_get_contents($staticFilePath.'.html'))
        );
        if (file_exists($fsp->getDestPath())) {
            Filesystem::removeFolder($fsp->getDestPath());
        }
    }

    public function testPurgeURL()
    {
        $this->logInWithPermission('ADMIN');

        $fsp = FilesystemPublisher::create()
            ->setDestFolder('cache/testing/');
        $level1 = StaticPublisherTestPage::create();
        $level1->URLSegment = 'to-be-purged';
        $level1->write();
        $level1->publishRecursive();

        $fsp->publishURL('to-be-purged', true);
        $this->assertFileExists($fsp->getDestPath().'to-be-purged.html');
        $this->assertFileExists($fsp->getDestPath().'to-be-purged.php');

        $fsp->purgeURL('to-be-purged');
        $this->assertFileNotExists($fsp->getDestPath().'to-be-purged.html');
        $this->assertFileNotExists($fsp->getDestPath().'to-be-purged.php');
    }

    public function testPurgeURLAfterSwitchingExtensions()
    {
        $this->logInWithPermission('ADMIN');

        $fsp = FilesystemPublisher::create()
            ->setDestFolder('cache/testing/');
        $level1 = StaticPublisherTestPage::create();
        $level1->URLSegment = 'purge-me';
        $level1->write();
        $level1->publishRecursive();

        $fsp->publishURL('purge-me', true);
        $this->assertFileExists($fsp->getDestPath().'purge-me.html');
        $this->assertFileExists($fsp->getDestPath().'purge-me.php');

        $fsp->setFileExtension('html');

        $fsp->purgeURL('purge-me');
        $this->assertFileNotExists($fsp->getDestPath().'purge-me.html');
        $this->assertFileNotExists($fsp->getDestPath().'purge-me.php');
    }

    public function testNoErrorPagesWhenHTMLOnly()
    {
        $this->logInWithPermission('ADMIN');

        $fsp = FilesystemPublisher::create()
            ->setFileExtension('html')
            ->setDestFolder('cache/testing/');
        $fsp->publishURL('not_really_there', true);
        $this->assertFileNotExists($fsp->getDestPath() . 'not_really_there.html');
        $this->assertFileNotExists($fsp->getDestPath() . 'not_really_there.php');

        if (file_exists($fsp->getDestPath())) {
            Filesystem::removeFolder($fsp->getDestPath());
        }
    }

    public function testErrorPageWhenPHP()
    {
        $this->logInWithPermission('ADMIN');

        $fsp = FilesystemPublisher::create()
            ->setDestFolder('cache/testing/');
        $fsp->publishURL('not_really_there', true);
        $this->assertFileExists($fsp->getDestPath() . 'not_really_there.html');
        $this->assertFileExists($fsp->getDestPath() . 'not_really_there.php');
        $phpCacheConfig = require $fsp->getDestPath() . 'not_really_there.php';
        $this->assertEquals(404, $phpCacheConfig['responseCode']);

        if (file_exists($fsp->getDestPath())) {
            Filesystem::removeFolder($fsp->getDestPath());
        }
    }

    public function testRedirectorPageWhenPHP()
    {
        $this->logInWithPermission('ADMIN');

        $fsp = FilesystemPublisher::create()
            ->setDestFolder('cache/testing/');
        $redirectorPage = RedirectorPage::create();
        $redirectorPage->URLSegment = 'somewhere-else';
        $redirectorPage->RedirectionType = 'External';
        $redirectorPage->ExternalURL = 'silverstripe.org';
        $redirectorPage->write();
        $redirectorPage->publishRecursive();

        $fsp->publishURL('somewhere-else', true);

        $this->assertFileExists($fsp->getDestPath() . 'somewhere-else.html');
        $this->assertContains(
            'Click this link if your browser does not redirect you',
            file_get_contents($fsp->getDestPath() . 'somewhere-else.html')
        );
        $this->assertFileExists($fsp->getDestPath() . 'somewhere-else.php');
        $phpCacheConfig = require $fsp->getDestPath() . 'somewhere-else.php';
        $this->assertEquals(301, $phpCacheConfig['responseCode']);
        $this->assertContains('location: http://silverstripe.org', $phpCacheConfig['headers']);

        if (file_exists($fsp->getDestPath())) {
            Filesystem::removeFolder($fsp->getDestPath());
        }
    }

    public function testRedirectorPageWhenHTMLOnly()
    {
        $this->logInWithPermission('ADMIN');

        $fsp = FilesystemPublisher::create()
            ->setFileExtension('html')
            ->setDestFolder('cache/testing/');

        $redirectorPage = RedirectorPage::create();
        $redirectorPage->URLSegment = 'somewhere-else';
        $redirectorPage->RedirectionType = 'External';
        $redirectorPage->ExternalURL = 'silverstripe.org';
        $redirectorPage->write();
        $redirectorPage->publishRecursive();

        $fsp->publishURL('somewhere-else', true);

        $this->assertFileExists($fsp->getDestPath() . 'somewhere-else.html');
        $this->assertContains(
            'Click this link if your browser does not redirect you',
            file_get_contents($fsp->getDestPath() . 'somewhere-else.html')
        );
        $this->assertFileNotExists($fsp->getDestPath() . 'somewhere-else.php');

        if (file_exists($fsp->getDestPath())) {
            Filesystem::removeFolder($fsp->getDestPath());
        }
    }
}
