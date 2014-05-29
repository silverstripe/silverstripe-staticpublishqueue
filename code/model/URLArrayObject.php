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

	private $shutDownRegistered = false;

	/**
	 * Adds metadata into the URL.
	 *
	 * @param $url string
	 * @param $obj DataObject to inject
	 *
	 * @return string transformed URL.
	 */
	public function addObject($url, DataObject $dataObject) {
		$updatedUrl = HTTP::setGetVar('_ID', $dataObject->ID, $url, '&');
		$updatedUrl = HTTP::setGetVar('_ClassName', $dataObject->ClassName, $updatedUrl, '&');

		// Hack: fix the HTTP::setGetVar removing leading slash from the URL if BaseURL is used.
		if (strpos($url, '/')===0) return '/' . $updatedUrl;
		else return $updatedUrl;
	}

	/**
	 * Adds metadata into all URLs in the array.
	 *
	 * @param $urls array of url => priority
	 * @param $obj DataObject to inject
	 *
	 * @return array array of transformed URLs.
	 */
	public function addObjects($urls, DataObject $dataObject) {
		$processedUrls = array();
		foreach ($urls as $url=>$priority) {
			$url = $this->addObject($url, $dataObject);
			$processedUrls[$url] = $priority;
		}

		return $processedUrls;
	}

	/**
	 * Extracts the metadata from the queued structure.
	 *
	 * @param $url string
	 *
	 * @return DataObject represented by the URL.
	 */
	public function getObject($url) {
		$urlParts = @parse_url($url);
		if (isset($urlParts['query'])) parse_str($urlParts['query'], $getParameters);
		else $getParameters = array();

		$obj = null;
		if(isset($getParameters['_ID']) && isset($getParameters['_ClassName'])) {
			$id = $getParameters['_ID'];
			$className = $getParameters['_ClassName'];
			$obj = DataObject::get($className)->byID($id);
		}

		return $obj;
	}

	/**
	 * Adds urls to the queue after injecting the objects' metadata.
	 *
	 * @param $urls array associative array of url => priority
	 * @param $dataObject DataObject object to associate the urls with
	 */
	public function addUrlsOnBehalf(array $urls, DataObject $dataObject) {
		return $this->addUrls($this->addObjects($urls, $dataObject));
	}

	/**
	 * The format of the urls should be array( 'URLSegment' => '50')
	 *
	 * @param array $urls 
	 */
	public function addUrls(array $urls) {
		if(!$urls) {
			return;
		}
		
		$urlsAlreadyProcessed = array();    //array to filter out any duplicates
		foreach ($urls as $URLSegment=>$priority) {
			if(is_numeric($URLSegment) && is_string($priority)) {   //case when we have a non-associative flat array
				$URLSegment = $priority;
				$priority = 50;
			}

			//only add URLs of a certain length and only add URLs not already added
			if (!empty($URLSegment) &&
			    strlen($URLSegment) > 0 &&
			    !isset($urlsAlreadyProcessed[$URLSegment]) &&
				substr($URLSegment,0,4) != "http") {    //URLs isn't to an external site

				//check to make sure this page isn't excluded from the cache
				if (!$this->excludeFromCache($URLSegment)) {
					$this->append(array($priority, $URLSegment));
				}
				$urlsAlreadyProcessed[$URLSegment] = true;  //set as already processed
			}
		}

		// Insert into the database directly instead of waiting to destruct time
		if (Config::inst()->get('StaticPagesQueue', 'realtime')) {
			$this->insertIntoDB();
		} else {
			//don't register a shutdown twice within a single object
			if (!$this->shutDownRegistered) {
				register_shutdown_function(array($this,'destructMethod'));
				$this->shutDownRegistered = true;
			}
		}
	}

	protected function excludeFromCache($url) {
		$excluded = false;

		//don't publish objects that are excluded from cache
		$candidatePage = SiteTree::get_by_link($url);
		if (!empty($candidatePage)) {
			if (!empty($candidatePage->excludeFromCache)) {
				$excluded = true;
			}
		}

		return $excluded;
	}

	/**
	 * When this class is getting garbage collected, trigger the insert of all
	 * urls into the database
	 *
	 */
	public function destructMethod() {
		if (!Config::inst()->get('StaticPagesQueue', 'realtime')) {
			$this->insertIntoDB();
		}
	}

	/**
	 * This method will insert all URLs that exists in this object into the 
	 * database by calling the StaticPagesQueue
	 *
	 * @return type 
	 */
	public function insertIntoDB() {
		$arraycopy = $this->getArrayCopy();
		usort($arraycopy, array($this, 'sortOnPriority'));
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
	protected function sortOnPriority($a, $b) {
		if ($a[0] == $b[0]) {
			return 0;
		}
		return ($a[0] > $b[0]) ? -1 : 1;
	}

}
