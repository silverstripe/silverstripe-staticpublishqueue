<?php

/**
 * Holds data about runs from the task BuildStaticCacheFromQueue
 *
 */
class BuildStaticCacheSummary extends DataObject {

	public static $db = array(
		'Pages' => 'Int',
		'TotalTime' => 'Float',
		'AverageTime' => 'Float',
		'MemoryUsage' => 'Float',
		'PID' => 'Int',
		'Finished' => 'Boolean'
	);

	public static function get_a_uniqueID( $pid ) {
		return uniqid($pid.'_');
	}
}
