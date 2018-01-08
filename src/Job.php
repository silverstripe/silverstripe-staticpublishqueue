<?php

namespace SilverStripe\StaticPublishQueue;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

abstract class Job extends AbstractQueuedJob
{
    use Configurable;

    /**
     * @var int
     * @config
     */
    private static $chunk_size = 200;

    public function getSignature()
    {
        return md5(implode('-', [static::class, $this->ObjectID, $this->ObjectType]));
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
    public function findAffectedURLs()
    {
        $page = $this->getObject();
        return $page->urlsToCache();
    }
}
