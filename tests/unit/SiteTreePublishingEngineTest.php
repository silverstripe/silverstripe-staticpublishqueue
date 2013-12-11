<?php

class SiteTreePublishingEngineTest extends SapphireTest {

	protected $requiredExtensions = array(
		'SiteTreePublishingEngineTest_StaticallyPublishable' => array(
			'SiteTreePublishingEngine',
			'FilesystemPublisher'
		),
		'SiteTreePublishingEngineTest_StaticPublishingTrigger' => array(
			'SiteTreePublishingEngine',
			'FilesystemPublisher'
		)
	);

	public function setUp() {
		parent::setUp();
		Config::inst()->nest();
		Config::inst()->update('StaticPagesQueue', 'realtime', true);
	}

	public function tearDown() {
		Config::inst()->unnest();
		parent::tearDown();
	}
	
	function testCollectChangesForPublishing() {

		$obj = Object::create('SiteTreePublishingEngineTest_StaticPublishingTrigger');
		$obj->collectChanges(array('action'=>'publish'));

		$this->assertEquals(
			$obj->getToUpdate(),
			array('/updateOnPublish?_ID=1&_ClassName=StaticallyPublishableTest'=>10)
		);
		$this->assertEquals(
			$obj->getToDelete(),
			array('/deleteOnPublish?_ID=1&_ClassName=StaticallyPublishableTest'=>10)
		);

	}

	function testCollectChangesForUnpublishing() {

		$obj = Object::create('SiteTreePublishingEngineTest_StaticPublishingTrigger');
		$obj->collectChanges(array('action'=>'unpublish'));

		$this->assertEquals(
			$obj->getToUpdate(),
			array('/updateOnUnpublish?_ID=1&_ClassName=StaticallyPublishableTest'=>10)
		);
		$this->assertEquals(
			$obj->getToDelete(),
			array('/deleteOnUnpublish?_ID=1&_ClassName=StaticallyPublishableTest'=>10)
		);

	}

	function testFlushChangesToUpdateEnqueuesAndDeletesRegular() {

		$toUpdate = array('/toUpdate?_ID=1&_ClassName=StaticallyPublishableTest'=>10);

		$stub = $this->getMock(
			'SiteTreePublishingEngineTest_StaticPublishingTrigger',
			array('convertUrlsToPathMap', 'deleteRegularFiles', 'deleteStaleFiles')
		);
		$stub->expects($this->once())
			->method('convertUrlsToPathMap')
			->will($this->returnValue(array('url'=>'file')));

		// Test: enqueues the updated URLs
		$stub->setUrlArrayObject($this->getMock('URLArrayObject', array('addUrls')));
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
		$this->assertEquals($stub->getToUpdate(), array(), 'The update cache has been flushed.');

	}

	function testFlushChangesToDeleteDeletesRegularAndStale() {

		$toDelete = array('/toDelete?_ID=1&_ClassName=StaticallyPublishableTest'=>10);

		$stub = $this->getMock(
			'SiteTreePublishingEngineTest_StaticPublishingTrigger',
			array('convertUrlsToPathMap', 'deleteRegularFiles', 'deleteStaleFiles')
		);
		$stub->expects($this->once())
			->method('convertUrlsToPathMap')
			->will($this->returnValue(array('url'=>'file')));

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
		$this->assertEquals($stub->getToDelete(), array(), 'The delete cache has been flushed.');

	}

	function testConvertUrlsToPathMapNoObject() {
		Config::inst()->nest();
		Config::inst()->update('FilesystemPublisher', 'static_base_url', 'http://foo');
		Config::inst()->update('Director', 'alternate_base_url', '/');

		$urls = array('/xyzzy');

		$stub = Object::create('SiteTreePublishingEngineTest_StaticPublishingTrigger');

		// Test (inclusively with urlsToPaths, these interfaces should be refactored together)
		$result = $stub->convertUrlsToPathMap($urls);
		$this->assertEquals($result, array(
			'/xyzzy' => './xyzzy.html'
		));

		Config::inst()->unnest();
	}

	function testConvertUrlsToPathMapMainSite() {
		Config::inst()->nest();
		Config::inst()->update('FilesystemPublisher', 'static_base_url', 'http://foo');
		Config::inst()->update('Director', 'alternate_base_url', '/');
		$urls = array('/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable');

		// Pretend this object supports subsites, and is from the main site.
		$page = $this->getMock('SiteTreePublishingEngineTest_StaticallyPublishable', array('Subsite', 'hasExtension'));
		$page->expects($this->any())
			->method('Subsite')
			->will($this->returnValue(null));
		$page->expects($this->any())
			->method('hasExtension')
			->will($this->returnValue(true));

		$stub = Object::create('SiteTreePublishingEngineTest_StaticPublishingTrigger');

		$stub->setUrlArrayObject($this->getMock('URLArrayObject', array('getObject')));
		$stub->getUrlArrayObject()->expects($this->any())
			->method('getObject')
			->will($this->returnValue(
				$page
			));

		// Test (inclusively with urlsToPaths, these interfaces should be refactored together)
		$result = $stub->convertUrlsToPathMap($urls);
		$this->assertEquals(
			$result,
			array('http://foo/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable' =>
				'foo/xyzzy.html')
		);

		Config::inst()->unnest();
	}

