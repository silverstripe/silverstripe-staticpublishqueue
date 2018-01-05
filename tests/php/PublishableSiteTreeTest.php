<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree;
use SilverStripe\StaticPublishQueue\Model\StaticPagesQueue;
use SilverStripe\StaticPublishQueue\Test\PublishableSiteTreeTest\Model\PublishablePage;

class PublishableSiteTreeTest extends SapphireTest
{
    protected static $required_extensions = array(
        PublishablePage::class => array(PublishableSiteTree::class)
    );

    public function testObjectsToUpdateOnPublish()
    {
        $parent = PublishablePage::create();
        $parent->Title = 'parent';

        $stub = $this->getMockBuilder(PublishablePage::class)
            ->setMethods(
                array(
                    'getParentID',
                    'Parent',
                )
            )->getMock();

        $stub->Title = 'stub';

        $stub->expects($this->once())
            ->method('getParentID')
            ->will($this->returnValue('2'));

        $stub->expects($this->once())
            ->method('Parent')
            ->will($this->returnValue($parent));

        $objects = $stub->objectsToUpdate(array('action' => 'publish'));
        $this->assertEquals(array('stub', 'parent'), $objects->column('Title'));
    }

    public function testObjectsToUpdateOnUnpublish()
    {
        $parent = PublishablePage::create();
        $parent->Title = 'parent';

        $stub = $this->getMockBuilder(PublishablePage::class)
            ->setMethods(
                array(
                    'getParentID',
                    'Parent',
                )
            )->getMock();

        $stub->Title = 'stub';

        $stub->expects($this->once())
            ->method('getParentID')
            ->will($this->returnValue('2'));

        $stub->expects($this->once())
            ->method('Parent')
            ->will($this->returnValue($parent));

        $objects = $stub->objectsToUpdate(array('action' => 'unpublish'));
        $this->assertEquals(array('parent'), $objects->column('Title'));
    }

    public function testObjectsToDeleteOnPublish()
    {
        $stub = PublishablePage::create();
        $objects = $stub->objectsToDelete(array('action' => 'publish'));
        $this->assertEquals(array(), $objects->column('Title'));
    }

    public function testObjectsToDeleteOnUnpublish()
    {
        $stub = PublishablePage::create();
        $stub->Title = 'stub';
        $objects = $stub->objectsToDelete(array('action' => 'unpublish'));
        $this->assertEquals(array('stub'), $objects->column('Title'));
    }

    public function testObjectsToUpdateOnPublishIfVirtualExists()
    {
        $redir = PublishablePage::create();
        $redir->Title = 'virtual';

        $stub = $this->getMockBuilder(PublishablePage::class)
            ->setMethods(array('getMyVirtualPages'))
            ->getMock();

        $stub->Title = 'stub';

        $stub->expects($this->once())
            ->method('getMyVirtualPages')
            ->will(
                $this->returnValue(
                    new ArrayList(array($redir))
                )
            );

        $objects = $stub->objectsToUpdate(array('action' => 'publish'));
        $this->assertContains('virtual', $objects->column('Title'));
    }

    public function testObjectsToDeleteOnUnpublishIfVirtualExists()
    {
        $redir = PublishablePage::create();
        $redir->Title = 'virtual';

        $stub = $this->getMockBuilder(PublishablePage::class)
            ->setMethods(array('getMyVirtualPages'))
            ->getMock();

        $stub->Title = 'stub';

        $stub->expects($this->once())
            ->method('getMyVirtualPages')
            ->will(
                $this->returnValue(
                    new ArrayList(array($redir))
                )
            );

        $objects = $stub->objectsToDelete(array('action' => 'unpublish'));
        $this->assertContains('virtual', $objects->column('Title'));
    }
}
