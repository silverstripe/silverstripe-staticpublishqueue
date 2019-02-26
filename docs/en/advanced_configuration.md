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

## Further improved performance

Static Publish Queue has been built to balance (developer) usability (and featureset) with performance. As such
there are ways to get better performance from the module if you're willing to accept some limitations / drawbacks.

### Invoke the static request handler before the composer autoloader

The composer autoloader allows us to include the `staticrequesthandler.php` file without knowledge of where it lives in
the filesystem as we make use of the [`include-path` property](https://getcomposer.org/doc/04-schema.md#include-path).
However, the composer autoloader can take up the majority of the processing time when serving a static page. As such you
can tightly couple to the vendor path to provide a performance benefit.

To do so, make your `index.php` file look like this (NB: if you're running using the `public` folder (SS 4.1+), you'll
need to use `../vendor/` instead of `vendor/` for the require paths):

```php
<?php

//use ...

require_once 'vendor/silverstripe/staticpublishqueue/includes/functions.php';
$requestHandler = require 'vendor/silverstripe/staticpublishqueue/includes/staticrequesthandler.php';

if (false !== $requestHandler('cache')) {
    die;
} else {
    header('X-Cache-Miss: ' . date(DateTime::COOKIE));
}

// Find autoload.php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo "autoload.php not found";
    exit(1);
}

...
```

### Avoiding PHP all together

For the best performance you can have your webserver (eg: Apache, Nginx, etc) serve the static HTML files directly.

The primary drawback of this is that the cached HTTP Headers will no longer work (as they are sent to the browser by
PHP). This means redirects will use meta refresh tags and/or javascript to forward users to new URLs (which will have
an SEO and usability impact for you site); other headers that may be required by your application will also not be
served.

You should consider implementing a redirect map at the server level (eg: in you `.htaccess` file) to avoid any negative
impact of redirect pages.

#### Serve HTML via Apache

To serve the HTML files via apache without invoking PHP you'll need to modify the default `.htaccess` rules that come
with SilverStripe. Please see our [`.htaccess` example](../examples/.htaccess.example).

#### Serve HTML via Nginx

To serve the HTML files via apache without invoking PHP you'll need to modify the default `nginx.conf` rules that are
recommended with SilverStripe. Please see our [`nginx.conf` example](../examples/nginx.vhost).
