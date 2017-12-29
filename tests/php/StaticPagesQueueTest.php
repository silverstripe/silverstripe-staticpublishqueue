<?php

namespace SilverStripe\StaticPublishQueue\Test;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\StaticPublishQueue\Model\StaticPagesQueue;
use SilverStripe\StaticPublishQueue\Model\URLArrayObject;

class StaticPagesQueueTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();
        Config::modify()->set(StaticPagesQueue::class, 'realtime', true);
    }

    public function testAddOneURL()
    {
        $obj = StaticPagesQueue::get()->First();
        $this->assertNotInstanceOf(StaticPagesQueue::class, $obj);

        Injector::inst()->get(URLArrayObject::class)->addUrls(array('test1' => 1));

        $obj = StaticPagesQueue::get()->first();
        $this->assertInstanceOf(StaticPagesQueue::class, $obj);
        $this->assertEquals('test1', $obj->URLSegment);
    }

    public function testAddManyURLs()
    {
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('test2-2' => 2, 'test2-10' => 10), true);
        $objSet = StaticPagesQueue::get();
        $this->assertCount(2, $objSet);
        $this->assertListContains(
            array(
                array('URLSegment' => 'test2-2'),
                array('URLSegment' => 'test2-10')
            ),
            $objSet
        );
    }

    public function testGetNextUrlInEmptyQueue()
    {
        $this->assertEmpty(StaticPagesQueue::get_next_url());
    }

    public function testGetNextUrlByPriority()
    {
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('monkey' => 1, 'stool' => 10), true);
        $this->assertEquals('stool', StaticPagesQueue::get_next_url());
        $this->assertEquals('monkey', StaticPagesQueue::get_next_url());
    }

    public function testBumpThePriority()
    {
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('foobar' => 1), true);
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('monkey' => 10), true);
        // Adding a duplicate
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('monkey' => 1), true);
        $this->assertCount(3, StaticPagesQueue::get());
        StaticPagesQueue::get_next_url();
        $this->assertEquals(10, StaticPagesQueue::get()->Last()->Priority);
    }

    public function testNothingToDelete()
    {
        $this->assertFalse(StaticPagesQueue::delete_by_link("Im not there"));
    }

    public function testGetNextUrlByPrioAndDate()
    {
        $oldObject = new StaticPagesQueue(array('URLSegment' => 'old/page', 'Priority' => 10), true);
        $oldObject->write();
        $oldObject->Created = "2010-01-01 10:00:01";
        $oldObject->write();
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('monkey/bites' => 1, 'stool/falls' => 10), true);
        $this->assertEquals('old/page', StaticPagesQueue::get_next_url());
        $this->assertEquals('stool/falls', StaticPagesQueue::get_next_url());
        $this->assertEquals('monkey/bites', StaticPagesQueue::get_next_url());
    }

    public function testMarkAsDone()
    {
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('monkey' => 1), true);
        $url = StaticPagesQueue::get_next_url();
        $this->assertTrue(StaticPagesQueue::delete_by_link($url));
    }

    /**
     * This tests that queueitems that are marked as regenerating, but are older
     * than 10 minutes actually gets another try
     */
    public function testDeadEntries()
    {
        $oldObject = new StaticPagesQueue(array('URLSegment' => 'old/page', 'Freshness' => 'regenerating'));
        $oldObject->write();
        // Update the entry directly, this is the only way to change LastEdited to a set value
        //@todo - look at using DBDatetime::set_mock_now()
        DB::query('UPDATE "StaticPagesQueue" SET "LastEdited" =\'' . date("Y-m-d H:i:s", time() - 60 * 11) . '\'');
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('monkey/bites' => 1), true);
        $this->assertEquals('monkey/bites', StaticPagesQueue::get_next_url());
        $this->assertEquals('old/page', StaticPagesQueue::get_next_url());
        Injector::inst()->get(URLArrayObject::class)->addUrls(array('should/be/next' => 1), true);
        $this->assertEquals('should/be/next', StaticPagesQueue::get_next_url());
    }

    public function testRemoveDuplicates()
    {
        Injector::inst()->get(URLArrayObject::class)->addUrls(
            array(
                'test1' => 1,
                'test2' => 1,
                'test3' => 1
            )
        );
        Injector::inst()->get(URLArrayObject::class)->addUrls(
            array(
                'test2' => 2, // duplicate
                'test3' => 2, // duplicate
            )
        );
        Injector::inst()->get(URLArrayObject::class)->addUrls(
            array(
                'test2' => 3, // duplicate
            )
        );
        $test1Objs = StaticPagesQueue::get()->filter(
            array(
                'URLSegment' => 'test1'
            )
        )->first();
        $test2Objs = StaticPagesQueue::get()->filter(
            array(
                'URLSegment' => 'test2'
            )
        )->first();
        $test3Objs = StaticPagesQueue::get()->filter(
            array(
                'URLSegment' => 'test3'
            )
        )->first();
        $this->assertCount(1, $test1Objs);
        $this->assertCount(3, $test2Objs);
        $this->assertCount(2, $test3Objs);

        StaticPagesQueue::remove_duplicates($test1Objs->first()->ID);
        $test1Objs = StaticPagesQueue::get()->filter(
            array(
                'URLSegment' => 'test1'
            )
        )->first();
        $test2Objs = StaticPagesQueue::get()->filter(
            array(
                'URLSegment' => 'test2'
            )
        )->first();
        $test3Objs = StaticPagesQueue::get()->filter(
            array(
                'URLSegment' => 'test3'
            )
        )->first();
        $this->assertCount(1, $test1Objs);
        $this->assertCount(3, $test2Objs);
        $this->assertCount(2, $test3Objs);

        StaticPagesQueue::remove_duplicates($test2Objs->first()->ID);
        $test2Objs = StaticPagesQueue::get()->filter(
            array(
                'URLSegment' => 'test2'
            )
        );
        $this->assertCount(1, $test2Objs);

        StaticPagesQueue::remove_duplicates($test3Objs->first()->ID);
        $test3Objs = StaticPagesQueue::get()->filter(
            array(
                'URLSegment' => 'test3'
            )
        );
        $this->assertCount(1, $test3Objs);
    }
}
