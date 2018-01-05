<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPApplication;
use SilverStripe\Control\HTTPRequestBuilder;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\CoreKernel;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Extension\Publisher\FilesystemPublisher;
use SilverStripe\StaticPublishQueue\Publisher;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class GenerateStaticCacheJob extends AbstractQueuedJob
{
    use Configurable;

    /**
     * @var int
     * @config
     */
    private static $chunk_size = 200;

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Generate a set of static pages from URLs';
    }

    public function getSignature()
    {
        $obj = $this->getObject();
        return md5(implode('-', [static::class, $obj->ID, $obj->ClassName]));
    }

    public function setup()
    {
        parent::setup();
        $this->URLsToProcess = $this->findURLsToCache();
        $this->totalSteps = ceil(count($this->URLsToProcess) / self::config()->get('chunk_size'));
        $this->addMessage('Building for ' . (string)$this->getObject());
        $this->addMessage('Building URLS ' . var_export(array_keys($this->URLsToProcess), true));
    }

    /**
     * Do some processing yourself!
     */
    public function process()
    {
        $chunkSize = self::config()->get('chunk_size');
        $count = 0;
        foreach ($this->jobData->URLsToProcess as $url => $priority) {
            if (++$count > $chunkSize) {
                break;
            }
            Publisher::singleton()->publishURL($url, true);
            $this->jobData->ProcessedURLs[$url] = $url;
            unset($this->jobData->URLsToProcess[$url]);
        }
        $this->isComplete = empty($this->jobData->URLsToProcess);
    }

    public function setObject(DataObject $object, $name = 'Object')
    {
        if (!$object->hasExtension(PublishableSiteTree::class)
            && !$object instanceof StaticallyPublishable
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Object of type "%s" does not implement "%s" interface',
                    get_class($object),
                    StaticallyPublishable::class
                )
            );
        }
        parent::setObject($object, $name);
    }

    /**
     * @return array
     */
    protected function findURLsToCache()
    {
        $page = $this->getObject();
        return $page->urlsToCache();
    }
}
