# Static publisher with queue

## Brief

This module provides an API for your project to be able to generate a static HTML cache of your pages to enhance
security and performance.

It provides a queue implementation that:

* allows to rebuild files selectively
* avoids the issues of rebuilding potentially hundreds of related pages synchronously once a page is saved in the CMS.
As the queue is worked off outside of any visitor views or CMS author actions, it allows for a more fine grained control
over the queue, prioritization of URLs, detection of queue duplicates, etc.

The module is optimized for high responsiveness in scenarios where a single edit might trigger hundreds of page
rebuilds, through batching the queue population as well as allowing to run the queue processing task as a continuous
background task (similar to a Unix daemon).

## Requirements

* SilverStripe 3.1

## Configuration guide

### Applying extensions

Module comes with some basic implementations to use out of the box (see "Reference" section below for information about
provided interfaces). You can apply these implementations using the config system to make all your Pages publishable:

	---
	Name: mysiteconfig_redirector
	After: 'staticpublishqueue/*'
	---
	RedirectorPage:
	  extensions:
	    - PublishableRedirectorPage
	---
	Name: mysiteconfig_staticpublishqueue
	After: 'staticpublishqueue/*','#mysiteconfig_redirector'
	---
	SiteTree:
	  extensions:
	    - PublishableSiteTree
	    - SiteTreePublishingEngine
	    - FilesystemPublisher('cache', 'html')

The order is significant - PublishableRedirectorPage needs to be applied before the `mysiteconfig_staticpublishqueue`, 
because it needs to override the default `PublishableSiteTree`.

### Overriding extensions

You can provide your own implementations of `StaticPublishingTrigger` and `StaticallyPublishable` by creating a
new extension similar to the `PublishableRedirectorPage`, and applying it through the config system where needed.

You can also implement the interfaces directly on your classes without using extensions. Be cautious though, as this
will override all static publisher extensions already present!

Note: something to keep in mind is that when providing your own implementations you need to make sure you also cater
for the default behaviour you will be replacing. There is no `parent::urlsToCache` function you can call here because
of how the extensions have been structured.

### BaseURL

The static base directory is the base directory that the Static Publishing will use as a basis for all static assets
refernces (js, css, etc.) using the HTML "base" tag. This needs to be configured in the following two locations.

In `_ss_environment.php` (with trailing slash!):

	$_FILE_TO_URL_MAPPING['/var/www/my-site'] = 'http://your-domain.co.nz/';

In your config file (without traling slash!):

	FilesystemPublisher:
		static_base_url: http://your-domain.co.nz

If using subsites, this will only apply to the main site. Subsites will use their respective domains as configured in
the CMS.

### Custom .htaccess and the stale-static-main.php

The `.htaccess` can pass all requests to a separate PHP file for pre-processing.
We've included an example file to get you started: `docs/en/stale-static-main.example.php`.

The Apache web-server looks in the following order to find cached results

* cache/url-segment-of-page.html
* cache/url-segment-of-page.stale.html
* passes to framework/main.php

### Using Nginx

There are suggested nginx.conf and nginx.vhost files located in the `/docs/` subfolder
that will do the same thing as the mysite/stale-static-main.php and if no cached
file exists pass it on to Apache backend.

### Setting up the builder as a cronjob

Example of cronjob entry in `/etc/cron.d/`

	#Cronjob for processing the static publishing queue
	* * * * * www-data /sites/my-website/www/framework/sake dev/tasks/BuildStaticCacheFromQueue daemon=1 verbose=0 >> /tmp/buildstaticcache.log

	#Rebuild the entire static cache at 1am every night".
	0 1 * * * www-data /sites/my-website/www/framework/sake dev/tasks/RebuildStaticCacheTask flush=all

## Reference

### Available interfaces

This module comes with two essential interfaces: `StaticPublishingTrigger` and `StaticallyPublishable`. This interfaces
allow objects to define their capabilities related to static publishing.

`StaticPublishingTrigger` should be assigned to an object which is interested in updating other objects as a result
of some change of state. An example here is a Page with some associated objects - changes to these objects need to
trigger republishing of the Page, but they are not themselves published.

`StaticallyPublishable` complements the trigger. It is assigned to objects which want to be statically published.
Objects are able to claim any amount of URLs (inlcuding their own, and also any sub-urls). It is important that each
URL is only claimed once, by a single object. If you need to trigger republishing or URLs assigned to another objects,
implement the `StaticPublishingTrigger`. In our example of Page with related objects, the Page would be publishable, but
the objects wouldn't.

Most of the time the Objects will be both acting as triggers (e.g. for themselves) and as publishable objects
(generating HTML files).

### Applying extensions to your classes

Some implementations of the aforementioned interfaces are bundled with this module. The `PublishableSiteTree` supports
the basic static publishing functionality for SiteTree objects - updating itself and the parent on change, and
deleting itself and updating parent on deletion.

