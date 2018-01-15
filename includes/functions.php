<?php

namespace SilverStripe\StaticPublishQueue;

function URLtoPath($url, $domainBasedCaching = false)
{
    // parse_url() is not multibyte safe, see https://bugs.php.net/bug.php?id=52923.
    // We assume that the URL hsa been correctly encoded either on storage (for SiteTree->URLSegment),
    // or through URL collection (for controller method names etc.).
    $urlParts = @parse_url($url);

    // Remove base folders from the URL if webroot is hosted in a subfolder)
    $path = isset($urlParts['path']) ? $urlParts['path'] : '';
    if (mb_substr(mb_strtolower($path), 0, mb_strlen(BASE_URL)) == mb_strtolower(BASE_URL)) {
        $urlSegment = mb_substr($path, mb_strlen(BASE_URL));
    } else {
        $urlSegment = $path;
    }

    // Normalize URLs
    $urlSegment = trim($urlSegment, '/');

    $filename = $urlSegment ?: "index";

    if ($domainBasedCaching) {
        if (!$urlParts) {
            throw new \LogicException('Unable to parse URL');
        }
        if (isset($urlParts['host'])) {
            $filename = $urlParts['host'] . '/' . $filename;
        }
    }
    $dirName = dirname($filename);
    $prefix = '';
    if ($dirName != '/' && $dirName != '.') {
        $prefix = $dirName . '/';
    }
    return $prefix . basename($filename);
}
