<?php
/** Methods for acquiring and releasing application-level locks when using the MySQL database */
class LockMySQL {

	private static $lockNamePostfix = null;

	/**
	 * Get the name of the lock to use specific to the database name of the current application, as a SQL-escaped string.
	 */
	protected static function lockNamePostfix() {
		if (!self::$lockNamePostfix) {
			self::$lockNamePostfix = Convert::raw2sql('_'.DB::query('select database()')->value());
		}

		return self::$lockNamePostfix;
	}

	/** Sets a lock so that no two processes can run at the same time.
	 * Return false = 0 if acquiring the lock fails; otherwise return true, if lock was acquired successfully.
	 * Lock is automatically released if connection to the database is broken (either normally or abnormally).
	 * Waits for the lock to free up for $secondsToTry seconds.
	 */
	static function getLock($lockName = '', $secondsToTry = 5) {
		return DB::query("SELECT GET_LOCK('".$lockName.self::$lockNamePostfix."',".$secondsToTry.")")->value();
	}

	/** Checks if the another process is already running:
	 */
	static function isFreeToLock($lockName = '') {
		//true = lock is free for the taking (Default)
		return DB::query("SELECT IS_FREE_LOCK('".$lockName.self::$lockNamePostfix."')")->value();
	}

	/** Remove the lock file to allow another process to run
	 * (if the execution aborts (e.g. due to an error) all locks are automatically released) */
	static function releaseLock($lockName = '') {
		return DB::query("SELECT RELEASE_LOCK('".$lockName.self::$lockNamePostfix."')")->value();
	}

	function run($request) {
		if ($this->isFreeToLock()) {
			if ($this->getLock()) {  //try to get the lock, but do nothing if someone else is quicker and gets it first
				//do stuff

				$this->releaseLock();
			} else {
				Debug::message('Aborting because another process acquired lock before this process could.', false);
			}
		} else {
			Debug::message('Aborting because of existing lock.', false);
		}
	}

}