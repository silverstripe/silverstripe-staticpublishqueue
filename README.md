# Static publisher with queue

## Brief

This module provides an API for your project to be able to generate a static HTML cache of your pages to enhance
security (by blocking off the interactive backend requests via your server configuration) and performance (by serving
just HTML).

It provides a queue implementation that:

* allows to rebuild files selectively
* avoids the issues of rebuilding potentially hundreds of related pages synchronously once a page is saved in the CMS.
As the queue is worked off outside of any visitor views or CMS author actions, it allows for a more fine grained control
over the queue, prioritization of URLs, detection of queue duplicates, etc.

The module is optimized for high responsiveness in scenarios where a single edit might trigger hundreds of page
rebuilds, through batching the queue population as well as allowing to run the queue processing task as a continuous
background task (similar to a Unix daemon).

<div class="warning" markdown="1">
Important security note for subsites: when using this module with subsites the separation between the subsites is not
enforced - the files simply reside in subdirectories. Unless you restrict access on the webserver side it will be
possible to cross the subsite boundary, e.g. by hitting `http://onesubsite.co.nz/cache/secondsubsite.co.nz/index.html`.
This will serve up the homepage of the second subsite on the first subsite's domain.
</div>

## Requirements

* SilverStripe 3.1

## Essential configuration guide

By default the module is not applying any of its decorators, and will not affect the behaviour of your application. You
need to configure the module and the webserver before use - this section will walk you through the configuration
activities that you need to perform.

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
because it needs to override the default `PublishableSiteTree` (hence the use of the "After" directives of the config
system).

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

### Webserver configuration

This section contains some examples of webserver configuration.

#### Apache: custom .htaccess and the stale-static-main.php

The `.htaccess` can pass all requests to a separate PHP file for pre-processing.
We've included an example file to get you started: `docs/en/stale-static-main.example.php`.

The Apache web-server looks in the following order to find cached results

* cache/url-segment-of-page.html
* cache/url-segment-of-page.stale.html
* passes to framework/main.php

#### Nginx

There are suggested nginx.conf and nginx.vhost files located in the `/docs/` subfolder that will do the same as the
mysite/stale-static-main.php and if no cached file exists pass it on to Apache backend.

These are just examples and don't pay too much attention to the security of the system. You will need to customise these
for your critical systems.

### Setting up the builder as a cronjob

Recommended cronjob entries to go into a crontab:

	# Cronjob for processing the static publishing queue
	* * * * * www-data /sites/my-website/www/framework/sake dev/tasks/BuildStaticCacheFromQueue daemon=1 verbose=0 >> /tmp/buildstaticcache.log

	# Rebuild the entire static cache at 1am every night".
	0 1 * * * www-data /sites/my-website/www/framework/sake dev/tasks/SiteTreeFullBuildEngine flush=all

## Reference

This section contains a more in-depth discussion on the details of the module.

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

### Engines

The interfaces are designed to be consumed by *engines* which are responsible for putting the desired behaviours into
action. This module provides the following two engines:

* `SiteTreePublishingEngine` - regenerates the static cache files after a page is published/unpublished. This makes use
of both interfaces as object trigger refresh of other objects.
* `SiteTreeFullBuildEngine` - intended to be an overnight task for making sure entire cache is regenerated. This uses
just one of the interfaces as what triggers what is not important for a bulk regeneration.

### Customising behaviours

As long as you respect the provided interfaces, any modifications will work well with the default engines. This means
that the only thing left for you to do is to define behaviours:

* what triggers publishing of what (`StaticPublishingTrigger` interface)
* what URLs belong to an object and need to be updated when object changes (`StaticallyPublishable` interface).

You can provide your own implementations by creating new extensions (see `PublishableRedirectorPage` for a simple
example). You apply these as normal through the config system. You can also implement the interfaces directly on your
classes without using extensions.  will override all static publisher extensions already present.

<div class="hint" markdown="1">
When providing your own implementations you need to make sure you also cater for the default behaviour you might be
replacing. There is no "parent" you can call here because of how the extensions have been structured, and there is only
one "dominant" extension that will be executed.
</div>

