<?php
/**
 * Describes an object that may wish to trigger updates in other objects as a result of it's own update.
 */
interface StaticPublishingTrigger {

	/**
	 * Provides an SS_List of StaticallyPublishable objects which need to be regenerated.
	 *
	 * @param $context An associative array with extra engine-specific information.
	 *
	 * @return SS_List
	 */
	public function objectsToUpdate($context);

	/**
	 * Provides a SS_list of objects that need to be deleted.
	 *
	 * @param $context An associative array with extra engine-specific information.
	 *
	 * @return SS_List
	 */
	public function objectsToDelete($context);

}
