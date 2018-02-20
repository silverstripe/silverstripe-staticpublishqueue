<?php

namespace SilverStripe\StaticPublishQueue\Publisher;

use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\StaticPublishQueue\Publisher;
use function SilverStripe\StaticPublishQueue\URLtoPath;

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
        $base = defined('PUBLIC_PATH') ? PUBLIC_PATH : BASE_PATH;
        return $base . DIRECTORY_SEPARATOR . $this->getDestFolder();
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
        if ($path = $this->URLtoPath($url)) {
            $success = $this->deleteFromPath($path . '.html') && $this->deleteFromPath($path . '.php');
            return [
                'success' => $success,
                'url' => $url,
                'path' => $this->getDestPath() . DIRECTORY_SEPARATOR . $path,
            ];
        }
        return [
            'success' => false,
            'url' => $url,
            'path' => false,
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
        if ($path = $this->URLtoPath($url)) {
            $location = $response->getHeader('Location');
            if ($this->getFileExtension() === 'php') {
                $phpContent = $this->generatePHPCacheFile($response);
                $success = $this->saveToPath($phpContent, $path . '.php');
            }
            return $this->saveToPath($this->generateHTMLCacheRedirection($location), $path . '.html') && $success;
        }
        return false;
    }

    /**
     * @param HTTPResponse $response
     * @param string       $url
     * @return bool
     */
    protected function publishPage($response, $url)
    {
        $success = true;
        if ($path = $this->URLtoPath($url)) {
            if ($this->getFileExtension() === 'php') {
                $phpContent = $this->generatePHPCacheFile($response);
                $success = $this->saveToPath($phpContent, $path . '.php');
            }
            return $this->saveToPath($response->getBody(), $path . '.html') && $success;
        }
        return false;
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
        return URLtoPath($url, BASE_URL, FilesystemPublisher::config()->get('domain_based_caching'));
    }

    protected function pathToURL($path)
    {
        if (strpos($path, $this->getDestPath()) === 0) {
            //Strip off the full path of the cache dir from the front
            $path = substr($path, strlen($this->getDestPath()));
        }

        // Strip off the file extension
        $relativeURL = substr($path, 0, (strrpos($path, ".")));

        if (FilesystemPublisher::config()->get('domain_based_caching')) {
            // factor in the domain as the top dir
            $absoluteURL = ltrim($relativeURL, '/');

            return Director::protocol() . $absoluteURL;
        }

        return $relativeURL == 'index' ? Director::absoluteBaseURL() : Director::absoluteURL($relativeURL);
    }

    public function getPublishedURLs($dir = null, &$result = [])
    {
        if ($dir == null) {
            $dir = $this->getDestPath();
        }

        $root = scandir($dir);
        foreach ($root as $fileOrDir) {
            if (strpos($fileOrDir, '.') === 0) {
                continue;
            }
            $fullPath = $dir . DIRECTORY_SEPARATOR . $fileOrDir;
            // we know html will always be generated, this prevents double ups
            if (is_file($fullPath) && pathinfo($fullPath)['extension'] == 'html') {
                $result[] = $this->pathToURL($fullPath);
                continue;
            }

            if (is_dir($fullPath)) {
                $this->getPublishedURLs($fullPath, $result);
            }
        }
        return $result;
    }
}
