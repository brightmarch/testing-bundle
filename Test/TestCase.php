<?php

namespace Brightmarch\TestingBundle\Test;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class TestCase extends WebTestCase
{

    protected function getContainer()
    {
    }

    protected function getUrl($route, array $parameters=[])
    {
    }

}
