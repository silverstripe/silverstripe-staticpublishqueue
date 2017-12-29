<?php

namespace SilverStripe\StaticPublishQueue\Test;

use PHPUnit_Framework_Assert;
use ReflectionMethod;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Extension\Engine\SiteTreePublishingEngine;
use SilverStripe\StaticPublishQueue\Model\URLArrayObject;
use SilverStripe\StaticPublishQueue\Test\URLArrayObjectTest\Model\URLArrayObjectTestPage;

/**
 * @mixin PHPUnit_Framework_Assert
 */
class URLArrayObjectTest extends SapphireTest
{

    protected static $fixture_file = 'URLArrayObjectTestFixture.yml';
    protected static $extra_dataobjects = array(URLArrayObjectTestPage::class);
    protected static $required_extensions = array(
    SiteTree::class => array(SiteTreePublishingEngine::class),
    );

    public function testExcludeFromCacheExcludesPageURLs()
    {
        /**
 * @var URLArrayObject $urlArrayObject
*/
        $urlArrayObject = singleton('SiteTree')->urlArrayObject;
        $method = $this->getProtectedOrPrivateMethod('excludeFromCache');

        /**
 * @var SiteTree $excluded
*/
        $excluded = $this->objFromFixture(URLArrayObjectTestPage::class, 'excluded');
        /**
 * @var SiteTree $included
*/
        $included = $this->objFromFixture(URLArrayObjectTestPage::class, 'included');

        $this->assertTrue($method->invoke($urlArrayObject, $excluded->Link()));
        $this->assertFalse($method->invoke($urlArrayObject, $included->Link()));
    }

    public function testExcludeFromCacheExcludesPageURLsWithQueryStrings()
    {
        /**
 * @var URLArrayObject $urlArrayObject
*/
        $urlArrayObject = SiteTree::singleton()->urlArrayObject;
        $method = $this->getProtectedOrPrivateMethod('excludeFromCache');

        /**
 * @var SiteTree $excluded
*/
        $excluded = $this->objFromFixture(URLArrayObjectTestPage::class, 'excluded');
        /**
 * @var SiteTree $included
*/
        $included = $this->objFromFixture(URLArrayObjectTestPage::class, 'included');

        $this->assertTrue($method->invoke($urlArrayObject, $excluded->Link() . '?query=string'));
        $this->assertFalse($method->invoke($urlArrayObject, $included->Link() . '?query=string'));
    }

    public function testExcludeFromCachePrefersObjectAnnotationOverUrls()
    {
        /**
 * @var URLArrayObject $urlArrayObject
*/
        $urlArrayObject = singleton('SiteTree')->urlArrayObject;
        $method = $this->getProtectedOrPrivateMethod('excludeFromCache');

        /**
 * @var SiteTree $excluded
*/
        $excluded = $this->objFromFixture(URLArrayObjectTestPage::class, 'excluded');
        /**
 * @var SiteTree $included
*/
        $included = $this->objFromFixture(URLArrayObjectTestPage::class, 'included');

        $actuallyExcludedUrl = $included->RelativeLink . "?_ID=$excluded->ID&_ClassName=$excluded->ClassName";
        $actuallyIncludedUrl = $excluded->RelativeLink . "?_ID=$included->ID&_ClassName=$included->ClassName";
        $this->assertTrue($method->invoke($urlArrayObject, $actuallyExcludedUrl));
        $this->assertFalse($method->invoke($urlArrayObject, $actuallyIncludedUrl));
    }

    private function getProtectedOrPrivateMethod($name)
    {
        $method = new ReflectionMethod(URLArrayObject::class, $name);
        $method->setAccessible(true);

        return $method;
    }
}
