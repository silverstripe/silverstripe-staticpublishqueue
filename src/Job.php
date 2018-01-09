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

    public function setup()
    {
        parent::setup();
        $this->totalSteps = ceil(count($this->URLsToProcess) / self::config()->get('chunk_size'));
    }

    public function getSignature()
    {
        return md5(implode('-', [static::class, implode('-', array_keys($this->URLsToProcess))]));
    }
}
