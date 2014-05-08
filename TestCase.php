<?php

namespace Brightmarch\TestingBundle;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

use \DateTime;

abstract class TestCase extends WebTestCase
{

    /** @var Container */
    private $container = null;

    public function tearDown()
    {
        parent::tearDown();

        $this->container = null;
    }

    /**
     * Returns the current time.
     * 
     * @return DateTime
     */
    public function now()
    {
        return new DateTime;
    }

    /**
     * Provides an easy way to authenticate a user against a firewall.
     *
     * @param Symfony\Component\Security\Core\User\UserInterface $user
     * @param string $firewall
     * @return Symfony\Bundle\FrameworkBundle\Client
     */
    protected function authenticate(UserInterface $user, $firewall)
    {
        $securityKey = sprintf('_security_%s', $firewall);

        $client = $this->getClient();
        $client->followRedirects(false);

        $session = $client->getContainer()->get('session');
        $session->start();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);

        $token = new UsernamePasswordToken($user, null, $firewall, $user->getRoles());
        $session->set($securityKey, serialize($token));
        $session->save();

        return $client;
    }

    /**
     * Gets the container for this kernel.
     *
     * @return Symfony\Component\DependencyInjection\Container
     */
    protected function getContainer()
    {
        if (!$this->container) {
            $kernel = static::createKernel();
            $kernel->boot();

            $this->container = $kernel->getContainer();
        }

        return $this->container;
    }

    /**
     * Gets the Container parameters.
     *
     * @return array
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
