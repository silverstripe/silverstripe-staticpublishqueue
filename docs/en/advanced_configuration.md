# Advanced Configuration

As long as you respect the provided interfaces, any modifications will work well with the default engine. This means
that the only thing left for you to do is to define behaviours:

* what triggers publishing of what (`StaticPublishingTrigger` interface)
* what URLs belong to an object and need to be updated when object changes (`StaticallyPublishable` interface).

You can provide your own implementations by creating new extensions (see `PublishableSiteTree` for a simple
example). You apply these as normal through the config system. You can also implement the interfaces directly on your
classes without using extensions.

<div class="hint" markdown="1">
When providing your own implementations you need to make sure you also cater for the default behaviour you might be
replacing. There is no "parent" you can call here because of how the extensions have been structured, and there is only
one "dominant" extension that will be executed.
</div>

Some implementations of the aforementioned interfaces are bundled with this module. The `PublishableSiteTree` supports
the basic static publishing functionality for SiteTree objects - updating itself and the parent on change, and
deleting itself and updating parent on deletion.
