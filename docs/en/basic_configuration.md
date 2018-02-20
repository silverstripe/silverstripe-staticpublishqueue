# Basic Configuration

By default, the extensions `SiteTreePublishingEngine` and `PublishableSiteTree` 
are applied to `SiteTree`, so as soon as the module is installed, it should 
be ready to go.

You'll need to configure a cron job or equivalent to process the queue (if you haven't already):

```bash
* * * * * php /path/to/silverstripe/vendor/bin/sake dev/tasks/ProcessJobQueueTask
```

Which will ensure that the `GenerateStaticCacheJob`s are processed quickly.

Without further configuration, your site won't serve the static cache files. 
See [handling requests](handling_requests.md) for details on how to 
make sure you are passing through to the statically cached files.

Out of the box, the publisher will create a simple `.php` file, which contains
meta information about the cached page and an `.html` file. To only generate
`.html`, you can set the config flag:

```yaml

---
Name: mystaticpublishqueue
After: '#staticpublishqueue'
---
SilverStripe\Core\Injector\Injector:
  SilverStripe\StaticPublishQueue\Publisher:
    class: SilverStripe\StaticPublishQueue\Publisher\FilesystemPublisher
    properties:
      fileExtension: html
```

## Available interfaces

This module comes with two essential interfaces: `StaticPublishingTrigger` and `StaticallyPublishable`. This interfaces
allow objects to define their capabilities related to static publishing.

`StaticPublishingTrigger` should be assigned to an object which is interested in updating other objects as a result
of some change of state. An example here is a `Page` with some associated objects - changes to these objects need to
trigger republishing of the `Page`, but they are not themselves published.

`StaticallyPublishable` complements the trigger. It is assigned to objects which want to be statically published.
Objects are able to claim any amount of URLs (including their own, and also any sub-urls). If you need to trigger 
republishing or URLs assigned to another objects, implement the `StaticPublishingTrigger`. In our example of `Page` 
with related objects, the Page would be publishable, but the objects wouldn't.

Most of the time the Objects will be both acting as triggers (e.g. for themselves) and as publishable objects
(generating HTML files).

## Engines

The interfaces are designed to be consumed by *engines* which are responsible for putting the desired behaviours into
action. This module provides the following engine:

* `SiteTreePublishingEngine` - regenerates the static cache files after a page is published/unpublished. This makes use
of both interfaces as object trigger refresh of other objects.