	function testConvertUrlsToPathMapSubsite() {
		Config::inst()->nest();
		Config::inst()->update('FilesystemPublisher', 'static_base_url', 'http://foo');
		Config::inst()->update('Director', 'alternate_base_url', '/');
		$urls = array('/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable');

		// Mock a set of objects pretending to support Subsites. Subsites might not be installed.
		$domain1 = $this->getMock('SubsiteDomain_mock', array('Domain'));
		$domain1->Domain = 'subiste1.domain.org';
		$domain2 = $this->getMock('SubsiteDomain_mock', array('Domain'));
		$domain2->Domain = 'subiste2.domain.org';

		$domains = Object::create('ArrayList', array($domain1, $domain2));

		$subsite = $this->getMock('Subsite_mock', array('Domains', 'ID'));
		$subsite->expects($this->any())
			->method('Domains')
			->will($this->returnValue($domains));
		$subsite->ID = 1;

		$stub = $this->getMock('SiteTreePublishingEngineTest_StaticallyPublishable', array('Subsite', 'hasExtension'));
		$stub->expects($this->any())
			->method('Subsite')
			->will($this->returnValue($subsite));
		$stub->expects($this->any())
			->method('hasExtension')
			->will($this->returnValue(true));

		// Prepare static mocks.
		$stub->setUrlArrayObject($this->getMock('URLArrayObject', array('getObject')));
		$stub->getUrlArrayObject()->expects($this->any())
			->method('getObject')
			->will($this->returnValue($stub));

		// Test (inclusively with urlsToPaths, these interfaces should be refactored together)
		$result = $stub->convertUrlsToPathMap($urls);
		$this->assertEquals(
			$result,
			array(
				'http://subiste1.domain.org/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable' =>
				"subiste1.domain.org/xyzzy.html",
				'http://subiste2.domain.org/xyzzy?_ID=1&_ClassName=SiteTreePublishingEngineTest_StaticallyPublishable' =>
				"subiste2.domain.org/xyzzy.html"
			)
		);

		Config::inst()->unnest();

	}

	function testDeleteStaleFiles() {

		$stub = $this->getMock(
			'SiteTreePublishingEngineTest_StaticPublishingTrigger',
			array('deleteFromCacheDir')
		);
		$stub->expects($this->at(0))
			->method('deleteFromCacheDir')
			->with($this->equalTo('xyzzy.stale.html'));
		$stub->expects($this->at(1))
			->method('deleteFromCacheDir')
			->with($this->equalTo('foo/bar/baz.stale.html'));

		$stub->deleteStaleFiles(array('xyzzy.html', 'foo/bar/baz.html'));

	}
}

class SiteTreePublishingEngineTest_StaticallyPublishable extends SiteTree implements TestOnly, StaticallyPublishable {

	public $url;
	public $prio;

	public function getClassName() {
		return 'StaticallyPublishableTest';
	}

	public function getID() {
		return '1';
	}

	public function urlsToCache() {
		return array($this->url => $this->prio);
	}

}

class SiteTreePublishingEngineTest_StaticPublishingTrigger extends SiteTree implements TestOnly, StaticPublishingTrigger {

	public function generatePublishable($url, $prio) {
		$obj = Object::create('SiteTreePublishingEngineTest_StaticallyPublishable');
		$obj->url = $url;
		$obj->prio = $prio;

		return $obj;
	}

	public function objectsToUpdate($context) {

		switch ($context['action']) {
			case 'publish':
				return new ArrayList(array($this->generatePublishable('/updateOnPublish', 10)));
			case 'unpublish':
				return new ArrayList(array($this->generatePublishable('/updateOnUnpublish', 10)));
		}

	}

	/**
	 * Remove the object on unpublishing (the parent will get updated via objectsToUpdate).
	 */
	public function objectsToDelete($context) {

		switch ($context['action']) {
			case 'publish':
				return new ArrayList(array($this->generatePublishable('/deleteOnPublish', 10)));
			case 'unpublish':
				return new ArrayList(array($this->generatePublishable('/deleteOnUnpublish', 10)));
		}

	}

}
