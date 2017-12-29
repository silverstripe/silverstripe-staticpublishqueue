<?php

namespace SilverStripe\StaticPublishQueue\Extension\Publishable;

use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger;

/**
 * Bare-bones impelmentation of a publishable page.
 *
 * You can override this either by implementing one of the interfaces the class directly, or by applying
 * an extension via the config system ordering (inject your extension "before" the PublishableSiteTree).
 *
 * @TODO: re-implement optional publishing of all the ancestors up to the root? Currently it only republishes the parent
 *
 * @see SiteTreePublishingEngine
 */
class PublishableSiteTree extends DataExtension implements StaticallyPublishable, StaticPublishingTrigger
{

    public function getMyVirtualPages()
    {
        return VirtualPage::get()->filter(array('CopyContentFrom.ID' => $this->owner->ID));
    }

    /**
     * @param array $context
     * @return ArrayList
     */
    public function objectsToUpdate($context)
    {
        $list = ArrayList::create();
        switch ($context['action']) {
            case 'publish':
                // Trigger refresh of the page itself.
                $list->push($this->getOwner());

                // Refresh the parent.
                if ($this->getOwner()->ParentID) {
                    $list->push($this->getOwner()->Parent());
                }

                // Refresh related virtual pages.
                $virtuals = $this->getOwner()->getMyVirtualPages();
                if ($virtuals->exists()) {
                    foreach ($virtuals as $virtual) {
                        $list->push($virtual);
                    }
                }
                break;

            case 'unpublish':
                // Refresh the parent
                if ($this->owner->ParentID) {
                    $list->push($this->owner->Parent());
                }
                break;
        }
        return $list;
    }

    /**
     * @param array $context
     * @return ArrayList
     */
    public function objectsToDelete($context)
    {
        $list = ArrayList::create();
        switch ($context['action']) {
            case 'unpublish':
                // Trigger cache removal for this page.
                $list->push($this->getOwner());

                // Trigger removal of the related virtual pages.
                $virtuals = $this->getOwner()->getMyVirtualPages();
                if ($virtuals->exists()) {
                    foreach ($virtuals as $virtual) {
                        $list->push($virtual);
                    }
                }
                break;
        }
        return $list;
    }

    /**
     * The only URL belonging to this object is it's own URL.
     */
    public function urlsToCache()
    {
        if ($this->getOwner() instanceof RedirectorPage) {
            $link = $this->getOwner()->regularLink();
        } else {
            $link = $this->getOwner()->Link();
        }
        return array($link => 0);
    }
}
