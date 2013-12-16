<?php

/**
 * Tests for the {@link FilesystemPublisher} class.
 * 
 * @package staticpublisher
 */
class FilesystemPublisherTest extends SapphireTest {
	
	protected $usesDatabase = true;

	public function setUp() {
		parent::setUp();
		
		SiteTree::add_extension("FilesystemPublisher('assets/FilesystemPublisherTest-static-folder/')");
		
		Config::inst()->nest();
		Config::inst()->update('StaticPagesQueue', 'realtime', true);
		Config::inst()->update('FilesystemPublisher', 'domain_based_caching', false);
		Config::inst()->update('FilesystemPublisher', 'static_base_url', 'http://foo');
		Config::inst()->update('Director', 'alternate_base_url', 'http://foo/');
	}
	
	public function tearDown() {
		parent::tearDown();

		Config::inst()->unnest();

		SiteTree::remove_extension("FilesystemPublisher('assets/FilesystemPublisherTest-static-folder/')");

		if(file_exists(BASE_PATH . '/assets/FilesystemPublisherTest-static-folder')) {
			Filesystem::removeFolder(BASE_PATH . '/assets/FilesystemPublisherTest-static-folder');
		}

		// Purge DB from StaticPagesQueue items.
		self::empty_temp_db();
	}
	
	public function testUrlsToPathsWithRelativeUrls() {
		$fsp = new FilesystemPublisher('.', 'html');
		
		$this->assertEquals(
			$fsp->urlsToPaths(array('/')),
			array('/' => './index.html'),
			'Root URL path mapping'
		);
		
		$this->assertEquals(
			$fsp->urlsToPaths(array('about-us')),
			array('about-us' => './about-us.html'),
			'URLsegment path mapping'
		);
		
		$this->assertEquals(
			$fsp->urlsToPaths(array('parent/child')),
			array('parent/child' => 'parent/child.html'),
			'Nested URLsegment path mapping'
		);
	}
	
	public function testUrlsToPathsWithAbsoluteUrls() {
		$fsp = new FilesystemPublisher('.', 'html');
		
		$url = Director::absoluteBaseUrl();
		$this->assertEquals(
			$fsp->urlsToPaths(array($url)),
			array($url => './index.html'),
			'Root URL path mapping'
		);
		
		$url = Director::absoluteBaseUrl() . 'about-us';
		$this->assertEquals(
			$fsp->urlsToPaths(array($url)),
			array($url => './about-us.html'),
			'URLsegment path mapping'
		);
		
		$url = Director::absoluteBaseUrl() . 'parent/child';
		$this->assertEquals(
			$fsp->urlsToPaths(array($url)),
			array($url => 'parent/child.html'),
			'Nested URLsegment path mapping'
		);
	}

	public function testUrlsToPathsWithDomainBasedCaching() {
		$origDomainBasedCaching = Config::inst()->get('FilesystemPublisher', 'domain_based_caching');
		Config::inst()->update('FilesystemPublisher', 'domain_based_caching', true);
		
		$fsp = new FilesystemPublisher('.', 'html');
		
		$url = 'http://domain1.com/';
		$this->assertEquals(
			$fsp->urlsToPaths(array($url)),
			array($url => 'domain1.com/index.html'),
			'Root URL path mapping'
		);
		
		$url = 'http://domain1.com/about-us';
		$this->assertEquals(
			$fsp->urlsToPaths(array($url)),
			array($url => 'domain1.com/about-us.html'),
			'URLsegment path mapping'
		);
		
		$url = 'http://domain2.com/parent/child';
		$this->assertEquals(
			$fsp->urlsToPaths(array($url)),
			array($url => 'domain2.com/parent/child.html'),
			'Nested URLsegment path mapping'
		);
		
		Config::inst()->update('FilesystemPublisher', 'domain_based_caching', $origDomainBasedCaching);
	}
	
	/**
	 * Simple test to ensure that FileSystemPublisher::__construct()
	 * has called parent::__construct() by checking the class property.
	 * The class property is set on {@link Object::__construct()} and
	 * this is therefore a good test to ensure it was called.
	 * 
	 * If FilesystemPublisher doesn't call parent::__construct() then
	 * it won't be enabled propery because {@link Object::__construct()}
	 * is where extension instances are set up and subsequently used by
	 * {@link DataObject::defineMethods()}.
	 */
	public function testHasCalledParentConstructor() {
		$fsp = new FilesystemPublisher('.', '.html');

		$this->assertEquals($fsp->class, 'FilesystemPublisher');
	}
	
