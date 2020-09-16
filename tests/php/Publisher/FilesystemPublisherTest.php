<?php

namespace SilverStripe\StaticPublishQueue\Test\Publisher;

use SilverStripe\Assets\Filesystem;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPApplication;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestKernel;
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

    /**
     * @var FilesystemPublisher
     */
    private $fsp = null;

    protected static $required_extensions = [
        SiteTree::class => [
            PublishableSiteTree::class,
        ],
    ];

    protected function setUp()
    {
        parent::setUp();

        Config::modify()->set(FilesystemPublisher::class, 'domain_based_caching', false);
        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://example.com/');

        $mockFSP = $this->getMockBuilder(FilesystemPublisher::class)->setMethods([
            'getHTTPApplication',
        ])->getMock();

        $mockFSP->method('getHTTPApplication')->willReturnCallback(function () {
            return new HTTPApplication(new TestKernel(BASE_PATH));
        });

        $this->fsp = $mockFSP->setDestFolder('cache/testing/');
    }

    protected function tearDown()
    {
        if ($this->fsp !== null && file_exists($this->fsp->getDestPath())) {
            Filesystem::removeFolder($this->fsp->getDestPath());
        }
        parent::tearDown();
    }

    public function testUrlToPathWithRelativeUrls()
    {
        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $this->assertSame(
            'index',
            $urlToPath->invokeArgs($this->fsp, ['/'])
        );

        $this->assertSame(
            'about-us',
            $urlToPath->invokeArgs($this->fsp, ['about-us'])
        );

        $this->assertSame(
            'parent/child',
            $urlToPath->invokeArgs($this->fsp, ['parent/child'])
        );
    }

    public function testUrlToPathWithAbsoluteUrls()
    {
        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $url = Director::absoluteBaseUrl();
        $this->assertSame(
            'index',
            $urlToPath->invokeArgs($this->fsp, [$url])
        );

        $url = Director::absoluteBaseUrl() . 'about-us';
        $this->assertSame(
            'about-us',
            $urlToPath->invokeArgs($this->fsp, [$url])
        );

        $url = Director::absoluteBaseUrl() . 'parent/child';
        $this->assertSame(
            'parent/child',
            $urlToPath->invokeArgs($this->fsp, [$url])
        );
    }

    public function testUrlToPathWithDomainBasedCaching()
    {
        Config::modify()->set(FilesystemPublisher::class, 'domain_based_caching', true);

        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $this->fsp->setFileExtension('html');

        $url = 'http://domain1.com/';
        $this->assertSame(
            'domain1.com/index',
            $urlToPath->invokeArgs($this->fsp, [$url])
        );

        $url = 'http://domain1.com/about-us';
        $this->assertSame(
            'domain1.com/about-us',
            $urlToPath->invokeArgs($this->fsp, [$url])
        );

        $url = 'http://domain2.com/parent/child';
        $this->assertSame(
            'domain2.com/parent/child',
            $urlToPath->invokeArgs($this->fsp, [$url])
        );
    }

    public function testMenu2LinkingMode()
    {
        SSViewer::set_themes(null);

        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $urlToPath = $reflection->getMethod('URLtoPath');
        $urlToPath->setAccessible(true);

        $level1 = new StaticPublisherTestPage();
        $level1->URLSegment = 'test-level-1';
        $level1->write();
        $level1->publishRecursive();

        $level2_1 = new StaticPublisherTestPage();
        $level2_1->URLSegment = 'test-level-2-1';
        $level2_1->ParentID = $level1->ID;
        $level2_1->write();
        $level2_1->publishRecursive();

        $this->fsp->publishURL($level1->Link(), true);
        $this->fsp->publishURL($level2_1->Link(), true);
        $static2_1FilePath = $this->fsp->getDestPath() . $urlToPath->invokeArgs($this->fsp, [$level2_1->Link()]);

        $this->assertFileExists($static2_1FilePath . '.html');
        $this->assertFileExists($static2_1FilePath . '.php');
        $this->assertContains(
            'current',
            file_get_contents($static2_1FilePath . '.html')
        );

        $level2_2 = new StaticPublisherTestPage();
        $level2_2->URLSegment = 'test-level-2-2';
        $level2_2->ParentID = $level1->ID;
        $level2_2->write();
        $level2_2->publishRecursive();

        $this->fsp->publishURL($level2_2->Link(), true);
        $static2_2FilePath = $this->fsp->getDestPath() . $urlToPath->invokeArgs($this->fsp, [$level2_2->Link()]);

        $this->assertFileExists($static2_2FilePath . '.html');
        $this->assertFileExists($static2_2FilePath . '.php');
        $this->assertContains(
            'linkcurrent',
            file_get_contents($static2_2FilePath . '.html')
        );
    }

    public function testOnlyHTML()
    {
        $this->fsp->setFileExtension('html');

        $level1 = new StaticPublisherTestPage();
        $level1->URLSegment = 'mimetype';
        $level1->write();
        $level1->publishRecursive();

        $this->fsp->publishURL($level1->Link(), true);
        $staticFilePath = $this->fsp->getDestPath() . 'mimetype';

        $this->assertFileExists($staticFilePath . '.html');
        $this->assertFileNotExists($staticFilePath . '.php');
        $this->assertSame(
            '<div class="statically-published" style="display: none"></div>',
            trim(file_get_contents($staticFilePath . '.html'))
        );
    }

    public function testPurgeURL()
    {
        $level1 = new StaticPublisherTestPage();
        $level1->URLSegment = 'to-be-purged';
        $level1->write();
        $level1->publishRecursive();

        $this->fsp->publishURL('to-be-purged', true);
        $this->assertFileExists($this->fsp->getDestPath() . 'to-be-purged.html');
        $this->assertFileExists($this->fsp->getDestPath() . 'to-be-purged.php');

        $this->fsp->purgeURL('to-be-purged');
        $this->assertFileNotExists($this->fsp->getDestPath() . 'to-be-purged.html');
        $this->assertFileNotExists($this->fsp->getDestPath() . 'to-be-purged.php');
    }

    public function testPurgeURLAfterSwitchingExtensions()
    {
        $level1 = new StaticPublisherTestPage();
        $level1->URLSegment = 'purge-me';
        $level1->write();
        $level1->publishRecursive();

        $this->fsp->publishURL('purge-me', true);
        $this->assertFileExists($this->fsp->getDestPath() . 'purge-me.html');
        $this->assertFileExists($this->fsp->getDestPath() . 'purge-me.php');

        $this->fsp->setFileExtension('html');

        $this->fsp->purgeURL('purge-me');
        $this->assertFileNotExists($this->fsp->getDestPath() . 'purge-me.html');
        $this->assertFileNotExists($this->fsp->getDestPath() . 'purge-me.php');
    }

    public function testNoErrorPagesWhenHTMLOnly()
    {
        $this->fsp->setFileExtension('html');

        $this->fsp->publishURL('not_really_there', true);
        $this->assertFileNotExists($this->fsp->getDestPath() . 'not_really_there.html');
        $this->assertFileNotExists($this->fsp->getDestPath() . 'not_really_there.php');
    }

    public function testErrorPageWhenPHP()
    {
        $this->fsp->publishURL('not_really_there', true);
        $this->assertFileExists($this->fsp->getDestPath() . 'not_really_there.html');
        $this->assertFileExists($this->fsp->getDestPath() . 'not_really_there.php');
        $phpCacheConfig = require $this->fsp->getDestPath() . 'not_really_there.php';
        $this->assertEquals(404, $phpCacheConfig['responseCode']);
    }

    public function testRedirectorPageWhenPHP()
    {
        $redirectorPage = RedirectorPage::create();
        $redirectorPage->URLSegment = 'somewhere-else';
        $redirectorPage->RedirectionType = 'External';
        $redirectorPage->ExternalURL = 'silverstripe.org';
        $redirectorPage->write();
        $redirectorPage->publishRecursive();

        $this->fsp->publishURL('somewhere-else', true);

        $this->assertFileExists($this->fsp->getDestPath() . 'somewhere-else.html');
        $this->assertContains(
            'Click this link if your browser does not redirect you',
            file_get_contents($this->fsp->getDestPath() . 'somewhere-else.html')
        );
        $this->assertFileExists($this->fsp->getDestPath() . 'somewhere-else.php');
        $phpCacheConfig = require $this->fsp->getDestPath() . 'somewhere-else.php';
        $this->assertSame(301, $phpCacheConfig['responseCode']);
        $this->assertContains('location: http://silverstripe.org', $phpCacheConfig['headers']);
    }

    public function testRedirectorPageWhenHTMLOnly()
    {
        $this->fsp->setFileExtension('html');

        $redirectorPage = RedirectorPage::create();
        $redirectorPage->URLSegment = 'somewhere-else';
        $redirectorPage->RedirectionType = 'External';
        $redirectorPage->ExternalURL = 'silverstripe.org';
        $redirectorPage->write();
        $redirectorPage->publishRecursive();

        $this->fsp->publishURL('somewhere-else', true);

        $this->assertFileExists($this->fsp->getDestPath() . 'somewhere-else.html');
        $this->assertContains(
            'Click this link if your browser does not redirect you',
            file_get_contents($this->fsp->getDestPath() . 'somewhere-else.html')
        );
        $this->assertFileNotExists($this->fsp->getDestPath() . 'somewhere-else.php');
    }

    /**
     * @dataProvider providePathsToURL
     */
    public function testPathToURL($expected, $path)
    {
        $reflection = new \ReflectionClass(FilesystemPublisher::class);
        $pathToURL = $reflection->getMethod('pathToURL');
        $pathToURL->setAccessible(true);

        $this->assertSame(
            $expected,
            $pathToURL->invoke($this->fsp, $this->fsp->getDestPath() . $path)
        );
    }

    public function providePathsToURL()
    {
        return [
            ['http://example.com/', 'index.html'],
            ['http://example.com/about-us', 'about-us.html'],
            ['http://example.com/about-us', 'about-us.php'],
            ['http://example.com/parent/child', 'parent/child.html'],
        ];
    }

    public function testGetPublishedURLs()
    {
        $level1 = new StaticPublisherTestPage();
        $level1->URLSegment = 'find-me';
        $level1->write();
        $level1->publishRecursive();

        $this->fsp->publishURL('find-me', true);
        // We have to redeclare this config because the testkernel wipes it when we generate the page response
        Director::config()->set('alternate_base_url', 'http://example.com');

        $this->assertSame(['http://example.com/find-me'], $this->fsp->getPublishedURLs());

        $level2_1 = new StaticPublisherTestPage();
        $level2_1->URLSegment = 'find-me-child';
        $level2_1->ParentID = $level1->ID;
        $level2_1->write();
        $level2_1->publishRecursive();

        $this->fsp->publishURL($level2_1->Link(), true);
        Director::config()->set('alternate_base_url', 'http://example.com');

        $urls = $this->fsp->getPublishedURLs();
        $this->assertContains('http://example.com/find-me', $urls);
        $this->assertContains('http://example.com/find-me/find-me-child', $urls);
        $this->assertCount(2, $urls);

        $this->fsp->purgeURL('find-me');
        $this->assertSame(['http://example.com/find-me/find-me-child'], $this->fsp->getPublishedURLs());
    }
}
