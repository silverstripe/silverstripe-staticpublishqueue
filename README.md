# Eventsystem and the static publisher

## Brief

This is system takes care of the business of collecting URLs that needs to be
rebuild, the same URLs as stale and at last rebuilds them. It does this in away
that is designed to leave minimal impact to the visitor and the administrator.

## The Event system

All core code relevant to this system is located in mysite/code/caching

The system is configured in the _config.php with registrering events with
event listeners.

    Event::register_event('BallotUpdateEvent', 'BallotUpdateEventListener'):

This means that the event BallotUpdateEvent gets triggered, it will notify all
objects that implements the interface BallotUpdateEventListener.

The events most likely triggered in a onAfterWrite or onBeforeDelete. This is
how a class would trigger a BallotUpdateEvent:

    public function onAfterWrite() {
        parent::onAfterWrite();
        Event::fire_event(new BallotUpdateEvent($this));
    }

The BallotUpdateEvent takes an reference to the DataObject that triggers this
event.

The Event system then calls every implementator of the BallotUpdateEventListener
with the method Object#ballotUpdateEvent(Event $event). This gives the
implementator a reference to the original triggerer.

The listener (implementor) takes care of collecting a list of URLs that needs to
be updated in it's control. A Candidate_Controller would only create a list of
urls that it has an handler for.

This list of URLs are then sent to a URLArrayObject.

## The URLArrayObject

This is a bit hack-ish approach to solve the issue of preventing the system to
do 100s of DB insert statements separately.

It is a singleton collector of URLs, and at the objects _destruct it calls the
StaticPagesQueue#add_to_queue and StaticPagesQueue#push_urls_to_db

## StaticPagesQueue

This DataObject takes of manipulating a list of URLs with priorities and status
that is stored in the database.

It also removes (if existing) a fresh page and leaving the system with a stale
page in the cache.

## The builder part - the BuildStaticCacheFromQueue

A cronjob or a user triggers the task

    ./sappire/sake dev/tasks/BuildStaticCacheFromQueue

It will ask the StaticPagesQueue to give it urls, prio sorted, one by one. And
recaches them by using the StaticPublisher task.

There is an option to let the BuildStaticCacheFromQueue a bit less chatty by
tagging on the shy=1 param. This is good for cronjobs e.g:

    ./sappire/sake dev/tasks/BuildStaticCacheFromQueue shy=1

This will generate a fresh page and a stale page in the cache.

### The stale page

The stale page is just a copy of the fresh one with a little extra content in it
that tells the user that the page is stale.

## Current status of stale pages and previous building

There are to reports in the admin that shows this information.

## Custom .htaccess and the stale-static-main.php

The .htaccess passes all requests to the mysite/stale-static-main.php.

The stale-static-main.php looks in the following order to find cached results

 - cache/url-segment-of-page.html
 - cache/url-segment-of-page.stale.html
 - passes to sapphire/main.php

# Recommended setup on production environments

There are suggesting nginx.conf and nginx.vhost files located in /mysite/docs/
that will do the same thing as the mysite/stale-static-main.php and if no cached
file exists pass it on to apache backend.

## Setting up the builder as a cronjob

Example of cronjob entry in /etc/cron.d/lgol-staging-build-static-cache-from-queue

    * * * * * www-data /var/sites/lgolstaging/www/sapphire/sake dev/tasks/BuildStaticCacheFromQueue shy=1 >> /tmp/buildstaticcache.log
