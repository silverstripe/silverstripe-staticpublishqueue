# Handling Requests

The idea behind the module is to avoid passing requests through to SilverStripe
where possible. To that end, we want to attempt to serve the request from the 
static files if we can. The simplest way to do this is to add the following 
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

You can see an example `index.php` [here](docs/examples/index.php)
