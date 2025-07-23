---
title: Advanced Configuration
summary: Advanced options to improve performance, including specific webserver configurations
icon: sliders-h
---

# Advanced configuration

As long as you respect the provided interfaces, any modifications will work well with the default engine. This means
that the only thing left for you to do is to define behaviours:

- what triggers publishing of what ([`StaticPublishingTrigger`](api:SilverStripe\StaticPublishQueue\Contract\StaticPublishingTrigger) interface)
- what URLs belong to an object and need to be updated when object changes ([`StaticallyPublishable`](api:SilverStripe\StaticPublishQueue\Contract\StaticallyPublishable) interface).

You can provide your own implementations by creating new extensions (see `PublishableSiteTree` for a simple
example). You apply these as normal through the config system. You can also implement the interfaces directly on your
classes without using extensions.

> [!TIP]
> When providing your own implementations you need to make sure you also cater for the default behaviour you might be
> replacing. There is no "parent" you can call here because of how the extensions have been structured, and there is only
> one "dominant" extension that will be executed.

Some implementations of the aforementioned interfaces are bundled with this module. The [`PublishableSiteTree`](api:SilverStripe\StaticPublishQueue\Extension\Publishable\PublishableSiteTree) supports
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

To do so, make your `index.php` file look like this:

```php
require_once '../vendor/silverstripe/staticpublishqueue/includes/functions.php';
$requestHandler = require '../vendor/silverstripe/staticpublishqueue/includes/staticrequesthandler.php';

if ($requestHandler('cache') !== false) {
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
```

### Avoiding PHP all together

For the best performance you can have your webserver (eg: Apache, Nginx, etc) serve the static HTML files directly.

The primary drawback of this is that the cached HTTP Headers will no longer work (as they are sent to the browser by
PHP). This means redirects will use meta refresh tags and/or JavaScript to forward users to new URLs (which will have
an SEO and usability impact for you site); other headers that may be required by your application will also not be
served.

You should consider implementing a redirect map at the server level (eg: in you `.htaccess` file) to avoid any negative
impact of redirect pages.

#### Serve HTML via apache {#apache}

To serve the HTML files via apache without invoking PHP you'll need to modify the default `.htaccess` rules that come with Silverstripe.

```text
### SILVERSTRIPE START ###
<Files *.ss>
    Order deny,allow
    Deny from all
    Allow from 127.0.0.1
</Files>

<Files web.config>
    Order deny,allow
    Deny from all
</Files>

# This denies access to all yml files, since developers might include sensitive
# information in them. See the docs for work-arounds to serve some yaml files
<Files ~ "\.ya?ml$">
    Order allow,deny
    Deny from all
</Files>

ErrorDocument 404 /assets/error-404.html
ErrorDocument 500 /assets/error-500.html

<IfModule mod_alias.c>
    RedirectMatch 403 /silverstripe-cache(/|$)
    RedirectMatch 403 /vendor(/|$)
    RedirectMatch 403 /composer\.(json|lock)
</IfModule>

<IfModule mod_rewrite.c>
    SetEnv HTTP_MOD_REWRITE On
    RewriteEngine On

    ## CONFIG FOR STATIC PUBLISHING
    # Cached content - sub-pages (site in the root of a domain)
    RewriteCond %{REQUEST_METHOD} ^GET|HEAD$
    RewriteCond %{QUERY_STRING} ^$
    RewriteCond %{REQUEST_URI} /(.*[^/])/?$
    RewriteCond %{DOCUMENT_ROOT}/cache/%1.html -f
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule .* /cache/%1.html [L]

    # Cached content - homepage (site in root of a domain)
    RewriteCond %{REQUEST_METHOD} ^GET|HEAD$
    RewriteCond %{QUERY_STRING} ^$
    RewriteCond %{REQUEST_URI} ^/?$
    RewriteCond %{DOCUMENT_ROOT}/cache/index.html -f
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule .* /cache/index.html [L]

    # Config for site in a subdirectory
    # (remove the following four rules if site is on root of a domain. E.g. test.com rather than test.com/my-site)
    # Cached content - sub-pages (site in sub-directory)
    RewriteCond %{REQUEST_METHOD} ^GET|HEAD$
    RewriteCond %{QUERY_STRING} ^$
    RewriteCond %{REQUEST_URI} /(.*[^/])/(.*[^/])/?$
    RewriteCond %{DOCUMENT_ROOT}/%1/cache/%2.html -f
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule .* cache/%2.html [L]

    # Cached content - homepage (site in sub-directory)
    RewriteCond %{REQUEST_METHOD} ^GET|HEAD$
    RewriteCond %{QUERY_STRING} ^$
    RewriteCond %{REQUEST_URI} ^/(.*[^/])/?$
    RewriteCond %{DOCUMENT_ROOT}/%1/cache/index.html -f
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule .* cache/index.html [L]

    ## DYNAMIC CONFIG

    # Process through SilverStripe if no file with the requested name exists.
    # Pass through the original path as a query parameter, and retain the existing parameters.
    # Try finding framework in the vendor folder first
    RewriteCond %{REQUEST_URI} ^(.*)$
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule .* index.php

</IfModule>
### SILVERSTRIPE END ###
```

#### Serve HTML via nginx {#nginx}

To serve the HTML files via ngnix without invoking PHP you'll need to modify the default `nginx.conf` rules that are recommended with Silverstripe CMS.

```text
user www-data;
worker_processes 4;

error_log  /var/log/nginx/error.log;
pid        /var/run/nginx.pid;

events {
    worker_connections 2048;
}

http {
    upstream backend  {
        server 127.0.0.1:8080;
    }

    include /etc/nginx/mime.types;

    default_type application/octet-stream;
    server_tokens off;

    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    sendfile on;
    tcp_nopush on;

    keepalive_timeout 65;
    tcp_nodelay on;

    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;

    # Needed for file upload over reverse proxy
    client_max_body_size 128m;
}
```

And use something similar to the following for `nginx.vhost`:

```text
server {
    listen 80;
    server_name mywebsite.dev;

    root /home/www/mywebsite;
    index index.php index.html;

    # Any assets file, serve up directly. Never pass through to apache
    location ^~ /assets/ {
        break;
    }

    # Any clearly asset files, don't pass request to apache if missing
    location ~* (\.gif|\.jpg|\.png|\.css|\.js)$ {
    }

    # Otherwise, see if the file exists and serve if so, pass through if not (but regexs below take precedence over this)
    # This checks if the static cache has been built, and server that static html.
    # Order is the php copy, then the html copy, an actual file somewhere and at last pass it down to the webserver
    location / {
        try_files /cache/$request_uri.php /cache/$request_uri.html $uri @passthrough;
        open_file_cache max=1000 inactive=120s;
        open_file_cache_valid 5;
        expires 5;
    }

    # PHP request, always pass to apache
    location ~* \.php$ {
        error_page 404 = @passthrough;
        return 404;
    }

    # This is the backend webserver that is defined in the main nginx.conf
    location @passthrough {
        proxy_pass http://backend;
        expires off;
    }

    include /etc/nginx/includes/proxy_buffered.conf;
    include /etc/nginx/includes/deny_sensitive.conf;
}
```
