<?php

/**
 * Shows and report from the BuildStaticCacheSummary dataobject
 *
 */
class BuildStaticCacheSummaryReport extends SS_Report {

	/**
	 *
	 * @var string
	 */
	protected $dataClass = 'BuildStaticCacheSummary';

	/**
	 *
	 * @return string
	 */
	public function title() {
		return 'Summaries from Static building';
	}

	/**
	 *
	 * @return array
	 */
	public function columns() {
		return array(
			"ID" => array ("ID" => "ID"),
			"Finished" => array ("Finished" => "Finished"),
			"LastEdited" => array(
				"title" => 'Last update',
				'casting' => 'SS_Datetime->Ago'
			),
			"Pages" => array ("Created" => "Created"),
			"AverageTime" => array ("AverageTime" => "AverageTime"),
			"MemoryUsage" => array ("MemoryUsage" => "MemoryUsage"),
			"PID" => array ("PID" => "PID"),
			"Created" => array("Created" => "Created"),
		);
	}

	/**
	 *
	 * @param unknown $params
	 * @param array $sort
	 * @param int $limit
	 * @return DataObjectSet or false
	 */
	function sourceRecords($params, $sort, $limit) {
		if($sort) {
			$parts = explode(' ', $sort);
			$field = $parts[0];
			$direction = $parts[1];

			$sort = $field.' '.$direction;
		} else {
			$sort = $this->dataClass.'.ID DESC';
		}

		return DataObject::get($this->dataClass, "", $sort, null, $limit);
	}

	public function getReportField() {
		$field = parent::getReportField();

		if (class_exists('GridFieldAjaxRefresh')) {
			$field->getConfig()->addComponent(new GridFieldAjaxRefresh(20000,true));
		}

		return $field;
	}
}
