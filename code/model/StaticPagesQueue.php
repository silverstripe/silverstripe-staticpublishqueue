<?php
/**
 * This class responsibility is twofold:
 * 1) Holding the data for a prioritized queue of URLs that needs to be static cached
 * 2) Interaction with that queue
 *
 * @TODO: would be good to refactor this queue to hold not only URLSegment, but also ClassName and ID of the
 * associated object (or any other metadata). This would allow FilesystemPublisher::publishPages and others
 * to stop having to smuggle the metadata within the URL (see URLArrayData::get_object).
 *
 */
class StaticPagesQueue extends DataObject {

	/**
	 *
	 * @var array
	 */
	public static $create_table_options = array(
		'MySQLDatabase' => 'ENGINE=InnoDB'
	);

	/**
	 *
	 * @var array
	 */
	public static $db = array(
		'Priority' => 'Int',
		'URLSegment' => 'Varchar(255)',
		'Freshness' => "Enum('stale, regenerating, error', 'stale')"
	);

	/**
	 *
	 * @var array
	 */
	public static $defaults = array(
		"Priority" => 3
	);

	/**
	 *
	 * @var array
	 */
	public static $default_sort = "\"Priority\"";

	/**
	 * Sets database indexes
	 *
	 * @var array
	 */
	public static $indexes = array(
		'freshness_priority_created' => '(Freshness, Priority, Created)',
	);

	/**
	 *
	 * @var boolean
	 */
	private static $realtime = false;

	/**
	 *
	 * @var int
	 */
	protected static $minutes_until_force_regeneration = 1;

	/**
	 *
	 * @var array
	 */
	protected static $insert_statements = array();

	/**
	 *
	 * @var array
	 */
	protected static $urls = array();
	
	/**
	 *
	 * @return bool
	 */
	public static function is_realtime() {
		return Config::inst()->get('StaticPagesQueue','realtime');
	}

	/**
	 *
	 * @param type $priority
	 * @param type $URLSegment
	 * @return type
	 */
	public static function add_to_queue($priority, $URLSegment) {
		$now = date("Y-m-d H:i:s");
		self::$insert_statements[$URLSegment] = '(\''.$now.'\',\''.$now.'\', \''.Convert::raw2sql($priority).'\',\''.Convert::raw2sql($URLSegment).'\')';
		self::$urls[md5($URLSegment)] = $URLSegment;
	}

		/**
	 * This will push all the currently cached insert statements to be pushed 
	 * into the database
	 *
	 * @return void
	 */
	public static function push_urls_to_db() {
		foreach(self::$insert_statements as $stmt) {
			$insertSQL = 'INSERT INTO "StaticPagesQueue" ("Created", "LastEdited", "Priority", "URLSegment") VALUES ' . $stmt;
			DB::query($insertSQL);
		}
		self::remove_old_cache(self::$urls);
		// Flush the cache so DataObject::get works correctly
		if(!empty(self::$insert_statements) && DB::affectedRows()) {
			singleton(__CLASS__)->flushCache();
		}
		self::$insert_statements = array();
	}
	
	/**
	 * Remove an object by the url
	 *
	 * @param string $URLSegment
	 * @return bool - if there was an queue item removed
	 *
	 */
	public static function delete_by_link($URLSegment) {
		$object = self::get_by_link($URLSegment);
		if(!$object) return false;

		$object->delete();
		unset($object);
		return true;
	}
	
	/**
	 * Update the queue with the information that this url renders an error somehow
	 *
	 * @param string $url
	 */
	public static function has_error( $url ) {
		if(!$url) return;
		
		$existingObject = self::get_by_link($url);
		$existingObject->Freshness = 'error';
		$existingObject->write();
	}

