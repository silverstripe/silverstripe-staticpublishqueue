<?php
/**
 * Describes an object that may wish to trigger updates in other objects as a result of it's own update.
 *
 * Available context information:
 * * action - name of the executed action: publish or unpublish
 */
interface StaticPublishingTrigger {

	/**
	 * Provides an SS_List of StaticallyPublishable objects which need to be regenerated.
	 *
	 * @param $context An associative array with information on the action.
	 *
	 * @return DataList of Page.
	 */
	public function objectsToUpdate($context);

	/**
	 * Provides a SS_list of objects that need to be deleted.
	 *
	 * @param $context An associative array with information on the action.
	 *
	 * @return DataList of Page.
	 */
	public function objectsToDelete($context);

}