Some implementations of the aforementioned interfaces are bundled with this module. The `PublishableSiteTree` supports
the basic static publishing functionality for SiteTree objects - updating itself and the parent on change, and
deleting itself and updating parent on deletion.

`PublishableRedirectorPage` is a slight variation that is designed to couple with the RedirectorPage, and makes sure
this page is published into HTML.

### Cache directory

If not using subsites, all static files will go into `cache` directory in the webroot (unless reconfigured).

If using subsites, each subsite domain will trigger generation of a full copy of the static site into a subdirectory
named after the domain, e.g. `cache/your-subsite.co.nz`.

### The Event system

Events are the preferred way to fill the queue in a way that allows you to build arbitrary dependencies.

Quick example: An event could be fired when a user is saved, and a listener on a forum thread queues up a rebuild of all
his forum post pages.  But at the same time, another listener is registered to queue a rebuild a hypothetical "all forum
members" list.  The user logic doesn't need to know about the object being displayed in forum threads or member lists.

The system is configured in the `_config.php` with registering events with event listeners.

	StaticPagesQueueEvent::register_event('MyEvent', 'MyEventListener'):

This means that the event MyEvent gets triggered, it will notify all objects that implements the interface
MyEventListener.

The events most likely triggered in a `onAfterWrite()` or `onBeforeDelete()`. This is how a class would trigger a
`MyEvent`:

	public function onAfterWrite() {
		parent::onAfterWrite();
		StaticPagesQueueEvent::fire_event(new MyEvent($this));
	}

The `MyEvent` takes an reference to the `DataObject` that triggers this event.

The Event system then calls every implementator of the `MyEventListener` with the method `Object#MyEvent(Event $event)`.
This gives the implementator a reference to the original triggerer.

The listener (implementor) takes care of collecting a list of URLs that needs to be updated in it's control.

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

This DataObject takes of manipulating a list of URLs with priorities and status that is stored in the database.

It also removes (if existing) a fresh page and leaving the system with a stale page in the cache.

### Builder daemon (BuildStaticCacheFromQueue)

A cronjob or a user triggers the following task, which installs itself as a resident daemon:

	framework/sake dev/tasks/BuildStaticCacheFromQueue daemon=1 verbose=0

It will ask the `StaticPagesQueue` to give it urls, sorted by priority, one by one. And recaches them by using the
`FilesystemPublisher`. This will generate a fresh page and a stale page in the cache.

This task uses a pid file to prevent launching two processes in parallel. It is recommended to run this from a cronjob
every minute to make sure the task is alive.

You can run this once-off from command line for debugging purposes using: `daemon=0 verbose=1`.

### Full rebuild (SiteTreeFullBuildEngine)

This task will rebuild the cache in full, making sure all changes have been flushed:

	framework/sake dev/tasks/SiteTreeFullBuildEngine flush=all

We recommend running this nightly to catch all discrepancies from in-flight updates.

### Distributed Static Cache Rebuilding

When using the MySQL database, the static publish queue automatically uses locking to ensure that no two processes
grab an item from the static publishing queue at the same time. This allows any number of processes to build
the static cache concurrently. That is, if the static cache is shared between multiple servers, then all the servers
can work together to re-build the static cache.

### The stale page

A "stale page" is just a copy of the fresh one with a little extra HTML content in it that tells a visitor that the page
is stale. It lives in the same directory structure as the other cached files, but has a `.stale.html` suffix. This stale
copy comes into play as a fallback when the actual cached file is invalidated

To use the stale pages, you need to make sure your webserver is configured to serve these files as a fallback if the
main file is not available.

You also need to include the following snippet in your template so the module knows where to inject the "this page is
stale" message:

	<div id="stale"></div>

### Current status of stale pages and previous building

There are two reports in the admin that shows this information: StaticPagesQueueReport and BuildStaticCacheSummaryReport
SilverStripe 3 will automatically register these reports and they will show up in the admin/reports tab. Both reports
automatically updates themselves every few seconds so you can monitor the state of the queues in real-time.

