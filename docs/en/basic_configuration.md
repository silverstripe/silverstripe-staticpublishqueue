# Basic Configuration

By default, the extensions `SiteTreePublishingEngine` and `PublishableSiteTree` 
are applied to `SiteTree`, so as soon as the module is installed, it should 
be ready to go.

You'll need to configure a cron job or equivalent to process the queue:

```bash
*/1 * * * * php /path/to/silverstripe/vendor/bin/sake dev/tasks/ProcessJobQueueTask
```

Which will ensure that the `GenerateStaticCacheJob`s are processed quickly.

Without further configuration, your site won't serve the static cache files. 
See [handling requests](docs/en/handling_requests.md) for details on how to 
make sure you are passing through to the statically cached files.
