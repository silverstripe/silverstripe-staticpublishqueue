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
            throw new \InvalidArgumentException(
                sprintf(
                    'Bad file extension "%s" passed to %s::%s',
                    $fileExtension,
                    static::class,
                    __FUNCTION__
                )
            );
        }
        $this->fileExtension = $fileExtension;
        return $this;
    }

    public function getFileExtension()
    {
        return $this->fileExtension;
    }

    public function purgeURL($url)
    {
        if (!$url) {
            user_error("Bad url:" . var_export($url, true), E_USER_WARNING);
            return;
        }
        $path = $this->URLtoPath($url);
        $success = $this->deleteFromPath($path . '.html') && $this->deleteFromPath($path . '.php');
        return [
            'success' => $success,
            'url' => $url,
            'path' => $this->getDestPath() . DIRECTORY_SEPARATOR . $path,
        ];
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
        $success = false;
        $response = $this->generatePageResponse($url);
        $statusCode = $response->getStatusCode();
        $doPublish = ($forcePublish && $this->getFileExtension() == 'php') || $statusCode < 400;

        if ($statusCode < 300) {
            // publish success response
            $success = $this->publishPage($response, $url);
        } elseif ($statusCode < 400) {
            // publish redirect response
            $success = $this->publishRedirect($response, $url);
        } elseif ($doPublish) {
            // only publish error pages if we are able to send status codes via PHP
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
     * @param string       $url
     * @return bool
     */
    protected function publishRedirect($response, $url)
    {
        $success = true;
        $path = $this->URLtoPath($url);
        $location = $response->getHeader('Location');
        if ($this->getFileExtension() === 'php') {
            $phpContent = $this->generatePHPCacheFile($response);
            $success = $this->saveToPath($phpContent, $path . '.php');
        }
        return $this->saveToPath($this->generateHTMLCacheRedirection($location), $path . '.html') && $success;
    }

    /**
     * @param HTTPResponse $response
     * @param string       $url
     * @return bool
     */
    protected function publishPage($response, $url)
    {
        $success = true;
        $path = $this->URLtoPath($url);
        if ($this->getFileExtension() === 'php') {
            $phpContent = $this->generatePHPCacheFile($response);
            $success = $this->saveToPath($phpContent, $path . '.php');
        }
        return $this->saveToPath($response->getBody(), $path . '.html') && $success;
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

    protected function deleteFromPath($filePath)
    {
        $deletePath = $this->getDestPath() . DIRECTORY_SEPARATOR . $filePath;
        if (file_exists($deletePath)) {
            $success = unlink($deletePath);
        } else {
            $success = true;
        }
        Filesystem::remove_folder_if_empty(dirname($deletePath));
        return $success;
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

        $filename = $urlSegment ?: "index";

        if (FilesystemPublisher::config()->get('domain_based_caching')) {
            if (!$urlParts) {
                return;
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

    protected function pathToURL($path)
    {
        if (strpos($path, $this->getDestPath()) === 0) {
            //Strip off the full path of the cache dir from the front
            $path = substr($path, strlen($this->getDestPath()));
        }
        $path = ltrim($path, '/');

        if (strpos($path, $this->getDestFolder()) === 0) {
            //Strip off the cache dir from the front
            $path = substr($path, strlen($this->getDestFolder()));
        }

        // Strip off the file extension
        $relativeURL = ltrim(substr($path, 0, (strrpos($path, "."))), '/');

        return $relativeURL == 'index' ? '' : $relativeURL;
    }

    public function getPublishedURLs($dir = null, &$result = [])
    {
        if ($dir == null) {
            $dir = $this->getDestPath();
        }

        $root = scandir($dir);
        foreach ($root as $fileOrDir) {
            if ($fileOrDir === '.' || $fileOrDir === '..') {
                continue;
            }
            $fullPath = $dir . DIRECTORY_SEPARATOR . $fileOrDir;
            if (is_file($fullPath)) {
                $result[] = $this->pathToURL($fullPath);
                continue;
            }

            if (is_dir($fullPath)) {
                $this->getPublishedURLs($fullPath, $result);
            }
        }
        return array_values(array_unique($result));
    }
}
