<?php

namespace SilverStripe\StaticPublishQueue\Extension\Publishable;

use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\SS_List;
use SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable;
use SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger;
use SilverStripe\StaticPublishQueue\Extension\Engine\SiteTreePublishingEngine;

/**
 * Bare-bones implementation of a publishable page.
 *
 * You can override this either by implementing one of the interfaces the class directly, or by applying
 * an extension via the config system ordering (inject your extension "before" the PublishableSiteTree).
 *
 * @see SiteTreePublishingEngine
 *
 * @extends DataExtension<SiteTree>
 */
class PublishableSiteTree extends DataExtension implements StaticallyPublishable, StaticPublishingTrigger
{
    public const REGENERATE_RELATIONS_NONE = 'none';
    public const REGENERATE_RELATIONS_DIRECT = 'direct';
    public const REGENERATE_RELATIONS_RECURSIVE = 'recursive';

    private static string $regenerate_children = PublishableSiteTree::REGENERATE_RELATIONS_NONE;

    private static string $regenerate_parents = PublishableSiteTree::REGENERATE_RELATIONS_DIRECT;

    public function getMyVirtualPages()
    {
        return VirtualPage::get()->filter(['CopyContentFrom.ID' => $this->owner->ID]);
    }

    /**
     * @return iterable<SiteTree>
     */
    public function objectsToUpdate($context)
    {
        $list = [];
        $siteTree = $this->getOwner();

        if ($context['action'] === SiteTreePublishingEngine::ACTION_PUBLISH) {
            // Trigger refresh of the page itself
            $list[] = $siteTree;

            // Refresh related virtual pages
            $virtualPages = $siteTree->getMyVirtualPages();

            if ($virtualPages->exists()) {
                foreach ($virtualPages as $virtual) {
                    $list[] = $virtual;
                }
            }

            // For the 'publish' action, we will update children when we are configured to do so. Any config value other
            // than 'none' means that we want to regenerate children at some level
            $regenerateChildren = SiteTree::config()->get('regenerate_children');
            // When the context of urlSegmentChanged has been provided we *must* update children - because all of their
            // URLs will have just changed
            $forceRecursiveRegeneration = $context['urlSegmentChanged'] ?? false;

            // We've either been configured to regenerate (some level) of children, or the above context has been set
            if ($regenerateChildren !== PublishableSiteTree::REGENERATE_RELATIONS_NONE || $forceRecursiveRegeneration) {
                // We will want to recursively add all children if our regenerate_children config was set to Recursive,
                // or if $forceRecursiveRegeneration was set to true
                // If neither of those conditions are true, then we will only be adding the direct children of this
                // parent page
                $recursive = $regenerateChildren === PublishableSiteTree::REGENERATE_RELATIONS_RECURSIVE || $forceRecursiveRegeneration;

                $this->addChildren($list, $siteTree, $recursive);
            }
        }

        // For any of our defined actions, we will update parents when configured to do so. Any config value other than
        // 'none' means that we want to include children at some level
        $regenerateParents = SiteTree::config()->get('regenerate_parents');

        if ($regenerateParents !== PublishableSiteTree::REGENERATE_RELATIONS_NONE) {
            // You can also choose whether to update only the direct parent, or the entire tree
            $recursive = $regenerateParents === PublishableSiteTree::REGENERATE_RELATIONS_RECURSIVE;

            $this->addParents($list, $siteTree, $recursive);
        }

        return $list;
    }

    /**
     * This method controls which caches will be purged
     *
     * @return iterable<SiteTree>
     */
    public function objectsToDelete($context)
    {
        // This context isn't one of our valid actions, so there's nothing to do here
        if ($context['action'] !== SiteTreePublishingEngine::ACTION_UNPUBLISH) {
            return [];
        }

        $list = [];
        $siteTree = $this->getOwner();

        // Trigger cache removal for this page
        $list[] = $siteTree;

        // Trigger removal of the related virtual pages
        $virtualPages = $siteTree->getMyVirtualPages();

        if ($virtualPages->exists()) {
            foreach ($virtualPages as $virtual) {
                $list[] = $virtual;
            }
        }

        // Check if you specifically want children regenerated in all actions. Any config value other than 'none' means
        // that we want to regenerate children at some level
        $regenerateChildren = SiteTree::config()->get('regenerate_children');
        // Check to see if SiteTree enforces strict hierarchy (that being, parents must be published in order for
        // children to be viewed)
        // If strict hierarchy is being used, then we *must* purge all child pages recursively, as they are no longer
        // available for frontend users to view
        $forceRecursiveRegeneration = SiteTree::config()->get('enforce_strict_hierarchy');

        // We've either been configured to include (some level) of children, or enforce_strict_hierarchy was true
        if ($regenerateChildren !== PublishableSiteTree::REGENERATE_RELATIONS_NONE || $forceRecursiveRegeneration) {
            // We will want to recursively add all children if our regenerate_children config was set to Recursive,
            // or if $forceRecursiveRegeneration was set to true
            // If neither of those conditions are true, then we will only be adding the direct children of this
            // parent page
            $recursive = $regenerateChildren === PublishableSiteTree::REGENERATE_RELATIONS_RECURSIVE || $forceRecursiveRegeneration;

            $this->addChildren($list, $siteTree, $recursive);
        }

        return $list;
    }

    /**
     * The only URL belonging to this object is its own URL.
     *
     * @return array
     */
    public function urlsToCache()
    {
        $page = $this->getOwner();

        if ($page instanceof RedirectorPage) {
            // use RedirectorPage::regularLink() so that it returns the url of the page,
            // rather than the url of the target of the RedirectorPage
            $link = $page->regularLink();
        } else {
            $link = $page->Link();
        }

        return [Director::absoluteURL($link) => 0];
    }

    private function addChildren(array &$list, SiteTree $currentPage, bool $recursive = false): void
    {
        // Loop through each Child that this page has. If there are no Children(), then the loop won't process anything
        foreach ($currentPage->Children() as $childPage) {
            $list[] = $childPage;

            // We have requested only to add the direct children of this page, so we'll continue here
            if (!$recursive) {
                continue;
            }

            // Recursively add children
            $this->addChildren($list, $childPage);
        }
    }

    private function addParents(array &$list, SiteTree $currentPage, bool $recursive = false): void
    {
        $parent = $currentPage->Parent();

        // This page is top level, and there is no parent
        if (!$parent?->exists()) {
            return;
        }

        // Add the parent to the list
        $list[] = $parent;

        // We have requested only to add the direct parent, so we'll return here
        if (!$recursive) {
            return;
        }

        // Recursively add parent
        $this->addParents($list, $parent);
    }
}
