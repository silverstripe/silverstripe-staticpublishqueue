<?php

namespace SilverStripe\StaticPublishQueue;

include_once __DIR__ . '/functions.php';

return function($cacheDir, $urlMapping = null)
{
    if (isset($_COOKIE['bypassStaticCache'])) {
        return false;
    }

    // Convert into a full URL
    $port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
    $https = $port === '443' || isset($_SERVER['HTTPS']) || isset($_SERVER['HTTPS']);
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    $url = $https ? 'https://' : 'http://';
    $url .= $host . $uri;


    if (is_callable($urlMapping)) {
        $path = $urlMapping($url);
    } else {
        $path = URLtoPath($url);
    }

    if (!$path) {
        return false;
    }

    $cachePath = $cacheDir . DIRECTORY_SEPARATOR . $path;

    //check for directory traversal attack
    $realCacheDir = realpath($cacheDir);
    $realCachePath = realpath($dirname = dirname($cachePath));

    // path is outside the cache dir
    if (substr($realCachePath, 0, strlen($realCacheDir)) !== $realCacheDir) {
        return false;
    }

    $cacheConfig = [];

    if (file_exists($cachePath . '.php')) {
        $cacheConfig = require $cachePath . '.php';
    } elseif (!file_exists($cachePath . '.html')) {
        return false;
    }
    header('X-Cache-Hit: ' . date(\DateTime::COOKIE));
    if (!empty($cacheConfig['responseCode'])) {
        header('HTTP/1.1 ' . $cacheConfig['responseCode']);
    }
    if (!empty($cacheConfig['headers'])) {
        foreach ($cacheConfig['headers'] as $header) {
            header($header, true);
        }
    }
    if (file_exists($cachePath . '.html')) {
        $etag = '"' . md5_file($cachePath . '.html') . '"';
        if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
            header('HTTP/1.1 304', true);
            return true;
        }
        header('ETag: ' . $etag);
        readfile($cachePath . '.html');
    }
    return true;
};
