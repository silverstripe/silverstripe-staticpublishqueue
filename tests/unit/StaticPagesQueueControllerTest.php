<?php

/**
 * Description of StaticPagesQueueControllerTest
 *
 */
class StaticPagesQueueControllerTest extends SapphireTest {
	
	
	public function testRepublish() {
		$this->assertTrue( new BuildStaticCacheFromQueue instanceof BuildStaticCacheFromQueue);
	}
	
}
