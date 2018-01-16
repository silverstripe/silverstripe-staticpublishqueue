# Static Publisher with Queue

## Brief

This module provides an API for your project to be able to generate a static cache of your pages to enhance
security (by blocking off the interactive backend requests via your server configuration) and performance 
(by not booting SilverStripe in order to serve requests).

It generates the cache files using the QueuedJobs module.

[Docs](docs/en/index.md)

## Maintainers

* Ed Linklater <ed@silverstripe.com>

## Requirements

* "silverstripe/framework": "^4.0.2",
* "silverstripe/cms": "^4",
* "silverstripe/assets": "^1",
* "silverstripe/config": "^1",
* "silverstripe/errorpage": "^1",
* "symbiote/silverstripe-queuedjobs": "^4.0.6",
* "silverstripe/versioned": "^1.0.2"
