<?php

class PublishableSiteTreeTest extends SapphireTest {

	function testObjectsToUpdateOnPublish() {

		$parent = Object::create('PublishableSiteTreeTest_Publishable');
		$parent->Title = 'parent';

		$stub = $this->getMock(
			'PublishableSiteTreeTest_Publishable',
			array('getParentID', 'Parent')
		);
		$stub->Title = 'stub';

		$stub->expects($this->once())
			->method('getParentID')
			->will($this->returnValue('2'));

		$stub->expects($this->once())
			->method('Parent')
			->will($this->returnValue($parent));

		$objects = $stub->objectsToUpdate(array('action' => 'publish'));
		$this->assertEquals($objects->column('Title'), array('stub', 'parent'), 'Updates itself and parent on publish');

	}

	function testObjectsToUpdateOnUnpublish() {

		$parent = Object::create('PublishableSiteTreeTest_Publishable');
		$parent->Title = 'parent';

		$stub = $this->getMock(
			'PublishableSiteTreeTest_Publishable',
			array('getParentID', 'Parent')
		);
		$stub->Title = 'stub';

		$stub->expects($this->once())
			->method('getParentID')
			->will($this->returnValue('2'));

		$stub->expects($this->once())
			->method('Parent')
			->will($this->returnValue($parent));

		$objects = $stub->objectsToUpdate(array('action' => 'unpublish'));
		$this->assertEquals($objects->column('Title'), array('parent'), 'Updates parent on unpublish');

	}

	function testObjectsToDeleteOnPublish() {

		$stub = Object::create('PublishableSiteTreeTest_Publishable');
		$objects = $stub->objectsToDelete(array('action' => 'publish'));
		$this->assertEquals($objects->column('Title'), array(), 'Deletes nothing on publish');

	}

	function testObjectsToDeleteOnUnpublish() {

		$stub = Object::create('PublishableSiteTreeTest_Publishable');
		$stub->Title = 'stub';
		$objects = $stub->objectsToDelete(array('action' => 'unpublish'));
		$this->assertEquals($objects->column('Title'), array('stub'), 'Deletes itself on unpublish');

	}

}

class PublishableSiteTreeTest_Publishable extends SiteTree implements TestOnly {

	private $extensions = array(
		'PublishableSiteTree'
	);

}
