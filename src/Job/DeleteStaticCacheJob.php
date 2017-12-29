<?php

namespace SilverStripe\StaticPublishQueue\Job;

use SilverStripe\ORM\DataObject;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class DeleteStaticCacheJob extends AbstractQueuedJob
{

    public function setObject(DataObject $object, $name = 'Object')
    {
        if (
            !$object->hasExtension(PublishableSiteTree::class)
            && !$object instanceof StaticallyPublishable
        ) {
            throw new \InvalidArgumentException(sprintf(
                'Object of type "%s" does not implement "%s" interface',
                get_class($object),
                StaticallyPublishable::class
            ));
        }
        parent::setObject($object, $name);
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Remove a set of static pages from the cache';
    }

    /**
     * Do some processing yourself!
     */
    public function process()
    {
        // TODO: Implement process() method.
    }
}