`PublishableRedirectorPage` is a slight variation that is designed to couple with the RedirectorPage, and makes sure
this page is published into HTML.

On the generation side, the capabilities provided by these implementations are consumed by:

* `SiteTreePublishingEngine` which is able to trigger republishing on CMS operations using both interfaces
* `RebuildStaticCacheTask` which only searches for `StaticallyPublishable` and makes sure all the requested URLs
are refreshed in case a trigger missed something.

### Cache directory

If not using subsites, all static files will go into `cache` directory in the webroot (unless reconfigured).

If using subsites, each subsite domain will trigger generation of a full copy of the static site into a subdirectory
named after the domain, e.g. `cache/your-subsite.co.nz`.

### The Event system

Events are the preferred way to fill the queue in a way that
allows you to build arbitrary dependencies.

Quick example: An event could be fired when a user is saved,
and a listener on a forum thread queues up a rebuild of all his forum post pages.
But at the same time, another listener is registered to queue a rebuild a hypothetical "all forum members" list.
The user logic doesn't need to know about the object being displayed in forum threads or member lists.

The system is configured in the `_config.php` with registering events with event listeners.

	StaticPagesQueueEvent::register_event('MyEvent', 'MyEventListener'):

This means that the event MyEvent gets triggered, it will notify all
objects that implements the interface MyEventListener.

The events most likely triggered in a `onAfterWrite()` or `onBeforeDelete()`. This is
how a class would trigger a `MyEvent`:

	public function onAfterWrite() {
		parent::onAfterWrite();
		StaticPagesQueueEvent::fire_event(new MyEvent($this));
	}

The `MyEvent` takes an reference to the `DataObject` that triggers this event.

The Event system then calls every implementator of the `MyEventListener`
with the method `Object#MyEvent(Event $event)`. This gives the
implementator a reference to the original triggerer.

The listener (implementor) takes care of collecting a list of URLs that needs to
be updated in it's control. 

This list of URLs are then sent to a `URLArrayObject`.

### The URLArrayObject

The URLArrayObject class has a static function that you can use to add pages directly to the static publishing queue. 
This is especially useful if you don't need the layer of indirection that the events system provides. 
Here is a code example:

	//Insert a bunch of pages
	$pages = Page::get();
	foreach($pages as $page) {
		if ($page->URLSegment == RootURLController::get_homepage_link()) {
			//high priority page that should be republished before other pages
			$urls[$page->Link()] = 90;
		} else {
			//regular page with default priority of 50
			$urls[] = $page->Link();
		}
	}
	URLArrayObject::add_urls($urls);

At the end of the PHP execution cycle (when the object's `_destruct()` method is called) the object
inserts all the added URLs into the database. Everything gets inserted in one big insert, rather than 
doing a whole bunch of slow database insert queries.

It also provides an interface for smuggling contextual information about the related object within the GET parameters of
the URL.

### StaticPagesQueue

This DataObject takes of manipulating a list of URLs with priorities and status
that is stored in the database.

It also removes (if existing) a fresh page and leaving the system with a stale
page in the cache.

### Builder daemon (BuildStaticCacheFromQueue)

A cronjob or a user triggers the following task, which installs itself as a resident daemon:

	framework/sake dev/tasks/BuildStaticCacheFromQueue daemon=1 verbose=0

It will ask the `StaticPagesQueue` to give it urls, sorted by priority, one by one. And
recaches them by using the `FilesystemPublisher`. This will generate a fresh page and a stale page in the cache.

This task uses a pid file to prevent launching two processes in parallel. It is recommended to run this from a cronjob
every minute to make sure the task is alive.

You can run this once-off from command line for debugging purposes using: `daemon=0 verbose=1`.

### Full rebuild (RebuildStaticCacheTask)

This task will rebuild the cache in full, making sure all changes have been flushed:

	framework/sake dev/tasks/RebuildStaticCacheTask flush=all

We recommend running this nightly to catch all discrepancies from in-flight updates.

### Distributed Static Cache Rebuilding

When using the MySQL database, the static publish queue automatically uses locking to ensure that no two processes
grab an item from the static publishing queue at the same time. This allows any number of processes to build
the static cache concurrently. That is, if the static cache is shared between multiple servers, then all the servers
can work together to re-build the static cache.

### The stale page

A "stale page" is just a copy of the fresh one with a little extra HTML content in it
that tells a visitor that the page is stale. It lives in the same directory structure
as the other cached files, but has a `.stale.html` suffix. This stale copy comes into play
as a fallback when the actual cached file is invalidated

### Current status of stale pages and previous building

There are two reports in the admin that shows this information: StaticPagesQueueReport and BuildStaticCacheSummaryReport
SilverStripe 3 will automatically register these reports and they will show up in the admin/reports tab.
Both reports automatically updates themselves every few seconds so you can monitor the state of the queues in real-time.

