<?php

/**
 * Description of StaticPagesQueueReport
 *
 */
class StaticPagesQueueReport extends SS_Report {

	/**
	 * Dataobject that this report is reporting on
	 *
	 * @var string
	 */
	protected $dataClass = 'StaticPagesQueue';

	/**
	 * Title as shown in admin
	 *
	 * @return string
	 */
	public function title() {
		return 'Stale pages in queue';
	}

	/**
	 *
	 * @param array $params
	 * @param array $sort
	 * @param int $limit
	 * @return DataObjectSet or false
	 */
	public function sourceRecords(array $params, $sort, $limit) {
		if($sort) {
			$parts = explode(' ', $sort);
			$field = $parts[0];
			$direction = $parts[1];
			$sort = '"'.$field.'" '.$direction;
		} else {
			$sort = '"Priority" DESC, "ID" ASC';
		}
		if($limit) {
			$limit = 'LIMIT '.(int)$limit['start'].", ".(int)$limit['limit'];
		} else {
			$limit = '';
		}
		
		$sql = 'SELECT MAX("Created") as "Created", "URLSegment", MAX("Priority") as "Priority", "Freshness" 
				FROM "StaticPagesQueue" 
				GROUP BY "ID", "URLSegment", "Freshness" 
				ORDER BY '.$sort.'
				'.$limit.'';
		$result = DB::query($sql);		
		
		$set = new ArrayList();
		if(!$result->numRecords()){
			return $set;
		}
		foreach($result as $row) {
			$set->push(new DataObject(array(
				"Priority"=>$row['Priority'],
				"URLSegment"=>$row['URLSegment'],
				"Created"=>$row['Created'],
				"Freshness"=>$row['Freshness'],
				
			)));
		}
		return $set;
	}
	
	/**
	 * Return the {@link SQLQuery} that provides your report data.
	 */
	function sourceQuery($params) {
		if($this->hasMethod('sourceRecords')) {
			return $this->sourceRecords()->dataQuery();
		} else {
			user_error("Please override sourceQuery()/sourceRecords() and columns() or, if necessary, override getReportField()", E_USER_ERROR);
		}
	}

	/**
	 * Which columns to show in the report
	 *
	 * @return array
	 */
	public function columns() {
		return array(
			"Priority" => array ("Priority" => "Priority"),
			"URLSegment" => array("URLSegment" => "URLSegment"),
			"Created" => array ("Created" => "Created"),
			"Freshness" => array ("Freshness" => "Freshness"),
		);
	}

	public function getReportField() {
		$field = parent::getReportField();

		if (class_exists('GridFieldAjaxRefresh')) {
			$field->getConfig()->addComponent(new GridFieldAjaxRefresh(5000,true));
		}

		return $field;
	}
}