	/**
	 * Returns a single queue object according to a particular priority and freshness measure.
	 * This method removes any duplicates and makes the object as "regenerating", so other calls to this method
	 * don't grab the same object.
	 * If we are using MySQLDatabase with InnoDB, we do row-level locking when updating the dataobject to allow for
	 * distributed cache rebuilds
	 * @static
	 * @param $freshness
	 * @param $sortOrder
	 */
	protected static function get_queue_object($freshness, $interval = null, $sortOrder = array('Priority'=>'DESC', 'ID'=>'ASC')) {
		$className = __CLASS__;
		$queueObject = null;
		$filterQuery = array("Freshness" => $freshness);
		if ($interval) $filterQuery["LastEdited:LessThan"] = $interval;

		$query = self::get();
		if ($query->Count() > 0) {
			$offset = 0;
			$filteredQuery = $query->filter($filterQuery)->sort($sortOrder);

			if ($filteredQuery->Count() > 0) {
				if (DB::getConn() instanceof MySQLDatabase) {   //locking currently only works on MySQL

					do {
						$queueObject = $filteredQuery->limit(1, $offset)->first();   //get first item

						if ($queueObject) $lockName = md5($queueObject->URLSegment . $className);
						//try to locking the item's URL, keep trying new URLs until we find one that is free to lock
						$offset++;
					} while($queueObject && !LockMySQL::isFreeToLock($lockName));

					if ($queueObject) {
						$lockSuccess = LockMySQL::getLock($lockName);  //acquire a lock with the URL of the queue item we have just fetched
						if ($lockSuccess) {
							self::remove_duplicates($queueObject->ID);  //remove any duplicates
							self::mark_as_regenerating($queueObject);   //mark as regenerating so nothing else grabs it
							LockMySQL::releaseLock($lockName);			//return the object and release the lock
						}
					}
				} else {
					$queueObject = $filteredQuery->first();
					self::remove_duplicates($queueObject->ID);
					self::mark_as_regenerating($queueObject);
				}
			}
		}

		return $queueObject;    //return the object or null
	}

	/**
	 * Finds the next most prioritized url that needs recaching
	 *
	 * @return string
	 */
	public static function get_next_url() {
		$object = self::get_queue_object('stale');
		if($object) return $object->URLSegment;

		$interval = date('Y-m-d H:i:s', strtotime('-'.self::$minutes_until_force_regeneration.' minutes'));

		// Find URLs that has been stuck in regeneration
		$object = self::get_queue_object('regenerating', $interval);
		if($object) return $object->URLSegment;

		// Find URLs that is erronous and might work now (flush issues etc)
		$object = self::get_queue_object('error', $interval);
		if($object) return $object->URLSegment;

		return '';
	}

	/**
	 * Removes the .html fresh copy of the cache.
	 * Keeps the *.stale.html copy in place,
	 * in order to notify the user of the stale content.
	 *
	 * @param array $URLSegments
	 */
	protected static function remove_old_cache( array $URLSegments ) {
		$publisher = singleton('SiteTree')->getExtensionInstance('FilesystemPublisher');
		if ($publisher) {
			$paths = $publisher->urlsToPaths($URLSegments);
			foreach($paths as $absolutePath) {

				if(!file_exists($publisher->getDestDir().'/'.$absolutePath)) {
					continue;
				}

				unlink($publisher->getDestDir().'/'.$absolutePath);
			}
		}
	}

	/**
	 * Mark this current StaticPagesQueue as a work in progress
	 *
	 * @param StaticPagesQueue $object 
	 */
	protected static function mark_as_regenerating(StaticPagesQueue $object) {
		$now = date('Y-m-d H:i:s');
		DB::query('UPDATE "StaticPagesQueue" SET "LastEdited" = \''.$now.'\', "Freshness"=\'regenerating\' WHERE "ID" = '.$object->ID);
		singleton(__CLASS__)->flushCache();
	}

	/**
	 * Removes all duplicates that has the same URLSegment as $ID
	 *
	 * @param int $ID - ID of the object whose duplicates we want to remove
	 * @return void
	 */
	static function remove_duplicates( $ID ) {
		$obj = DataObject::get_by_id('StaticPagesQueue', $ID);
		if(!$obj) return 0;
		DB::query(
			sprintf('DELETE FROM "StaticPagesQueue" WHERE "URLSegment" = \'%s\' AND "ID" != %d', $obj->URLSegment, (int)$ID)
		);
	}

	/**
	 *
	 * @param string $url
	 * @param bool $onlyStale - Get only stale entries
	 * @return DataObject || false - The first item matching the query
	 */
	protected static function get_by_link($url) {
		$filter = '"URLSegment" = \''.Convert::raw2sql($url).'\'';
		$res = DB::query('SELECT * FROM "StaticPagesQueue" WHERE '.$filter.' LIMIT 1;');
		if(!$res->numRecords()){
			return false;
		}
		return new StaticPagesQueue($res->first());
	}
}
