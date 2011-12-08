<?php
/**
 * This is an helper object to StaticPagesQueue to hold an array of urls with
 * priorites to be recached.
 * 
 * If the StaticPagesQueue::is_realtime is false this class will call 
 * StaticPagesQueue::push_urls_to_db when in __destructs.
 *
 */
class URLArrayObject extends ArrayObject {

	/**
	 *
	 * @var URLArrayObject
	 */
	protected static $instance;

	/**
	 *
	 * @staticvar string $instance
	 * @return URLArrayObject
	 */
	protected static function get_instance() {
		static $instance = null;
		if (!self::$instance) {
			self::$instance = new URLArrayObject();
		}
		return self::$instance;
	}

	/**
	 * The format of the urls should be array( 'URLSegment' => '50')
	 *
	 * @param array $urls 
	 */
	public static function add_urls(array $urls) {
		if(!$urls) {
			return;
		}
		foreach ($urls as $URLSegment=>$priority) {
			if(is_numeric($URLSegment) && is_string($priority)) {
				$URLSegment = $priority;
				$priority = 50;
			}
			self::get_instance()->append(array($priority, $URLSegment));
		}

		// Insert into the database directly instead of waiting to destruct time
		if (StaticPagesQueue::is_realtime()) {
			self::get_instance()->insertIntoDB();
		}
	}

	/**
	 * When this class is getting garbage collected, trigger the insert of all 
	 * urls into the database
	 * 
	 */
	public function __destruct() {
		$this->insertIntoDB();
	}

	/**
	 * This method will insert all URLs that exists in this object into the 
	 * database by calling the StaticPagesQueue
	 *
	 * @return type 
	 */
	public function insertIntoDB() {
		$arraycopy = $this->getArrayCopy();
		usort($arraycopy, array(__CLASS__, 'sort_on_priority'));
		foreach ($arraycopy as $array) {
			StaticPagesQueue::add_to_queue($array[0], $array[1]);
		}
		StaticPagesQueue::push_urls_to_db();
		$this->exchangeArray(array());
	}

	/**
	 * Sorts the array on priority, from highest to lowest
	 *
	 * @param array $a
	 * @param array $b
	 * @return int - signed
	 */
	protected function sort_on_priority($a, $b) {
		if ($a[0] == $b[0]) {
			return 0;
		}
		return ($a[0] > $b[0]) ? -1 : 1;
	}

}
