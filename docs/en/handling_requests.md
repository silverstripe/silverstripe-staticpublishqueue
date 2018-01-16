# Handling Requests

The idea behind the module is to avoid passing requests through to SilverStripe
where possible. To that end, we want to attempt to serve the request from the 
static files if we can. 

## Using `index.php`

The simplest way to do this is to add the following 
code block to your `index.php`:

```php
require __DIR__ . '/vendor/autoload.php';

/** --- goes in here ---- */
$requestHandler = require 'vendor/silverstripe/staticpublishqueue/includes/staticrequesthandler.php';

// successful cache hit
if (false !== $requestHandler('cache')) {
    die;
} else {
    // do something here if you want, for example add a cache-miss header
    header('X-Cache-Miss: ' . date(\Datetime::COOKIE));
}
```

To walk through this a bit:

```php
$requestHandler = require 'vendor/silverstripe/staticpublishqueue/includes/staticrequesthandler.php';
```

This includes the function from the static request handler that we 
are using to search for the appropriate cache file. Out of the box, 
this will look for a `.php` file, then a `.html` file, that matches 
the URL.

```php
if (false !== $requestHandler('cache')) {
    die;
}
```

The argument passed through here is the cache directory - by default 
this is `cache`, but can be configured via yaml. The function returns 
`false` if it doesn't find a cache file. So this means that we only 
pass through to SilverStripe for processing if we don't hit a cache 
file.

This also takes a second argument, `$urlMapping`, which should be 
a callable that processes the URL into a path.

You can see an example `index.php` [here](../examples/index.php)

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
[here](../examples/.htaccess.example) that includes some more advanced configuration options.

## Using Nginx

There are a couple of example configurations in the [examples dir](../examples) of Nginx templates, 
but you'll need to ensure they are optimized for the security of your system.
