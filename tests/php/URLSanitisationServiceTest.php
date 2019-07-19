<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Service\URLSanitisationService;

/**
 * Class URLSanitisationServiceTest
 *
 * @package SilverStripe\StaticPublishQueue\Test
 */
class URLSanitisationServiceTest extends SapphireTest
{
    /**
     * @param bool $formatted
     * @param array $urls
     * @param array $expected
     * @dataProvider formattedUrlsProvider
     */
    public function testFormatting($formatted, array $urls, array $expected)
    {
        $urlsService = URLSanitisationService::create();
        $urlsService->addURLs($urls);
        $urls = $urlsService->getURLs($formatted);

        $this->assertEquals($expected, $urls);
    }

    /**
     * @param bool $ssl
     * @param array $urls
     * @param array $expected
     * @dataProvider transformationUrlsProvider
     */
    public function testTransformation($ssl, array $urls, array $expected)
    {
        if ($ssl) {
            Config::modify()->set(URLSanitisationService::class, 'force_ssl', true);
        }

        // normal transformation (protocol change)
        $urlsService = URLSanitisationService::create();
        $urlsService->addURLs($urls);
        $urls = $urlsService->getURLs();

        $this->assertEquals($expected, $urls);

        $urlsService = URLSanitisationService::create();
        $urlsService->addURLs($urls);
        $this->assertEquals($expected, $urlsService->getURLs());
    }

    public function transformationUrlsProvider()
    {
        return [
            [
                // protocol change
                false,
                [
                    'http://some-locale/some-page/',
                ],
                [
                    'http://some-locale/some-page/',
                ],
            ],
            [
                // protocol change
                true,
                [
                    'http://some-locale/some-page/',
                ],
                [
                    'https://some-locale/some-page/',
                ],
            ],
            [
                // enforce trailing slash
                false,
                [
                    'http://some-locale/some-page',
                ],
                [
                    'http://some-locale/some-page/',
                ],
            ],
            [
                // enforce trailing slash
                true,
                [
                    'http://some-locale/some-page',
                ],
                [
                    'https://some-locale/some-page/',
                ],
            ],
        ];
    }

    public function formattedUrlsProvider()
    {
        return [
            [
                false,
                [
                    'http://some-locale/some-page/',
                    'http://some-locale/some-other-page/',
                ],
                [
                    'http://some-locale/some-page/',
                    'http://some-locale/some-other-page/',
                ]
            ],
            [
                true,
                [
                    'http://some-locale/some-page/',
                    'http://some-locale/some-other-page/',
                ],
                [
                    'http://some-locale/some-page/' => 0,
                    'http://some-locale/some-other-page/' => 1,
                ],
            ],
        ];
    }
}
