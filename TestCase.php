<?php

namespace Brightmarch\TestingBundle;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

use \ReflectionClass,
    \ReflectionMethod;

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
        return date_create();
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
     * Installs the data fixtures for a test case.
     *
     * @param string $managerName
     * @param boolean $append
     * @return boolean
     */
    protected function installDataFixtures($managerName, $append = false)
    {
        $_em = $this->get('doctrine')
            ->getManager($managerName);

        // Reset any fixtures already managed for this entity manager.
        $this->fixtures[$managerName] = [];

        // Get all of the fixtures from the parameters.
        $fixtures = $this->getContainer()
            ->getParameterBag()
            ->all()['fixtures'];

        $_conn = $_em->getConnection();

        // Purge out old data first.
        if (!$append) {
            $entities = array_map(function($fixture) {
                return $fixture['_entity'];
            }, array_reverse($fixtures));

            $entities = array_unique($entities);

            foreach ($entities as $entity) {
                $table = $_em->getClassMetadata($entity)
                    ->getTableName();

                // DELETEs are dramatically faster than TRUNCATEs,
                // at least for small tables in Postgres.
                $_conn->executeUpdate("DELETE FROM $table");
            }
        }

        // Create a converter to convert snake case to camelCase.
        $converter = new CamelCaseToSnakeCaseNameConverter;

        // Next, create the new fixtures.
        foreach ($fixtures as $ref => $fixture) {
            $refClass = new ReflectionClass($fixture['_entity']);
            $entity = $refClass->newInstance();

            foreach ($fixture as $field => $value) {
                // _entity is a special field so it is ignored.
                if ('_entity' !== $field) {
                    // If the value begins with a tilde, it references
                    // another entity that is hopefully already hydrated.
                    if (0 === strpos($value, '~')) {
                        $xref = substr($value, 1);
                        $value = null;

                        if (isset($this->fixtures[$managerName][$xref])) {
                            $value = $this->fixtures[$managerName][$xref];
                        }
                    }

                    // Construct the setter and call it.
                    $field = $converter->denormalize($field);
                    $setter = sprintf('set%s', ucwords($field));

                    $refMethod = new ReflectionMethod($entity, $setter);
                    $refMethod->invoke($entity, $value);
                }
            }

            // Immediately flush the fixture entity.
            $_em->persist($entity);
            $_em->flush();

            // And refresh it so cross relationships work.
            $_em->refresh($entity);
            $this->fixtures[$managerName][$ref] = $entity;
        }

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
            return $this->fixtures[$managerName][$fixture];
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
        if (isset($this->fixtures[$managerName])) {
            return isset($this->fixtures[$managerName][$fixture]);
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
