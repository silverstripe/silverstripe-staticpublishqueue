<?php
 if(interface_exists('EnvironmentCheck')) {
 	
 	/**
	 * Used in the 'environmentchecks' module.
	 * Checks that the oldest queued item is newer than a certain age,
	 * which points to a functioning queue processing.
	 */
 	class StaticPagesQueueHealthCheck implements EnvironmentCheck {
	
		protected $maxAgeMins;

		/**
		 * @param Int In minutes.
		 */
		function __construct($maxAgeMins = 60) {
			$this->maxAgeMins = $maxAgeMins;
		}

		function check() {
			$oldest = DataObject::get_one('StaticPagesQueue', null, false, '"Created" ASC');
			if($oldest) {
				$oldestDate = strtotime($oldest->Created);
				$maxDate = SS_Datetime::now()->Format('U') - ($this->maxAgeMins*60);
				if($oldestDate < $maxDate) {
					return array(
						EnvironmentCheck::ERROR, 
						sprintf('Oldest queue item older than %d minutes (%s)', $this->maxAgeMins, date('c', $oldestDate))
					);
				}

				return array(
					EnvironmentCheck::OK, 
					sprintf('Oldest queue item younger than %d minutes (%s)', $this->maxAgeMins, date('c', $oldestDate))
				);
			}

			return array(EnvironmentCheck::OK, 'No items queued');
		}

	}
}