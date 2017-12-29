<?php

namespace SilverStripe\StaticPublishQueue\Publisher;

use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\StaticPublishQueue\Publisher;

class FilesystemPublisher extends Publisher
{
    /**
     * @var string
     */
    protected $destFolder = 'cache';

    /**
     * @var string
     */
    protected $fileExtension = 'php';

    /**
     * @return string
     */
    public function getDestPath()
    {
        return BASE_PATH . '/' . $this->getDestFolder();
    }

    public function setDestFolder($destFolder)
    {
        $this->destFolder = $destFolder;
        return $this;
    }

    public function getDestFolder()
    {
        return $this->destFolder;
    }

    public function setFileExtension($fileExtension)
    {
        $fileExtension = strtolower($fileExtension);
        if (!in_array($fileExtension, ['html', 'php'])) {
            throw new \InvalidArgumentException(sprintf(
                'Bad file extension "%s" passed to %s::%s',
                $fileExtension,
                static::class,
                __FUNCTION__
            ));
        }
        $this->fileExtension = $fileExtension;
        return $this;
    }

    public function getFileExtension()
    {
        return $this->fileExtension;
    }

    /**
     * @param string $url
     * @return array A result array
     */
    public function publishURL($url, $forcePublish = false)
    {
        if (!$url) {
            user_error("Bad url:" . var_export($url, true), E_USER_WARNING);
            return;
        }
        $response = $this->generatePageResponse($url);
        $statusCode = $response->getStatusCode();
        $doPublish = $forcePublish || $statusCode < 400;

        if ($statusCode < 300) {
            // publish success response
            $success = $this->publishPage($response, $url);
        } elseif ($statusCode < 400) {
            // publish redirect response
            $success = $this->publishRedirect($response, $url);
        } elseif ($doPublish) {
            // publish error page
            $success = $this->publishPage($response, $url);
        }
        return [
            'published' => $doPublish,
            'success' => $success,
            'responsecode' => $statusCode,
            'url' => $url,
        ];
    }

    /**
     * @param HTTPResponse $response
     * @param string $url
     * @return bool
     */
    protected function publishRedirect($response, $url)
    {
        $path = $this->URLtoPath($url);
        $location = $response->getHeader('Location');
        if ($this->getFileExtension() === 'php') {
            $content = $this->generatePHPCacheRedirection($location, $response->getStatusCode());
        } else {
            $absoluteURL = Convert::raw2htmlatt(Director::absoluteURL($location));
            $content = "<html><head><meta http-equiv=\"refresh\" content=\"2; URL=$absoluteURL\"></head><body></body></html>";
        }
        return $this->saveToPath($content, $path);
    }

    /**
     * @param HTTPResponse $response
     * @param string $url
     * @return bool
     */
    protected function publishErrorPage($response, $url)
    {
        $path = $this->URLtoPath($url);
        $path = dirname($path) . DIRECTORY_SEPARATOR . 'error-' . $response->getStatusCode() . '.' . $this->getFileExtension();
        if ($this->getFileExtension() === 'php') {
            $content = $this->generatePHPCacheFile(
                $response->getBody(),
                HTTP::get_cache_age(),
                date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp()),
                $response->getHeader('Content-Type'),
                $response->getStatusCode()
            );
        } else {
            $content = $response->getBody();
        }
        return $this->saveToPath($content, $path);
    }

    /**
     * @param HTTPResponse $response
     * @param string $url
     * @return bool
     */
    protected function publishPage($response, $url)
    {
        $path = $this->URLtoPath($url);
        if ($this->getFileExtension() === 'php') {
            $content = $this->generatePHPCacheFile(
                $response->getBody(),
                HTTP::get_cache_age(),
                date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp()),
                $response->getHeader('Content-Type'),
                $response->getStatusCode()
            );
        } else {
            $content = $response->getBody();
        }
        return $this->saveToPath($content, $path);
    }

    /**
     * @param string $content
     * @param string $filePath
     * @return bool
     */
    protected function saveToPath($content, $filePath)
    {
        if (empty($content)) {
            return false;
        }
        $publishPath = $this->getDestPath() . DIRECTORY_SEPARATOR . $filePath;
        Filesystem::makeFolder(dirname($publishPath));
        return file_put_contents($publishPath, $content) !== false;
    }

    protected function URLtoPath($url)
    {
        // parse_url() is not multibyte safe, see https://bugs.php.net/bug.php?id=52923.
        // We assume that the URL hsa been correctly encoded either on storage (for SiteTree->URLSegment),
        // or through URL collection (for controller method names etc.).
        $urlParts = @parse_url($url);

        // Remove base folders from the URL if webroot is hosted in a subfolder (same as static-main.php)
        $path = isset($urlParts['path']) ? $urlParts['path'] : '';
        if (mb_substr(mb_strtolower($path), 0, mb_strlen(BASE_URL)) == mb_strtolower(BASE_URL)) {
            $urlSegment = mb_substr($path, mb_strlen(BASE_URL));
        } else {
            $urlSegment = $path;
        }

        // Normalize URLs
        $urlSegment = trim($urlSegment, '/');
        $extension = $this->getFileExtension();

        $filename = $urlSegment ? "$urlSegment.$extension" : "index.$extension";

        if (FilesystemPublisher::config()->get('domain_based_caching')) {
            if (!$urlParts) {
                return;
            }
            if (isset($urlParts['host'])) {
                $filename = $urlParts['host'] . '/' . $filename;
            }
        }

        return ((dirname($filename) == '/') ? '' : (dirname($filename) . '/')) . basename($filename);
    }
}