	/*
	 * These are a few simple tests to check that we will be retrieving the 
	 * correct theme when we need it. StaticPublishing needs to be able to 
	 * retrieve a non-null theme at the time publishPages() is called.
	 */
	public function testStaticPublisherTheme(){
		
		// This will be the name of the default theme of this particular project
		$default_theme = Config::inst()->get('SSViewer', 'theme');
		
		$p1 = new Page();
		$p1->URLSegment = strtolower(__CLASS__).'-page-1';
		$p1->HomepageForDomain = '';
		$p1->write();
		$p1->doPublish();
		
		$current_theme = Config::inst()->get('SSViewer', 'theme_enabled') ? Config::inst()->get('SSViewer', 'theme') : null;
		$this->assertEquals($current_theme, $default_theme, 'After a standard publication, the theme is correct');
		
		//We can set the static_publishing theme to something completely different:
		//Static publishing will use this one instead of the current_custom_theme if it is not false
		Config::inst()->update('FilesystemPublisher', 'static_publisher_theme', 'otherTheme');
		$current_theme = Config::inst()->get('FilesystemPublisher', 'static_publisher_theme');

		$this->assertNotEquals($current_theme, $default_theme, 'The static publisher theme overrides the custom theme');
	}

	public function testMenu2LinkingMode() { 
		$this->logInWithPermission('ADMIN'); 
		
		Config::inst()->update('SSViewer', 'theme', null);
		
		$l1 = new StaticPublisherTestPage(); 
		$l1->URLSegment = strtolower(__CLASS__).'-level-1'; 
 		$l1->write(); 
		$l1->doPublish(); 
	
		$l2_1 = new StaticPublisherTestPage(); 
		$l2_1->URLSegment = strtolower(__CLASS__).'-level-2-1'; 
		$l2_1->ParentID = $l1->ID; 
		$l2_1->write(); 
		
		$l2_1->doPublish(); 
		$response = Director::test($l2_1->AbsoluteLink());

		$this->assertEquals(trim($response->getBody()), "current", "current page is level 2-1"); 
                
		$l2_2 = new StaticPublisherTestPage(); 
		$l2_2->URLSegment = strtolower(__CLASS__).'-level-2-2'; 
		$l2_2->ParentID = $l1->ID; 
		$l2_2->write(); 
		$l2_2->doPublish();

		$response = Director::test($l2_2->AbsoluteLink()); 
		$this->assertEquals(trim($response->getBody()), "linkcurrent", "current page is level 2-2"); 
	}

	public function testContentTypeHTML() {
		StaticPublisherTestPage::remove_extension('FilesystemPublisher');
		StaticPublisherTestPage::add_extension("FilesystemPublisher('assets/FilesystemPublisherTest-static-folder/', 'php')");
		$l1 = new StaticPublisherTestPage();
		$l1->URLSegment = 'mimetype';
 		$l1->write();
		$l1->doPublish();
		$response = Director::test('mimetype');
		$this->assertEquals($response->getHeader('Content-Type'), 'text/html; charset=utf-8', 'Content-Type should be text/html; charset=utf-8');
		StaticPublisherTestPage::remove_extension('FilesystemPublisher');
		StaticPublisherTestPage::add_extension('FilesystemPublisher');
	}

	public function testContentTypeJSON() {
		StaticPublisherTestPage::remove_extension('FilesystemPublisher');
		StaticPublisherTestPage::add_extension("FilesystemPublisher('assets/FilesystemPublisherTest-static-folder/', 'php')");
		$l1 = new StaticPublisherTestPage();
		$l1->URLSegment = 'mimetype';
 		$l1->write();
		$l1->doPublish();
		$response = Director::test('mimetype/json');
		$this->assertEquals($response->getHeader('Content-Type'), 'application/json', 'Content-Type should be application/json');
		StaticPublisherTestPage::remove_extension('FilesystemPublisher');
		StaticPublisherTestPage::add_extension('FilesystemPublisher');
	}
}

/**
 * @package staticpublisher
 */
class StaticPublisherTestPage extends Page implements TestOnly {

	private static $allowed_children = array(
		'StaticPublisherTestPage'
	);


	public function canPublish($member = null) { 
		return true; 
	}

	public function getTemplate() {
		return STATIC_MODULE_DIR . '/tests/templates/StaticPublisherTestPage.ss';
	}
} 

/**
 * @package staticpublisher
 */
class StaticPublisherTestPage_Controller extends Page_Controller implements TestOnly {

	/**
	 *
	 * @var array
	 */
	private static $allowed_actions = array('json');
	
	public function json(SS_HTTPRequest $request) {
		$response = new SS_HTTPResponse('{"firstName": "John"}');
		$response->addHeader('Content-Type', 'application/json');
		return $response;

	}

}
