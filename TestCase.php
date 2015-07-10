<?php

namespace Brightmarch\TestingBundle;

use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;

use \DateTime;

abstract class TestCase extends WebTestCase
{

    /** @var Container */
    private $container = null;

    /** @var array */
    private $fixtures = [];

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
     * Creates an authenticated client against a firewall.
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
     * Creates a stateless authenticated client from a UserInterface object.
     *
     * @param Symfony\Component\Security\Core\User\UserInterface $user
     * @return Symfony\Bundle\FrameworkBundle\Client
     */
    protected function authenticateStateless(UserInterface $user)
    {
        return $this->getClient([
            'PHP_AUTH_USER' => $user->getUsername(),
            'PHP_AUTH_PW' => $user->getPassword()
        ]);
    }

    /**
     * Installs the data fixtures for a test case. Assumes Doctrine Fixtures Bundle
     * is installed and enabled in the kernel.
     *
     * @param string $fixtureDirectory
     * @param string $managerName
     * @param boolean $append
     * @return boolean
     */
    protected function installDataFixtures($fixtureDirectory, $managerName, $append = false)
    {
        $_em = $this->get('doctrine')
            ->getManager($managerName);

        $loader = new ContainerAwareLoader($this->getContainer());
        $loader->loadFromDirectory($fixtureDirectory);

        $purger = new ORMPurger($_em);
        $executor = new ORMExecutor($_em, $purger);
        $executor->execute($loader->getFixtures(), $append);

        $this->fixtures[$managerName] = $executor->getReferenceRepository();

        return true;
    }

    /**
     * Boots a kernel and runs a console command. The output of the command is returned.
     *
     * @param Symfony\Component\Console\Command\Command $command
     * @param array $arguments
     * @return string
     */
    protected function runCommand(Command $command, $arguments = [])
    {
        $application = new Application($this->getKernel());
        $application->add($command);

        $command = $application->find($command->getName());
        $commandTester = new CommandTester($command);
        $commandTester->execute($arguments);

        return $commandTester->getDisplay();
    }

    /**
     * Returns a newly built kernel.
     *
     * @return \AppKernel
     */
    protected function getKernel()
    {
        $kernel = static::createKernel();
        $kernel->boot();

        return $kernel;
    }

    /**
     * Gets the container for this kernel.
     *
     * @return Symfony\Component\DependencyInjection\Container
     */
    protected function getContainer()
    {
        if (!$this->container) {
            $this->container = $this->getKernel()
                ->getContainer();
        }

        return $this->container;
    }

    /**
     * Returns a service from the container.
     *
     * @return mixed
     */
    protected function get($service)
    {
        return $this->getContainer()
            ->get($service);
    }

    /**
     * Gets a web client for navigating URLs.
     *
     * @param array $server
     * @return Client
     */
    protected function getClient(array $server = [])
    {
        $client = $this->getContainer()
            ->get('test.client');

        $client->setServerParameters($server);

        return $client;
    }

    /**
     * Get fixture by name.
     *
     * @param string $fixture
     * @param string $managerName
     * @return mixed
     */
    protected function getFixture($fixture, $managerName)
    {
        if ($this->hasFixture($fixture, $managerName)) {
            return $this->fixtures[$managerName]
                ->getReference($fixture);
        }

        return null;
    }

    /**
     * Has fixture.
     *
     * @param string $fixture
     * @param string $managerName
     * @return boolean
     */
    public function hasFixture($fixture, $managerName)
    {
        if (array_key_exists($managerName, $this->fixtures)) {
            return $this->fixtures[$managerName]
                ->hasReference($fixture);
        }

        return false;
    }

    /**
     * Generates a URL based on a route.
     *
     * @param string $route
     * @param array $parameters
     * @param boolean $absolute
     * @return string
     */
    protected function getUrl($route, array $parameters = [], $absolute = false)
    {
        return $this->get('router')
            ->generate($route, $parameters, $absolute);
    }

}
