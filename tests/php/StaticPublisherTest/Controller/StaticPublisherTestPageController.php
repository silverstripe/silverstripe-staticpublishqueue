<?php

namespace SilverStripe\StaticPublishQueue\Test\StaticPublisherTest\Controller;

use PageController;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\TestOnly;

class StaticPublisherTestPageController extends PageController implements TestOnly
{
    /**
     * @var array
     * @config
     */
    private static $allowed_actions = ['json'];

    /**
     * @return HTTPResponse
     */
    public function json()
    {
        $response = new HTTPResponse('{"firstName": "John"}');
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }
}
