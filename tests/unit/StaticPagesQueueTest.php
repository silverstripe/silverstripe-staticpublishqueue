<?php


class StaticPagesQueueTest extends SapphireTest {
	
	public function setUp() {
		parent::setUp();
		Config::inst()->nest();
		Config::inst()->update('StaticPagesQueue', 'realtime', true);
	}

	public function tearDown() {
		// Remove all StaticPagesQueue that might be left after running a testcase
		self::empty_temp_db();

		Config::inst()->unnest();
		parent::tearDown();
	}
	
	public function testAddOneURL() {
		$obj = StaticPagesQueue::get()->First();
		$this->assertFalse( $obj instanceof StaticPagesQueue );

		Injector::inst()->get('URLArrayObject')->addUrls(array('test1' => 1));

		$obj = StaticPagesQueue::get()->First();
		$this->assertTrue($obj instanceof StaticPagesQueue);
		$this->assertEquals('test1', $obj->URLSegment );
	}
	
	public function testAddManyURLs() {
		$objSet = DataObject::get('StaticPagesQueue');
		Injector::inst()->get('URLArrayObject')->addUrls(array('test2-2' => 2 ,'test2-10' => 10),true);
		$objSet = DataObject::get('StaticPagesQueue');
		$this->assertEquals( 2, $objSet->Count() );
		$this->assertDOSContains(array( 
			  array('URLSegment' => 'test2-2'), 
			  array('URLSegment' => 'test2-10')
		), $objSet);
	}
	
	public function testGetNextUrlInEmptyQueue() {
		$this->assertEquals( '', StaticPagesQueue::get_next_url() );
	}
	
	public function testGetNextUrlByPriority() {
		Injector::inst()->get('URLArrayObject')->addUrls(array('monkey' => 1 ,'stool' => 10),true);
		$this->assertEquals( 'stool', StaticPagesQueue::get_next_url() );
		$this->assertEquals( 'monkey', StaticPagesQueue::get_next_url() );
	}
	
	public function testBumpThePriority() {
		Injector::inst()->get('URLArrayObject')->addUrls(array('foobar' => 1),true);
		Injector::inst()->get('URLArrayObject')->addUrls(array('monkey' => 10),true);
		// Adding a duplicate
		Injector::inst()->get('URLArrayObject')->addUrls(array('monkey' => 1),true);
		$this->assertEquals(3, DataObject::get('StaticPagesQueue')->Count());
		StaticPagesQueue::get_next_url();
		$this->assertEquals(10, DataObject::get('StaticPagesQueue')->Last()->Priority);
	}
	
	public function testNothingToDelete() {
		$this->assertFalse( StaticPagesQueue::delete_by_link("Im not there"));
	}
	
	public function testGetNextUrlByPrioAndDate() {
		$oldObject = new StaticPagesQueue(array('URLSegment'=>'old/page','Priority'=>10),true);
		$oldObject->write();
		$oldObject->Created = "2010-01-01 10:00:01";
		$oldObject->write();
		Injector::inst()->get('URLArrayObject')->addUrls(array('monkey/bites' => 1 ,'stool/falls' => 10),true);
		$this->assertEquals( 'old/page', StaticPagesQueue::get_next_url());
		$this->assertEquals( 'stool/falls', StaticPagesQueue::get_next_url());
		$this->assertEquals( 'monkey/bites', StaticPagesQueue::get_next_url());
	}
	
	public function testMarkAsDone() {
		Injector::inst()->get('URLArrayObject')->addUrls(array('monkey' => 1),true);
		$url = StaticPagesQueue::get_next_url();
		$this->assertTrue( StaticPagesQueue::delete_by_link($url) );
		#$this->assertNull( DataObject::get('StaticPagesQueue'));
	}

	/**
	 * This tests that queueitems that are marked as regenerating, but are older 
	 * than 10 minutes actually gets another try
	 */
	public function testDeadEntries() {
		$oldObject = new StaticPagesQueue(array('URLSegment'=>'old/page','Freshness'=>'regenerating'));
		$oldObject->write();
		// Update the entry directly, this is the only way to change LastEdited to a set value
		DB::query('UPDATE "StaticPagesQueue" SET "LastEdited" =\''.date("Y-m-d H:i:s", time()-60*11).'\'');
		Injector::inst()->get('URLArrayObject')->addUrls(array('monkey/bites' => 1),true);
		$this->assertEquals( 'monkey/bites', StaticPagesQueue::get_next_url());
		$this->assertEquals( 'old/page', StaticPagesQueue::get_next_url());
		Injector::inst()->get('URLArrayObject')->addUrls(array('should/be/next' => 1),true);
		$this->assertEquals( 'should/be/next', StaticPagesQueue::get_next_url());
	}

	public function testRemoveDuplicates() {
		Injector::inst()->get('URLArrayObject')->addUrls(array(
			'test1' => 1 ,
			'test2' => 1,
			'test3' => 1
		));
		Injector::inst()->get('URLArrayObject')->addUrls(array(
			'test2' => 2, // duplicate
			'test3' => 2, // duplicate
		));
		Injector::inst()->get('URLArrayObject')->addUrls(array(
			'test2' => 3, // duplicate
		));
		$test1Objs = DataObject::get('StaticPagesQueue', '"URLSegment" = \'test1\'');
		$test2Objs = DataObject::get('StaticPagesQueue', '"URLSegment" = \'test2\'');
		$test3Objs = DataObject::get('StaticPagesQueue', '"URLSegment" = \'test3\'');
		$this->assertEquals(1, $test1Objs->Count());
		$this->assertEquals(3, $test2Objs->Count());
		$this->assertEquals(2, $test3Objs->Count());

		StaticPagesQueue::remove_duplicates($test1Objs->First()->ID);
		$test1Objs = DataObject::get('StaticPagesQueue', '"URLSegment" = \'test1\'');
		$test2Objs = DataObject::get('StaticPagesQueue', '"URLSegment" = \'test2\'');
		$test3Objs = DataObject::get('StaticPagesQueue', '"URLSegment" = \'test3\'');
		$this->assertEquals(1, $test1Objs->Count(), 'Keeps original instance without any duplicates found');
		$this->assertEquals(3, $test2Objs->Count(), 'Doesnt remove unrelated duplicates');
		$this->assertEquals(2, $test3Objs->Count(), 'Doesnt remove unrelated duplicates');

		StaticPagesQueue::remove_duplicates($test2Objs->First()->ID);
		$test2Objs = DataObject::get('StaticPagesQueue', '"URLSegment" = \'test2\'');
		$this->assertEquals(1, $test2Objs->Count(), 'Removing a single duplicate');
		
		StaticPagesQueue::remove_duplicates($test3Objs->First()->ID);
		$test3Objs = DataObject::get('StaticPagesQueue', '"URLSegment" = \'test3\'');
		$this->assertEquals(1, $test3Objs->Count(), 'Removing multiple duplicates');
	}

	/**
	 * 
	 * Test takes about 22sec on my local environment
	 */
//    public function testPerformance() {
//        $start_time = microtime(true);
//        $array = array();
//        for($idx=0;$idx<1000;$idx++){
//            $array["page-".$idx] = $idx;
//        }
//        StaticPagesQueue::add_urls($array);
//        while( $url = StaticPagesQueue::get_next_url() ) {
//            StaticPagesQueue::delete_by_link($url);
//        }
//        $time_end = microtime(true);
//		$end_time = $time_end - $start_time;
//        echo sprintf("%.3fs" ,$end_time);
//    }
}
