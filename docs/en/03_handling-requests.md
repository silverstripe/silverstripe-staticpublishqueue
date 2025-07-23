---
title: Handling Requests
summary: Configuring your server to serve statically cached HTML files
icon: server
---

# Handling requests

The idea behind the module is to avoid passing requests through to Silverstripe CMS routing
where possible. To that end, we want to attempt to serve the request from the
static files if we can.

## Using `index.php`

The simplest way to do this is to add the following code block to your `index.php`:

```php
require __DIR__ . '/vendor/autoload.php';

/** --- goes in here ---- */
$requestHandler = require 'staticrequesthandler.php';

// successful cache hit
if ($requestHandler('cache') !== false) {
    die;
} else {
    // do something here if you want, for example add a cache-miss header
    header('X-Cache-Miss: ' . date(Datetime::COOKIE));
}
```

To walk through this a bit:

```php
$requestHandler = require 'staticrequesthandler.php';
```

This includes the function from the static request handler that we
are using to search for the appropriate cache file. Out of the box,
this will look for a `.php` file, then a `.html` file, that matches
the URL.

```php
if ($requestHandler('cache') !== false) {
    die;
}
```

The argument passed through here is the cache directory - by default
this is `cache`, but can be configured via YAML. The function returns
`false` if it doesn't find a cache file. So this means that we only
pass through to Silverstripe for processing if we don't hit a cache
file.

This also takes a second argument, `$urlMapping`, which should be
a callable that processes the URL into a path.

You can see an example `index.php` at the bottom of this page.

## Using `.htaccess`

Alternatively, you can intercept the requests before they hit PHP at all. This is especially useful if
you are only generating `.html` files and don't need the extra overhead. To do this, you'd add a rewrite
rule to your `.htaccess` file like so:

```bash
# Cached content - sub-pages (site in the root of a domain)
RewriteCond %{REQUEST_METHOD} ^GET|HEAD$
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{REQUEST_URI} /(.*[^/])/?$
RewriteCond %{DOCUMENT_ROOT}/cache/%1.html -f
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule .* /cache/%1.html [L]
```

That routes all requests to the `.html` equivalent if it exists. You can see an example
[advanced configuration](./04_advanced-configuration.md#apache) that includes some more advanced configuration options.

## Example `index.php` file

```php
use SilverStripe\Control\HTTPApplication;
use SilverStripe\Control\HTTPRequestBuilder;
use SilverStripe\Core\CoreKernel;

// Find autoload.php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'autoload.php not found';
    exit(1);
}

$requestHandler = require 'staticrequesthandler.php';

// successful cache hit
if ($requestHandler('cache') !== false) {
    die;
}
header('X-Cache-Miss: ' . date(Datetime::COOKIE));

// Build request and detect flush
$request = HTTPRequestBuilder::createFromEnvironment();

// Default application
$kernel = new CoreKernel(BASE_PATH);
$app = new HTTPApplication($kernel);
$response = $app->handle($request);
$response->output();
```
