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

## Excluding page types
By default, all pages which inherit from `SiteTree` will be included in the static publishing system.  This can cause issues on some pages, especially those which contain a CSRF token (eg. forms) since the token will also be cached and hence prevent submissions from being validated.

You can exclude pages from being statically generated on a class-by-class basis by adding a `urlsToCache()` method to your page class which returns an empty array:

```php
class MyFormPage extends Page
{

    public function urlsToCache() {
        return [];
    }
}
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

#### Example:  Triggering a republish of the homepage after an update to a DataObject

In order for the triggers to get picked up and executed, make sure to add the SiteTreePublishingEngine to your DataObject.
```yaml
Project\ORM\YourDataObject:
  extensions:
    - SilverStripe\StaticPublishQueue\Extension\Engine\SiteTreePublishingEngine
```

Then implement the StaticPublishingTrigger interface on your DataObject:

```php
class YourDataObject extends DataObject implements staticPublishingTrigger 
{

    public function objectsToUpdate($context) 
    {
        return HomePage::get()->First();  // return 1 or multiple publishable objects
    }

     public function objectsToDelete($context)  
     {
        // return your publishable objects here
     }
}

```

## Cache information / Debugging

If required, a timestamp can be added to the generated HTML to show when the file was created.  The timestamp is added as an HTML comment immediately before the closing `</html>` tag (on pages which do not include this tag, enabling this functionality will have no effect).

This functionality can be enabled in the module by adding a configuration setting to your project, eg:

```yaml

---
Name: mystaticpublishqueueconfig
---

SilverStripe\StaticPublishQueue\Publisher:
  add_timestamp: true
```

Note: Changing this setting does not have an instant effect on the cached pages.  The timestamp will only be added/removed when the cached page is regenerated.

## Forcing SSL during cache generation

Add the following to your .env file:

```
SS_STATIC_FORCE_SSL="true"
```

Enabling this option means you need to force your site to use SSL. If you don't do this, the site won't load properly over plain HTTP.
