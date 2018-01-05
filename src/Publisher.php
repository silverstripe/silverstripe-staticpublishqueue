<?php

namespace SilverStripe\StaticPublishQueue;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPApplication;
use SilverStripe\Control\HTTPRequestBuilder;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\CoreKernel;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\StaticPublishQueue\Contract\StaticPublisher;
use SilverStripe\View\Requirements;
use SilverStripe\View\Requirements_Backend;
use SilverStripe\View\SSViewer;

abstract class Publisher implements StaticPublisher
{
    use Injectable;
    use Configurable;

    /**
     * @var array
     * @config
     */
    private static $static_publisher_themes = [];

    /**
     * @var string
     *
     * @config
     */
    private static $static_base_url = null;

    /**
     * @config
     *
     * @var Boolean Use domain based cacheing (put cache files into a domain subfolder)
     * This must be true if you are using this with the "subsites" module.
     * Please note that this form of caching requires all URLs to be provided absolute
     * (not relative to the webroot) via {@link SiteTree->AbsoluteLink()}.
     */
    private static $domain_based_caching = false;

    /**
     * @param string $url
     * @return HTTPResponse
     */
    public function generatePageResponse($url)
    {
        if ($url == "") {
            $url = "/";
        }
        if (Director::is_relative_url($url)) {
            $url = Director::absoluteURL($url);
        }
        $urlParts = parse_url($url);
        if (!empty($urlParts['query'])) {
            parse_str($urlParts['query'], $getVars);
        } else {
            $getVars = [];
        }
        // back up requirements backend
        $origRequirements = Requirements::backend();
        Requirements::set_backend(Requirements_Backend::create());
        $themes = self::config()->get('static_publisher_themes');
        if ($themes) {
            SSViewer::set_themes($themes);
        }
        try {
            // try to add all the server vars that would be needed to create a static cache
            $request = HTTPRequestBuilder::createFromVariables(
                [
                '_SERVER' => [
                    'REQUEST_URI' => isset($urlParts['path']) ? $urlParts['path'] : '',
                    'REQUEST_METHOD' => 'GET',
                    'REMOTE_ADDR' => '127.0.0.1',
                    'HTTPS' => $urlParts['scheme'] == 'https' ? 'on' : 'off',
                    'QUERY_STRING' => isset($urlParts['query']) ? $urlParts['query'] : '',
                    'REQUEST_TIME' => DBDatetime::now()->getTimestamp(),
                    'REQUEST_TIME_FLOAT' => (float) DBDatetime::now()->getTimestamp(),
                    'HTTP_HOST' => $urlParts['host'],
                    'HTTP_USER_AGENT' => 'silverstripe/staticpublisher',
                ],
                '_GET' => $getVars,
                '_POST' => [],
                ],
                ''
            );
            $kernel = new CoreKernel(BASE_PATH);
            $app = new HTTPApplication($kernel);
            $response = $app->handle($request);
        } catch (HTTPResponse_Exception $e) {
            $response = $e->getResponse();
        } finally {
            // restore backends
            SSViewer::set_themes(null);
            Requirements::set_backend($origRequirements);
            DataObject::singleton()->flushCache();
        }
        return $response;
    }

    /**
     * Generate the templated content for a PHP script that can serve up the
     * given piece of content with the given age and expiry.
     *
     * @param string $content
     * @param string $age
     * @param string $lastModified
     * @param string $contentType
     * @param int    $statusCode
     *
     * @return string
     */
    protected function generatePHPCacheFile($content, $age, $lastModified, $contentType, $statusCode)
    {
        $templateResource = ModuleLoader::getModule('silverstripe/staticpublishqueue')
            ->getResource('templates/CachedPHPPage.tmpl');
        $template = file_get_contents($templateResource->getPath());
        return str_replace(
            array('**MAX_AGE**', '**LAST_MODIFIED**', '**CONTENT**', '**CONTENT_TYPE**', '**RESPONSE_CODE**'),
            array((int)$age, $lastModified, $content, $contentType, $statusCode),
            $template
        );
    }

    /**
     * Generate the templated content for a PHP script that can serve up a 301
     * redirect to the given destination.
     *
     * @param string $destination
     *
     * @return string
     */
    protected function generatePHPCacheRedirection($destination, $statusCode)
    {
        $templateResource = ModuleLoader::getModule('silverstripe/staticpublishqueue')
            ->getResource('templates/CachedPHPRedirection.tmpl');
        $template = file_get_contents($templateResource->getPath());

        return str_replace(
            array('**DESTINATION**', '**STATUS_CODE**'),
            array($destination, $statusCode),
            $template
        );
    }

    /**
     * @param string $destination
     * @return string
     */
    protected function generateHTMLCacheRedirection($destination)
    {
        $templateResource = ModuleLoader::getModule('silverstripe/staticpublishqueue')
            ->getResource('templates/CachedHTMLRedirection.tmpl');
        $template = file_get_contents($templateResource->getPath());

        return str_replace(
            ['**DESTINATION**'],
            [$destination],
            $template
        );
    }

    /**
     * @param string $url
     *
     * @return array
     */
    public function getMetadata($url)
    {
        return array(
            'Cache generated on ' . date('Y-m-d H:i:s T (O)', DBDatetime::now()->getTimestamp())
        );
    }
}
