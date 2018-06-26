# Static Publisher with Queue

[![Build Status](https://travis-ci.org/silverstripe/silverstripe-staticpublishqueue.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-staticpublishqueue)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-staticpublishqueue/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-staticpublishqueue/?branch=master)
[![Code Coverage](https://codecov.io/gh/silverstripe/silverstripe-staticpublishqueue/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-staticpublishqueue/branch/master)

## Brief

This module provides an API for your project to be able to generate a static cache of your pages to enhance
performance by not booting SilverStripe in order to serve requests.

It generates the cache files using the [QueuedJobs module](https://github.com/symbiote/silverstripe-queuedjobs).

[Docs](docs/en/index.md)

## Maintainers

* Ed Linklater <ed@silverstripe.com>

## Requirements

* "silverstripe/framework": "^4.0.2",
* "silverstripe/cms": "^4",
* "silverstripe/config": "^1",
* "symbiote/silverstripe-queuedjobs": "^4.0.6",
* "silverstripe/versioned": "^1.0.2"
