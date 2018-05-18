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
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\StaticPublishQueue\Contract\StaticPublisher;
use SilverStripe\View\ArrayData;
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
     * @config
     *
     * @var Boolean Use domain based caching (put cache files into a domain subfolder)
     * This must be true if you are using this with the "subsites" module.
     * Please note that this form of caching requires all URLs to be provided absolute
     * (not relative to the webroot) via {@link SiteTree->AbsoluteLink()}.
     */
    private static $domain_based_caching = false;

    /**
     * @config
     *
     * @var Boolean Add a timestamp to the statically published output for HTML files
     */
    private static $add_timestamp = false;

    /**
     * @param string $url
     *
     * @return HTTPResponse
     */
    public function generatePageResponse($url)
    {
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

        $origThemes = SSViewer::get_themes();
        $staticThemes = self::config()->get('static_publisher_themes');
        if ($staticThemes) {
            SSViewer::set_themes($staticThemes);
        } else {
            // get the themes raw from config to prevent the "running from the CMS" problem where no themes are live
            $rawThemes = SSViewer::config()->uninherited('themes');
            SSViewer::set_themes($rawThemes);
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
                        'REQUEST_TIME_FLOAT' => (float)DBDatetime::now()->getTimestamp(),
                        'HTTP_HOST' => $urlParts['host'],
                        'HTTP_USER_AGENT' => 'silverstripe/staticpublishqueue',
                    ],
                    '_GET' => $getVars,
                    '_POST' => [],
                ],
                ''
            );
            $app = $this->getHTTPApplication();
            $response = $app->handle($request);

            if ($this->config()->get('add_timestamp') === true) {
                $response->setBody(
                    str_replace(
                        '</html>',
                        "<!-- " . DBDateTime::now()->Full() . " -->\n</html>",
                        $response->getBody()
                        )
                    );
            }
        } catch (HTTPResponse_Exception $e) {
            $response = $e->getResponse();
        } finally {
            // restore backends
            SSViewer::set_themes($origThemes);
            Requirements::set_backend($origRequirements);
            DataObject::singleton()->flushCache();
        }
        return $response;
    }

    /**
     * @return HTTPApplication
     */
    protected function getHTTPApplication()
    {
        $kernel = new CoreKernel(BASE_PATH);
        return new HTTPApplication($kernel);
    }

    /**
     * Generate the templated content for a PHP script that can serve up the
     * given piece of content with the given age and expiry.
     *
     * @param HTTPResponse $response
     *
     * @return string
     */
    protected function generatePHPCacheFile($response)
    {
        $cacheConfig = [
            'responseCode' => $response->getStatusCode(),
            'headers' => [],
        ];

        foreach ($response->getHeaders() as $header => $value) {
            if (!in_array($header, ['cache-control'])) {
                $cacheConfig['headers'][] = sprintf('%s: %s', $header, $value);
            }
        }

        return "<?php\n\nreturn " . var_export($cacheConfig, true) . ';';
    }

    /**
     * @param string $destination
     *
     * @return string
     */
    protected function generateHTMLCacheRedirection($destination)
    {
        return SSViewer::execute_template(
            'SilverStripe\\StaticPublishQueue\\HTMLRedirection',
            ArrayData::create([
                'URL' => DBField::create_field('Varchar', $destination),
            ])
        );
    }
}
