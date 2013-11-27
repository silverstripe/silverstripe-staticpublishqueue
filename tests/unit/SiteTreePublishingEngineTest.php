<?php

class SiteTreePublishingEngineTest extends SapphireTest {

	function testCollectChangesForPublishing() {

		$obj = Object::create('SiteTreePublishingEngineTest_StaticPublishingTrigger');
		$obj->collectChanges(array('action'=>'publish'));

		$this->assertEquals(
			$obj->getToUpdate(),
			array('/updateOnPublish?_ID=1&_ClassName=StaticallyPublishableTest'=>10)
		);
		$this->assertEquals(
			$obj->getToDelete(),
			array('/deleteOnPublish?_ID=1&_ClassName=StaticallyPublishableTest'=>10)
		);

	}

	function testCollectChangesForUnpublishing() {

		$obj = Object::create('SiteTreePublishingEngineTest_StaticPublishingTrigger');
		$obj->collectChanges(array('action'=>'unpublish'));

		$this->assertEquals(
			$obj->getToUpdate(),
			array('/updateOnUnpublish?_ID=1&_ClassName=StaticallyPublishableTest'=>10)
		);
		$this->assertEquals(
			$obj->getToDelete(),
			array('/deleteOnUnpublish?_ID=1&_ClassName=StaticallyPublishableTest'=>10)
		);

	}

	function testFlushChanges() {

		$toUpdate = array('/toUpdate?_ID=1&_ClassName=StaticallyPublishableTest'=>10);
		$toDelete = array('/toDelete?_ID=1&_ClassName=StaticallyPublishableTest'=>10);

		$urlArrayObjectClass = $this->getMockClass('URLArrayObject', array('add_urls'));
		Injector::inst()->registerNamedService('URLArrayObject', new $urlArrayObjectClass);
		$urlArrayObjectClass::staticExpects($this->once())
			->method('add_urls')
			->with($this->equalTo($toUpdate));

		$stub = $this->getMock(
			'SiteTreePublishingEngineTest_StaticPublishingTrigger',
			array('unpublishPagesAndStaleCopies')
		);
		$stub->expects($this->once())
			->method('unpublishPagesAndStaleCopies')
			->with($this->equalTo($toDelete));

		$stub->setToUpdate($toUpdate);
		$stub->setToDelete($toDelete);

		$stub->flushChanges();

		$this->assertEquals($stub->getToUpdate(), array(), 'The update cache has been flushed.');
		$this->assertEquals($stub->getToDelete(), array(), 'The delete cache has been flushed.');

	}

}

class SiteTreePublishingEngineTest_StaticallyPublishable extends SiteTree implements TestOnly, StaticallyPublishable {

	public $url;
	public $prio;

	public function getClassName() {
		return 'StaticallyPublishableTest';
	}

	public function getID() {
		return '1';
	}

	public function urlsToCache() {
		return array($this->url => $this->prio);
	}

}

class SiteTreePublishingEngineTest_StaticPublishingTrigger extends SiteTree implements TestOnly, StaticPublishingTrigger {

	private $extensions = array(
		'SiteTreePublishingEngine'
	);

	public function generatePublishable($url, $prio) {
		$obj = Object::create('SiteTreePublishingEngineTest_StaticallyPublishable');
		$obj->url = $url;
		$obj->prio = $prio;

		return $obj;
	}

	public function objectsToUpdate($context) {

		switch ($context['action']) {
			case 'publish':
				return new ArrayList(array($this->generatePublishable('/updateOnPublish', 10)));
			case 'unpublish':
				return new ArrayList(array($this->generatePublishable('/updateOnUnpublish', 10)));
		}

	}

	/**
	 * Remove the object on unpublishing (the parent will get updated via objectsToUpdate).
	 */
	public function objectsToDelete($context) {

		switch ($context['action']) {
			case 'publish':
				return new ArrayList(array($this->generatePublishable('/deleteOnPublish', 10)));
			case 'unpublish':
				return new ArrayList(array($this->generatePublishable('/deleteOnUnpublish', 10)));
		}

	}

}
