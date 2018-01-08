<?php

namespace SilverStripe\StaticPublishQueue\Contract;

interface StaticPublisher
{
    /**
     * @param string $url
     * @return array A result array
     */
    public function publishURL($url, $forcePublish = false);

    /**
     * @param string $url
     * @return array A result array
     */
    public function purgeURL($url);
}
