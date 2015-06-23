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
    private $referenceRepositories = [];

    /** @const string */
    const DEFAULT_REPOSITORY = '__default__';

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
     * @param string $em
     * @param boolean $append
     * @return boolean
     */
    protected function installDataFixtures($fixtureDirectory, $em = null, $append = false)
    {
        $entityManager = $this->getEntityManager($em);

        $loader = new ContainerAwareLoader($this->getContainer());
        $loader->loadFromDirectory($fixtureDirectory);

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures(), $append);

        $em = $this->getEntityManagerName($em);
        $this->referenceRepositories[$em] = $executor->getReferenceRepository();
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
     * Gets the container parameters as a key/value array.
     *
     * @return array
     */
    protected function getContainerParameters()
    {
        return $this->getContainer()
            ->getParameterBag()
            ->all();
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
     * Gets the Doctrine EntityManager.
     *
     * @param string $em
     * @return Doctrine\ORM\EntityManager
     */
    protected function getEntityManager($em = null)
    {
        return $this->getContainer()
            ->get('doctrine')
            ->getManager($em);
    }

    /**
     * Get fixture by name.
     *
     * @param string $fixture
     * @param string $em
     * @return mixed
     */
    protected function getFixture($fixture, $em = null)
    {
        $em = $this->getEntityManagerName($em);

        if ($this->hasFixture($fixture, $em)) {
            return $this->referenceRepositories[$em]
                ->getReference($fixture);
        }

        return null;
    }

    /**
     * Has fixture.
     *
     * @param string $fixture
     * @param string $em
     * @return boolean
     */
    public function hasFixture($fixture, $em = null)
    {
        $em = $this->getEntityManagerName($em);

        if (array_key_exists($em, $this->referenceRepositories)) {
            return $this->referenceRepositories[$em]
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
        return $this->getContainer()
            ->get('router')
            ->generate($route, $parameters, $absolute);
    }

    /**
     * Returns the entity manager name if it is set,
     * otherwise it returns the default entity manager.
     *
     * @param string $em
     * @return string
     */
    private function getEntityManagerName($em = null)
    {
        return (empty($em) ? self::DEFAULT_REPOSITORY : $em);
    }

}
