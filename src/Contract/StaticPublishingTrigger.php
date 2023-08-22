<?php

namespace SilverStripe\StaticPublishQueue\Contract;

use SilverStripe\ORM\SS_List;

/**
 * Describes an object that may wish to trigger updates in other objects as a result of it's own update.
 */
interface StaticPublishingTrigger
{
    /**
     * Provides an SS_List of StaticallyPublishable objects which need to be regenerated.
     *
     * @param array $context An associative array with extra engine-specific information.
     * @return array|SS_List
     */
    public function objectsToUpdate($context);

    /**
     * Provides a SS_list of objects that need to be deleted.
     *
     * @param array $context An associative array with extra engine-specific information.
     * @return array|SS_List
     */
    public function objectsToDelete($context);
}
