<?php

/**
 * This is a Unittest class for EventTest
 * 
 */
class StaticPagesQueueEventTest extends SapphireTest {

	public function setUp() {
		parent::setUp();
		StaticPagesQueueEvent::clear();
		Config::inst()->nest();
		Config::inst()->update('StaticPagesQueue', 'realtime', true);
	}

	public function tearDown() {
		Config::inst()->unnest();
		parent::tearDown();
	}

	public function testRegisterEventWithNonExistingInterface() {
		$this->setExpectedException('StaticPagesQueueEvent_Exception');
		StaticPagesQueueEvent::register_event('EventTestClassImplementator', 'apa');
	}
	
	public function testRegisterEventWithNonExistingEventhandler() {
		$this->setExpectedException('StaticPagesQueueEvent_Exception');
		StaticPagesQueueEvent::register_event('apa', 'TestingEventListener');
	}
	
	public function testRegisterEventNoImplementators() {
		$this->setExpectedException('StaticPagesQueueEvent_Exception');
		StaticPagesQueueEvent::register_event('EventTestClass', 'TestingEventListenerMissing');
	}
	
	public function testRegisterEvent() {
		 StaticPagesQueueEvent::register_event('EventTestClass', 'TestingEventListener');
	}
	
	public function testFireEventNoEventClassRegistered() {
		StaticPagesQueueEvent::trigger_events_during_testing(true);
		$this->setExpectedException('StaticPagesQueueEvent_Exception');
		StaticPagesQueueEvent::fire_event(new EventTestClass());
	}
	
	public function testFireEvent() {
		StaticPagesQueueEvent::trigger_events_during_testing(true);
		StaticPagesQueueEvent::register_event('EventTestClass', 'TestingEventListener');
		$this->assertTrue(StaticPagesQueueEvent::fire_event(new EventTestClass()));
		
	}
	
	public function testTriggerEventsDuringTesting() {
		StaticPagesQueueEvent::trigger_events_during_testing(false);
		StaticPagesQueueEvent::register_event('EventTestClass', 'TestingEventListener');
		$this->assertFalse(StaticPagesQueueEvent::fire_event(new EventTestClass()));
	}
}

interface TestingEventListener extends TestOnly {}

class EventTestClassImplementator implements TestingEventListener {
	public function testingEvent() {
		return true;
	}
}
class EventTestClass extends StaticPagesQueueEvent implements TestOnly{
}

interface TestingEventListenerMissing extends TestOnly {}
