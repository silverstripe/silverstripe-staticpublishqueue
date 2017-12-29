<?php

namespace SilverStripe\StaticPublishQueue\Test;

use Page;
use SilverStripe\Assets\Filesystem;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Extension\Publisher\FilesystemPublisher;
use SilverStripe\StaticPublishQueue\Model\StaticPagesQueue;
use SilverStripe\StaticPublishQueue\Test\StaticPublisherTest\Model\StaticPublisherTestPage;
use SilverStripe\View\SSViewer;

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

        SiteTree::add_extension("FilesystemPublisher('assets/FilesystemPublisherTest-static-folder/')");

        Config::modify()->set(StaticPagesQueue::class, 'realtime', true);
        Config::modify()->set(FilesystemPublisher::class, 'domain_based_caching', false);
        Config::modify()->set(FilesystemPublisher::class, 'static_base_url', 'http://foo');
        Config::modify()->set(Director::class, 'alternate_base_url', 'http://foo/');
    }

    protected function tearDown()
    {
        SiteTree::remove_extension("FilesystemPublisher('assets/FilesystemPublisherTest-static-folder/')");

        if (file_exists(BASE_PATH . '/assets/FilesystemPublisherTest-static-folder')) {
            Filesystem::removeFolder(BASE_PATH . '/assets/FilesystemPublisherTest-static-folder');
        }

        parent::tearDown();
    }

    public function testUrlsToPathsWithRelativeUrls()
    {
        $fsp = new FilesystemPublisher('.', 'html');

        $this->assertEquals(
            array('/' => './index.html'),
            $fsp->urlsToPaths(array('/'))
        );

        $this->assertEquals(
            array('about-us' => './about-us.html'),
            $fsp->urlsToPaths(array('about-us'))
        );

        $this->assertEquals(
            array('parent/child' => 'parent/child.html'),
            $fsp->urlsToPaths(array('parent/child'))
        );
    }

    public function testUrlsToPathsWithAbsoluteUrls()
    {
        $fsp = new FilesystemPublisher('.', 'html');

        $url = Director::absoluteBaseUrl();
        $this->assertEquals(
            array($url => './index.html'),
            $fsp->urlsToPaths(array($url))
        );

        $url = Director::absoluteBaseUrl() . 'about-us';
        $this->assertEquals(
            array($url => './about-us.html'),
            $fsp->urlsToPaths(array($url))
        );

        $url = Director::absoluteBaseUrl() . 'parent/child';
        $this->assertEquals(
            array($url => 'parent/child.html'),
            $fsp->urlsToPaths(array($url))
        );
    }

    public function testUrlsToPathsWithDomainBasedCaching()
    {
        $fsp = new FilesystemPublisher('.', 'html');

        $url = 'http://domain1.com/';
        $this->assertEquals(
            array($url => 'domain1.com/index.html'),
            $fsp->urlsToPaths(array($url))
        );

        $url = 'http://domain1.com/about-us';
        $this->assertEquals(
            array($url => 'domain1.com/about-us.html'),
            $fsp->urlsToPaths(array($url))
        );

        $url = 'http://domain2.com/parent/child';
        $this->assertEquals(
            array($url => 'domain2.com/parent/child.html'),
            $fsp->urlsToPaths(array($url))
        );
    }

    /**
     * These are a few simple tests to check that we will be retrieving the
     * correct theme when we need it. StaticPublishing needs to be able to
     * retrieve a non-null theme at the time publishPages() is called.
     */
    public function testStaticPublisherTheme()
    {

        // @todo - this needs re-working for 4.x cascading themes
        // This will be the name of the default theme of this particular project
        $default_theme = Config::inst()->get(SSViewer::class, 'theme');

        $p1 = new Page();
        $p1->URLSegment = strtolower(__CLASS__) . '-page-1';
        $p1->HomepageForDomain = '';
        $p1->write();
        $p1->publishRecursive();

        $current_theme = Config::inst()->get(SSViewer::class, 'theme_enabled') ? Config::inst()->get(SSViewer::class, 'theme') : null;
        $this->assertEquals($default_theme, $current_theme);

        //We can set the static_publishing theme to something completely different:
        //Static publishing will use this one instead of the current_custom_theme if it is not false
        Config::modify()->set(FilesystemPublisher::class, 'static_publisher_theme', 'otherTheme');
        $current_theme = Config::inst()->get(FilesystemPublisher::class, 'static_publisher_theme');

        $this->assertNotEquals($default_theme, $current_theme);
    }

    public function testMenu2LinkingMode()
    {
        $this->logInWithPermission('ADMIN');

        Config::modify()->set(SSViewer::class, 'theme', null);

        $l1 = new StaticPublisherTestPage();
        $l1->URLSegment = strtolower(__CLASS__) . '-level-1';
        $l1->write();
        $l1->publishRecursive();

        $l2_1 = new StaticPublisherTestPage();
        $l2_1->URLSegment = strtolower(__CLASS__) . '-level-2-1';
        $l2_1->ParentID = $l1->ID;
        $l2_1->write();

        $l2_1->publishRecursive();

        try {
            $response = Director::test($l2_1->AbsoluteLink());
        } catch (HTTPResponse_Exception $e) {
            $response = $e->getResponse();
        }

        $this->assertEquals("current", trim($response->getBody()));

        $l2_2 = new StaticPublisherTestPage();
        $l2_2->URLSegment = strtolower(__CLASS__) . '-level-2-2';
        $l2_2->ParentID = $l1->ID;
        $l2_2->write();
        $l2_2->publishRecursive();

        try {
            $response = Director::test($l2_2->AbsoluteLink());
        } catch (HTTPResponse_Exception $e) {
            $response = $e->getResponse();
        }
        $this->assertEquals("linkcurrent", trim($response->getBody()));
    }

    public function testContentTypeHTML()
    {
        StaticPublisherTestPage::remove_extension('FilesystemPublisher');
        StaticPublisherTestPage::add_extension("FilesystemPublisher('assets/FilesystemPublisherTest-static-folder/', 'php')");
        $l1 = new StaticPublisherTestPage();
        $l1->URLSegment = 'mimetype';
        $l1->write();
        $l1->publishRecursive();
        try {
            $response = Director::test('mimetype');
        } catch (HTTPResponse_Exception $e) {
            $response = $e->getResponse();
        }
        $this->assertEquals('text/html; charset=utf-8', $response->getHeader('Content-Type'));
        StaticPublisherTestPage::remove_extension('FilesystemPublisher');
        StaticPublisherTestPage::add_extension('FilesystemPublisher');
    }

    public function testContentTypeJSON()
    {
        StaticPublisherTestPage::remove_extension('FilesystemPublisher');
        StaticPublisherTestPage::add_extension("FilesystemPublisher('assets/FilesystemPublisherTest-static-folder/', 'php')");
        $l1 = new StaticPublisherTestPage();
        $l1->URLSegment = 'mimetype';
        $l1->write();
        $l1->publishRecursive();
        try {
            $response = Director::test('mimetype/json');
        } catch (HTTPResponse_Exception $e) {
            $response = $e->getResponse();
        }
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        StaticPublisherTestPage::remove_extension('FilesystemPublisher');
        StaticPublisherTestPage::add_extension('FilesystemPublisher');
    }
}
