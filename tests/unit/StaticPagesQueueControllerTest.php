<?php

/**
 * Description of StaticPagesQueueControllerTest
 *
 */
class StaticPagesQueueControllerTest extends SapphireTest {

	protected static $fixture_file = "staticpublishqueue/tests/Base.yml";
	
	protected $requiredExtensions = array(
		'SiteTree' => array(
			'SiteTreePublishingEngine',
			'PublishableSiteTree',
		)
	);

	public function setUp() {
		parent::setUp();
		StaticPagesQueue::realtime(true);
	}

	public function tearDown() {
		parent::tearDown();
		self::empty_temp_db();
	}

	public static function remove_query($url) {
		return substr($url, 0, strpos($url, '?'));
	}

	public function testQueueOnPublish() {
		$top1 = $this->objFromFixture("SiteTree","top1");
		$top1->doPublish();

		$queue = StaticPagesQueue::get()->filter(array('URLSegment:PartialMatch'=>$top1->URLSegment));

		$this->assertNotNull($queue);
		$this->assertGreaterThan(0,$queue->Count(),"Something in the queue");
		$this->assertEquals(
			self::remove_query($queue->First()->URLSegment),
			$top1->Link(),
			"The queued page's link is in the queue"
		);
	}

	public function testQueueParentPages() {
		$top2 = $this->objFromFixture("SiteTree","top2");
		$top2->doPublish();
		StaticPagesQueue::delete_by_link($top2->Link());    //remove the object just queued
		$this->assertEquals(0,StaticPagesQueue::get()->Count(),"Queue is now empty");

		$child2 = $this->objFromFixture("SiteTree","child2");
		$child2->doPublish();

		$this->assertGreaterThan(1,StaticPagesQueue::get()->Count(),"Two or more pages in the queue");

		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$child2->Link()))->First();
		$this->assertEquals($queue->URLSegment,$child2->Link(),"The child2 page's link is in the queue");

		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$top2->Link()))->First();
		$this->assertEquals($queue->URLSegment,$top2->Link(),"The child2 page's parent page link is in the queue");
	}

	public function testQueueAncestorPages() {
		//prepare
		$top1 = $this->objFromFixture("SiteTree","top1");
		$top1->doPublish();
		$child1 = $this->objFromFixture("SiteTree","child1");
		$child1->doPublish();

		//and clear the queue
		$queue = StaticPagesQueue::get();
		foreach($queue as $q) {
			$q->delete();
		}
		$this->assertEquals(0, StaticPagesQueue::get()->Count(), "Queue is now empty");

		$childchild1 = $this->objFromFixture("SiteTree","childchild1");
		$childchild1->doPublish();

		//queued page and both its parents are queued
		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$childchild1->Link()));
		
		$this->assertNotEquals(0, $queue->count(), 'Queue should contain more than zero items');
		$item = $queue->first();
		$this->assertEquals($item->URLSegment, $childchild1->Link(), "The page's link is in the queue");

		
		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$child1->Link()))->First();
		$this->assertNotEquals(0, $queue->count(), 'Queue should contain more than zero items');
		$item = $queue->first();
		$this->assertEquals($item->URLSegment,$child1->Link(),"The page's link is in the queue");

		//test if we include parents of parents (ancestor test)
		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$top1->Link()))->First();
		$this->assertEquals($queue->URLSegment,$top1->Link(),"The page's link is in the queue");
	}

	public function testQueueRedirectorAndVirtualPages() {
		//publish all pages
		$allPages = SiteTree::get();
		foreach($allPages as $page) {
			$page->doPublish();
		}

		//and clear the queue
		$queue = StaticPagesQueue::get();
		foreach($queue as $q) {
			$q->delete();
		}

		$redirectorTarget = $this->objFromFixture("SiteTree","top2"); //the one with the redirector page
		$redirectorTarget->doPublish();
		$redirectorPage = RedirectorPage::get()->First();
		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$redirectorPage->Link()))->First();
		$this->assertEquals($queue->URLSegment,$redirectorPage->Link(),"The page's link is in the queue");

		$virtualTarget = $this->objFromFixture("SiteTree","top3"); //the one with the virtual page
		$virtualTarget->doPublish();
		$virtualPage = VirtualPage::get()->First();
		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$virtualPage->Link()))->First();
		$this->assertEquals($queue->URLSegment,$virtualPage->Link(),"The page's link is in the queue");

		//check if the virtual and redirector page's parent's parent are included
		$top2 = $this->objFromFixture("SiteTree","top2");
		$top1 = $this->objFromFixture("SiteTree","top1");

		//(ancestor test)
		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$top1->Link()))->First();
		$this->assertEquals($queue->URLSegment,$top1->Link(),"The page's link is in the queue");
		$queue = StaticPagesQueue::get()->filter(array('URLSegment'=>$top2->Link()))->First();
		$this->assertEquals($queue->URLSegment,$top2->Link(),"The page's link is in the queue");
	}
	
}
