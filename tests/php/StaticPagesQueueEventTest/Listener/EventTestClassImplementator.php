<?php

namespace SilverStripe\StaticPublishQueue\Test\StaticPagesQueueEventTest\Listener;

use SilverStripe\StaticPublishQueue\Test\StaticPagesQueueEventTest\Contract\TestingEventListener;

class EventTestClassImplementator implements TestingEventListener
{
    public function testingEvent()
    {
        return true;
    }
}
