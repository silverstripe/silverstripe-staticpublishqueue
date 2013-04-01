<?php

class StaticPagesPublishingTest extends SapphireTest {

	public function testQueuePage() {
		parent::setUp();
		StaticPagesQueue::realtime(true);
	}

}
