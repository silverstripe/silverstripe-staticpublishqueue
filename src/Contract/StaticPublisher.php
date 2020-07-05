<?php

namespace SilverStripe\StaticPublishQueue\Contract;

interface StaticPublisher
{
    /**
     * @param string $url
     * @return array A result array
     */
    public function publishURL(string $url, ?bool $forcePublish = false): array;

    /**
     * @param string $url
     * @return array A result array
     */
    public function purgeURL(string $url): array;

    /**
     * @return array
     */
    public function getPublishedURLs(): array;
}
