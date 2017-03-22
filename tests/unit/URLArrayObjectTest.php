<?php

/**
 * @mixin PHPUnit_Framework_Assert
 */
class URLArrayObjectTest extends SapphireTest {

	protected static $fixture_file = 'URLArrayObjectTestFixture.yml';
	protected $extraDataObjects = array('URLArrayObjectTestPage');
	protected $requiredExtensions = array(
		'SiteTree' => array('SiteTreePublishingEngine')
	);

	public function testExcludeFromCacheExcludesPageURLs() {
		/** @var URLArrayObject $urlArrayObject */
		$urlArrayObject = singleton('SiteTree')->urlArrayObject;
		$method = $this->getProtectedOrPrivateMethod('excludeFromCache');

		/** @var SiteTree $excluded */
		$excluded = $this->objFromFixture('URLArrayObjectTestPage', 'excluded');
		/** @var SiteTree $included */
		$included = $this->objFromFixture('URLArrayObjectTestPage', 'included');

		$this->assertEquals(true, $method->invoke($urlArrayObject, $excluded->Link()));
		$this->assertEquals(false, $method->invoke($urlArrayObject, $included->Link()));
	}

	public function testExcludeFromCacheExcludesPageURLsWithQueryStrings() {
		/** @var URLArrayObject $urlArrayObject */
		$urlArrayObject = singleton('SiteTree')->urlArrayObject;
		$method = $this->getProtectedOrPrivateMethod('excludeFromCache');

		/** @var SiteTree $excluded */
		$excluded = $this->objFromFixture('URLArrayObjectTestPage', 'excluded');
		/** @var SiteTree $included */
		$included = $this->objFromFixture('URLArrayObjectTestPage', 'included');

		$this->assertEquals(true, $method->invoke($urlArrayObject, $excluded->Link() . '?query=string'));
		$this->assertEquals(false, $method->invoke($urlArrayObject, $included->Link() . '?query=string'));
	}

	public function testExcludeFromCachePrefersObjectAnnotationOverUrls() {
		/** @var URLArrayObject $urlArrayObject */
		$urlArrayObject = singleton('SiteTree')->urlArrayObject;
		$method = $this->getProtectedOrPrivateMethod('excludeFromCache');

		/** @var SiteTree $excluded */
		$excluded = $this->objFromFixture('URLArrayObjectTestPage', 'excluded');
		/** @var SiteTree $included */
		$included = $this->objFromFixture('URLArrayObjectTestPage', 'included');

		$actuallyExcludedUrl = $included->RelativeLink . "?_ID=$excluded->ID&_ClassName=$excluded->ClassName";
		$actuallyIncludedUrl = $excluded->RelativeLink . "?_ID=$included->ID&_ClassName=$included->ClassName";
		$this->assertEquals(true, $method->invoke($urlArrayObject, $actuallyExcludedUrl));
		$this->assertEquals(false, $method->invoke($urlArrayObject, $actuallyIncludedUrl));
	}

	private function getProtectedOrPrivateMethod($name) {
		$method = new ReflectionMethod('URLArrayObject', $name);
		$method->setAccessible(true);

		return $method;
	}
}

class URLArrayObjectTestPage extends SiteTree implements TestOnly
{
	private static $db = array(
		'excludeFromCache' => 'Boolean'
	);
}
