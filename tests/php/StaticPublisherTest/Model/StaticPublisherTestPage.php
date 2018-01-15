<?php

namespace SilverStripe\StaticPublishQueue\Test\StaticPublisherTest\Model;

use Page;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\TestOnly;

class StaticPublisherTestPage extends Page implements TestOnly
{
    private static $table_name = 'SPQ_StaticPublisherTestPage';

    public function getTemplate()
    {
        $templateResource = ModuleLoader::getModule('silverstripe/staticpublishqueue')
            ->getResource('/tests/templates/StaticPublisherTestPage.ss');
        return $templateResource->getPath();
    }
}
