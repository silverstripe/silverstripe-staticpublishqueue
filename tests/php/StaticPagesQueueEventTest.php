<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Event\StaticPagesQueueEvent;
use SilverStripe\StaticPublishQueue\Exception\EventException;
use SilverStripe\StaticPublishQueue\Model\StaticPagesQueue;
use SilverStripe\StaticPublishQueue\Test\StaticPagesQueueEventTest\Contract\TestingEventListener;
use SilverStripe\StaticPublishQueue\Test\StaticPagesQueueEventTest\Contract\TestingEventListenerMissing;
use SilverStripe\StaticPublishQueue\Test\StaticPagesQueueEventTest\Event\EventTestClass;
use SilverStripe\StaticPublishQueue\Test\StaticPagesQueueEventTest\Listener\EventTestClassImplementator;

/**
 * This is a Unittest class for EventTest
 */
class StaticPagesQueueEventTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();
        StaticPagesQueueEvent::clear();
        Config::modify()->set(StaticPagesQueue::class, 'realtime', true);
    }

    /**
     * @expectedException EventException
     */
    public function testRegisterEventWithNonExistingInterface()
    {
        StaticPagesQueueEvent::register_event(EventTestClassImplementator::class, 'apa');
    }

    /**
     * @expectedException EventException
     */
    public function testRegisterEventWithNonExistingEventhandler()
    {
        StaticPagesQueueEvent::register_event('apa', TestingEventListener::class);
    }

    /**
     * @expectedException EventException
     */
    public function testRegisterEventNoImplementators()
    {
        StaticPagesQueueEvent::register_event(EventTestClass::class, TestingEventListenerMissing::class);
    }

    public function testRegisterEvent()
    {
        // @todo - make an assertion
        StaticPagesQueueEvent::register_event(EventTestClass::class, TestingEventListener::class);
    }

    /**
     * @expectedException EventException
     */
    public function testFireEventNoEventClassRegistered()
    {
        StaticPagesQueueEvent::trigger_events_during_testing(true);
        $this->setExpectedException(EventException::class);
        // @todo - confirm what assertion should be made here
        $this->assertTrue(StaticPagesQueueEvent::fire_event(new EventTestClass()));
    }

    public function testFireEvent()
    {
        StaticPagesQueueEvent::trigger_events_during_testing(true);
        StaticPagesQueueEvent::register_event(EventTestClass::class, TestingEventListener::class);
        $this->assertTrue(StaticPagesQueueEvent::fire_event(new EventTestClass()));
    }

    public function testTriggerEventsDuringTesting()
    {
        StaticPagesQueueEvent::trigger_events_during_testing(false);
        StaticPagesQueueEvent::register_event(EventTestClass::class, TestingEventListener::class);
        $this->assertFalse(StaticPagesQueueEvent::fire_event(new EventTestClass()));
    }
}
