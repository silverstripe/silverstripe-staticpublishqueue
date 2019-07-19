<?php

namespace SilverStripe\StaticPublishQueue\Service;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class URLSanitisationService
 * Provides an abstraction for populating and generating a list of
 * URLs to be cached by StaticPublishQueue.
 *
 * @package SilverStripe\StaticPublishQueue\Service
 */
class URLSanitisationService
{
    use Configurable;
    use Injectable;

    /**
     * Enable to force all URLs to be changed to https
     *
     * @var bool
     * @config
     */
    private static $force_ssl = false;

    /**
     * @var array
     */
    private $urls = [];

    /**
     * @var int
     */
    private $pointer = 0;

    /**
     * @param string $url
     */
    public function addURL(string $url): void
    {
        $url = $this->enforceTrailingSlash($url);
        $url = $this->enforceSSL($url);

        // Check if this URL is already represented.
        if (array_key_exists($url, $this->urls)) {
            // Don't need to add it again if it is.
            return;
        }

        $this->urls[$url] = $this->pointer;
        $this->pointer += 1;
    }

    /**
     * @param array $urls
     */
    public function addURLs(array $urls): void
    {
        foreach ($urls as $url) {
            $this->addURL($url);
        }
    }

    /**
     * @return array
     */
    public function getURLs(bool $formatted = false): array
    {
        if ($formatted) {
            return $this->urls;
        }

        return array_keys($this->urls);
    }

    /**
     * Make sure we don't have multiple variations of the same URL (with and without trailing slash)
     *
     * @param string $url
     * @return string
     */
    protected function enforceTrailingSlash(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        $queryString = strlen($query) > 0
            ? '?' . $query
            : '';

        $url = str_replace('?' . $query, '', $url);
        $url = rtrim($url, '/') . '/';

        return $url . $queryString;
    }

    /**
     * Force SSL if needed
     *
     * @param string $url
     * @return string
     */
    protected function enforceSSL(string $url): string
    {
        $forceSSL = $this->getForceSSL();
        if (!$forceSSL) {
            return $url;
        }

        return str_replace('http://', 'https://', $url);
    }

    /**
     * Override this function if runtime change is needed (CMS setting or Environment variable)
     *
     * @return bool
     */
    protected function getForceSSL(): bool
    {
        return (bool) $this->config()->get('force_ssl');
    }
}
