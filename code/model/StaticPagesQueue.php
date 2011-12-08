<?php
/**
 * This class responsibility is twofold:
 * 1) Holding the data for a prioritized queue of URLs that needs to be static cached
 * 2) Interaction with that queue
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
    protected static $realtime = false;

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
	 * Set this to true to insert entries directly into the database queue, 
	 * otherwise it will be inserted during __deconstruct time 
	 *
	 * @param bool $realtime 
	 */
    public static function realtime( $realtime ) {
        self::$realtime = $realtime;
    }

	/**
	 *
	 * @return bool
	 */
    public static function is_realtime() {
        return self::$realtime;
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
			$t = DB::query($insertSQL);
		}
		self::remove_old_cache(self::$urls);
		// Flush the cache so DataObject::get works correctly
		if( DB::affectedRows() ) singleton(__CLASS__)->flushCache();
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
        if(!$object) {
			return false;
		}
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
		if(!$url) {
			return;
		}

        $existingObject = self::get_by_link($url);
        $existingObject->Freshness = 'error';
        $existingObject->write();
    }
	
	/**
	 * Finds the next most prioritized url that needs recaching
	 *
	 * @return string
	 */
	public static function get_next_url() {
		$now = date('Y-m-d H:i:s');
		$sortOrder = '"Priority" DESC, "Created" ASC';
		$object = DataObject::get_one(__CLASS__, '"Freshness" in (\'stale\')  ', false, $sortOrder);
		if($object) {
			self::remove_duplicates($object->ID);
			self::mark_as_regenerating($object);
			return $object->URLSegment;
		}

		if(DB::getConn() instanceof MySQLDatabase) {
			$interval = sprintf(
				'"LastEdited" < \'%s\' - INTERVAL %d MINUTE',
				$now,
				self::$minutes_until_force_regeneration
			);
		} elseif(DB::getConn() instanceof SQLite3Database) {
			$interval = sprintf(
				'"LastEdited" < datetime(\'%s\', \'-%date(format) MINUTE\')',
				$now,
				self::$minutes_until_force_regeneration
			);
		}
		// Find URLs that is erronous and might work now (flush issues etc)
		$object = DataObject::get_one(__CLASS__, '"Freshness" = \'error\' AND ' . $interval, false, $sortOrder);
        if($object) {
            self::remove_duplicates($object->ID);
            self::mark_as_regenerating( $object );
            return $object->URLSegment;
        }
        return '';
    }

	/**
	 * Removes the .html fresh copy of the cache
	 *
	 * @param array $URLSegments
	 */
	protected static function remove_old_cache( array $URLSegments ) {
		$publisher = singleton('SiteTree')->getExtensionInstance('FilesystemPublisher');
		$paths = $publisher->urlsToPaths($URLSegments);
		foreach($paths as $absolutePath) {

			if(!file_exists($publisher->getDestDir().'/'.$absolutePath)) {
				continue;
			}
			unlink($publisher->getDestDir().'/'.$absolutePath);
		}
	}

	/**
	 * Mark this current StaticPagesQueue as a work in progress
	 *
	 * @param StaticPagesQueue $object 
	 */
    protected function mark_as_regenerating(StaticPagesQueue $object) {
        $now = date('Y-m-d H:i:s');
        DB::query('UPDATE StaticPagesQueue SET LastEdited = \''.$now.'\', Freshness=\'regenerating\' WHERE ID = '.$object->ID);
        singleton(__CLASS__)->flushCache();
    }
	
    /**
		 * Removes all duplicates that has the same URLSegment as $ID
		 *
		 * @param int $ID - ID of the object whose duplicates we want to remove
		 * @return int - how many duplicates that was removed
		 */
		protected static function remove_duplicates( $ID ) {
			$result = DB::query('DELETE FROM "StaticPagesQueue" WHERE "ID" NOT IN (SELECT MIN("ID") FROM "StaticPagesQueue" GROUP BY "URLSegment","ID")');
			if(!$total = DB::affectedRows()) {
				return 0;
			}
			return $total;
		}

    /**
     *
     * @param string $url
     * @param bool $onlyStale - Get only stale entries
     * @return DataObject || false - The first item matching the query
     */
    protected static function get_by_link($url) {
        $filter = '"URLSegment" = \''.Convert::raw2sql($url).'\'';
        $res = DB::query('SELECT * FROM StaticPagesQueue WHERE '.$filter.' LIMIT 1;');
        if(!$res->numRecords()){
            return false;
        }
        return new StaticPagesQueue($res->first());
    }
}