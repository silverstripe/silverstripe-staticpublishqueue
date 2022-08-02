# Static Publisher with Queue

[![CI](https://github.com/silverstripe/silverstripe-staticpublishqueue/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-staticpublishqueue/actions/workflows/ci.yml)
[![Silverstripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Brief

This module provides an API for your project to be able to generate a static cache of your pages to enhance
performance by not booting Silverstripe in order to serve requests.

It generates the cache files using the [QueuedJobs module](https://github.com/symbiote/silverstripe-queuedjobs).

[Docs](docs/en/index.md)

## Requirements

* "silverstripe/framework": "^4.0.2",
* "silverstripe/cms": "^4",
* "silverstripe/config": "^1",
* "symbiote/silverstripe-queuedjobs": "^4.5",
* "silverstripe/versioned": "^1.0.2"
