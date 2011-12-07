<?php

/**
 * This is a Unittest class for EventTest
 * 
 */
class StaticPageQueueEventTest extends SapphireTest {

    public function testGetInstance() {
        $this->assertTrue(new Results instanceof Results, 'Trying to find an instance of Results');
    }
    
    public function setUp() {
        parent::setUp();
        StaticPageQueueEvent::clear();
    }
    
    public function testRegisterEventWithNonExistingInterface() {
        $this->setExpectedException('StaticPageQueueEvent_Exception');
        StaticPageQueueEvent::register_event('EventTestClassImplementator', 'apa');
    }
    
    public function testRegisterEventWithNonExistingEventhandler() {
        $this->setExpectedException('StaticPageQueueEvent_Exception');
        StaticPageQueueEvent::register_event('apa', 'TestingEventListener');
    }
    
    public function testRegisterEventNoImplementators() {
        $this->setExpectedException('StaticPageQueueEvent_Exception');
        StaticPageQueueEvent::register_event('EventTestClass', 'TestingEventListenerMissing');
    }
    
    public function testRegisterEvent() {
         StaticPageQueueEvent::register_event('EventTestClass', 'TestingEventListener');
    }
    
    public function testFireEventNoEventClassRegistered() {
        StaticPageQueueEvent::trigger_events_during_testing(true);
        $this->setExpectedException('StaticPageQueueEvent_Exception');
        StaticPageQueueEvent::fire_event(new EventTestClass());
    }
    
    public function testFireEvent() {
        StaticPageQueueEvent::trigger_events_during_testing(true);
        StaticPageQueueEvent::register_event('EventTestClass', 'TestingEventListener');
        $this->assertTrue(StaticPageQueueEvent::fire_event(new EventTestClass()));
        
    }
    
    public function testTriggerEventsDuringTesting() {
        StaticPageQueueEvent::trigger_events_during_testing(false);
        StaticPageQueueEvent::register_event('EventTestClass', 'TestingEventListener');
        $this->assertFalse(StaticPageQueueEvent::fire_event(new EventTestClass()));
    }
}

interface TestingEventListener extends TestOnly {}

class EventTestClassImplementator implements TestingEventListener {
    public function testingEvent() {
        return true;
    }
}
class EventTestClass extends Event implements TestOnly{
}

interface TestingEventListenerMissing extends TestOnly {}
