<?php

/**
 * This is a Unittest class for EventTest
 * 
 */
class EventTest extends SapphireTest {

    public function testGetInstance() {
        $this->assertTrue(new Results instanceof Results, 'Trying to find an instance of Results');
    }
    
    public function setUp() {
        parent::setUp();
        Event::clear();
    }
    
    public function testRegisterEventWithNonExistingInterface() {
        $this->setExpectedException('Event_Exception');
        Event::register_event('EventTestClassImplementator', 'apa');
    }
    
    public function testRegisterEventWithNonExistingEventhandler() {
        $this->setExpectedException('Event_Exception');
        Event::register_event('apa', 'TestingEventListener');
    }
    
    public function testRegisterEventNoImplementators() {
        $this->setExpectedException('Event_Exception');
        Event::register_event('EventTestClass', 'TestingEventListenerMissing');
    }
    
    public function testRegisterEvent() {
         Event::register_event('EventTestClass', 'TestingEventListener');
    }
    
    public function testFireEventNoEventClassRegistered() {
        Event::trigger_events_during_testing(true);
        $this->setExpectedException('Event_Exception');
        Event::fire_event(new EventTestClass());
    }
    
    public function testFireEvent() {
        Event::trigger_events_during_testing(true);
        Event::register_event('EventTestClass', 'TestingEventListener');
        $this->assertTrue(Event::fire_event(new EventTestClass()));
        
    }
    
    public function testTriggerEventsDuringTesting() {
        Event::trigger_events_during_testing(false);
        Event::register_event('EventTestClass', 'TestingEventListener');
        $this->assertFalse(Event::fire_event(new EventTestClass()));
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
