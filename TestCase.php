<?php

namespace Brightmarch\TestingBundle;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class TestCase extends WebTestCase
{

    /** @var Container */
    private $container;

    protected function loginAs(UserInterface $user, $firewall)
    {
    }

    protected function getContainer()
    {
        if (!$this->container) {
            $kernel = static::createKernel();
            $kernel->boot();
        }

        return $kernel->getContainer();
    }

    /**
     * Gets the Container parameters.
     *
     * @return Symfony\Component\DependencyInjection\ParameterBag\ParameterBag
     */
    protected function getContainerParameters()
    {
        return $this->getContainer()->parameters;
    }

    /**
     * Gets a web client for navigating URLs.
     *
     * @param array $server
     * @return Client
     */
    protected function getClient(array $server=[])
    {
        $client = $this->getContainer()->get('test.client');
        $client->setServerParameters($server);

        return $client;
    }

    /**
     * Gets the Doctrine EntityManager.
     *
     * @return Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    /**
     * Generates a URL based on a route.
     *
     * @param string $route
     * @param array $parameters
     * @param boolean $absolute
     * @return string
     */
    protected function getUrl($route, array $parameters=[], $absolute=false)
    {
        return $this->getContainer()
            ->get('router')
            ->generate($route, $parameters, $absolute);
    }

}
