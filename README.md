# Eventsystem and the static publisher

## Brief

This module is designed to work together with SilverStripe's `StaticPublisher` class,
which allows to build static HTML caches of every page (for increased security and performance).

It allows to rebuild files selectively through a database queue of URLs,
rather than relying on the `RebuildStaticCache` task in core which rebuilds all pages irrespective of them being changed or not.

At the same time, it avoids the issues of rebuilding potentially hundreds of related pages 
synchronously once a page is saved in the CMS. As the queue is worked off outside of any
visitor views or CMS author actions, it allows for a more fine grained control over the queue,
prioritization of URLs, detection of queue duplicates, etc.

The module is optimized for high responsiveness in scenarios
where a single edit might trigger hundreds of page rebuilds,
through batching the queue population as well as allowing to
run the queue processing task as a continuous background task (a Unix daemon).

# Quick Setup Instructions
Here is a quick guide for how to set up basic static publishing. For more detailed customisations
see the detailed guide below.

### Set up cron job
 - SilverStripe 3.1 (for a module that works with 3.0, see the 1.0 branch)
 - A Linux or Mac web-server

### Set up cron job
Run the following on the command line from the web-root folder of your SilverStripe installation:

	php staticpublishqueue/scripts/create_cachedir.php
	php staticpublishqueue/scripts/htaccess_rewrite.php
	sudo php staticpublishqueue/scripts/create_cronjob.php

- - -
# Detailed Configuration Guide

## Static Base Directory
The static base directory is the base directory that the Static Publishing will use as a basis for all static
assets refernces (js, css, etc.) using the HTML "base" tag. In normal cases you don't need to change this.
But if you are using a proxy server or load-balancer, then it is important to set this to the actual public
URL of the website in the following two places:

in: _ss_environment.php

	$_FILE_TO_URL_MAPPING['/var/www/my-site'] = 'http://www.my-awesome-silverstripe-website.com';

in: mysite/_config/my-config.yml

	FilesystemPublisher:
		static_base_url: http://www.my-awesome-silverstripe-website.com

## The Event system

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

## The URLArrayObject

This is a bit hack-ish approach to solve the issue of preventing the system to
do 100s of DB insert statements separately.

It is a singleton collector of URLs, and at the objects `_destruct()` it calls the
`StaticPagesQueue#add_to_queue` and `StaticPagesQueue#push_urls_to_db`.

## StaticPagesQueue

This DataObject takes of manipulating a list of URLs with priorities and status
that is stored in the database.

It also removes (if existing) a fresh page and leaving the system with a stale
page in the cache.

## The builder part - the BuildStaticCacheFromQueue

A cronjob or a user triggers the task

    ./framework/sake dev/tasks/BuildStaticCacheFromQueue

It will ask the `StaticPagesQueue` to give it urls, sorted by priority, one by one. And
recaches them by using the `StaticPublisher` task built into SilverStripe core.

There is an option to let the `BuildStaticCacheFromQueue` a bit less chatty by
tagging on the verbose=0 param. This is good for cronjobs e.g:

    ./framework/sake dev/tasks/BuildStaticCacheFromQueue verbose=0

This will generate a fresh page and a stale page in the cache.

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

## Current status of stale pages and previous building

There are two reports in the admin that shows this information: StaticPagesQueueReport and BuildStaticCacheSummaryReport
SilverStripe 3 will automatically register these reports and they will show up in the admin/reports tab.
Both reports automatically updates themselves every few seconds so you can monitor the state of the queues in real-time.

## Custom .htaccess and the stale-static-main.php

The `.htaccess` can pass all requests to a separate PHP file for pre-processing.
We've included an example file to get you started: `docs/en/stale-static-main.example.php`.

The Apache web-server looks in the following order to find cached results

 - cache/url-segment-of-page.html
 - cache/url-segment-of-page.stale.html
 - passes to framework/main.php

## Using Nginx

There are suggested nginx.conf and nginx.vhost files located in the `/docs/` subfolder
that will do the same thing as the mysite/stale-static-main.php and if no cached
file exists pass it on to Apache backend.

## Setting up the builder as a cronjob

Example of cronjob entry in `/etc/cron.d/`

	#Cronjob for processing the static publishing queue
    * * * * * www-data /sites/my-website/www/framework/sake dev/tasks/BuildStaticCacheFromQueue daemon=1 verbose=0 >> /tmp/buildstaticcache.log

	#Rebuild the entire static cache at 1am every night".
	0 1 * * * www-data /sites/my-website/www/framework/sake dev/tasks/RebuildStaticCacheTask flush=all