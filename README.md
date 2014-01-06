# BrightmarchTestingBundle

This Symfony bundle makes it easy to write functional tests without the headache. The bundle allows you to easily create a client for executing requests against your application and access the Container to retrieve your services. Additionally, you can create authenticated clients to avoid the headache of having to navigate through a sign-in page to visit authenticated sections of your application.

## Installation
1. Begin by updating your `composer.json` file with the library name.

        "require-dev": {
            "brightmarch/testing-bundle": "dev-master"
        }

2. Install the bundle with Composer.

        php composer.phar update --dev brightmarch/testing-bundle

3. Add the bundle class to your `app/AppKernel.php` file.

        public function registerBundles()
        {
            // ...

            if (in_array($this->getEnvironment(), array('dev', 'test'))) {
                // ...
                $bundles[] = new Brightmarch\TestingBundle\BrightmarchTestingBundle();
            }

            return $bundles;
        }

## Usage
Using the bundle is simple. It comes with a single class, `Brightmarch\TestingBundle\TestCase` that your functional test suites can extend.

### Sample Test Suite
    <?php

    namespace My\AppBundle\Tests\Controller;

    use Brightmarch\TestingBundle\TestCase;

    class AdminControllerTest extends TestCase
    {

        public function testAdminRequiresAuthentication()
        {
            $client = $this->getClient();
            $client->request('GET', $this->getUrl('my_app_admin_index'));

            $this->assertContains("Sign In", $client->getCrawler()->text());
        }

        public function testAdmin()
        {
            $admin = $this->getEntityManager()
                ->getRepository('MyAppBundle:UserAccount')
                ->findOneByEmail('admin@myapp.com');

            // 'admin' is the name of the firewall.
            $client = $this->authenticate($admin, 'admin');

            $this->assertContains("Welcome back, Admin", $client->getCrawler()->text());
        }

    }

### Container
Accessing the Container is simple with the method `getContainer()`. The method takes no arguments and returns a `Symfony\Component\DependencyInjection\Container` object with the following parameters:

* environment: test
* debug: true

You can also get an array of all Container parameters with the `getContainerParameters()` method. It also takes no arguments an returns a multi-dimensional key-value array of parameters.

### Client
You can construct an HTTP client with the `getClient()` method. It takes a single optional array parameter where you can set additional server parameters.

* array $server=[]

### Authentication
Testing authenticated features becomes a chore when continually having to sign in as a user to perform them. The `authenticate()` method makes this simple by mimicking the full authentication process. The method takes two parameters, a user entity that extends the `Symfony\Component\Security\Core\User\UserInterface` interface, and the firewall name from the `app/config/security.yml` file that you are wanting to authenticate.

* Symfony\Component\Security\Core\User\UserInterface $user
* string $firewall

Please note that the `authenticate()` method returns the client you should use for all future interaction with your application. You do not need to call `getClient()` first.

### Database Interaction
You can access the Doctrine EntityManager with the `getEntityManager()` method. The method takes no arguments and returns a `Doctrine\ORM\EntityManager` object. Sorry, no Propel access at this time.

### URL
If you need to generate a URL from a route (a good practice as it allows your URLs to change and your routes to remain constant), you can do so with the `getUrl()` method. It takes three parameters:

* string $route
* array $parameters=[]
* boolean $absolute=false

## License
The MIT License (MIT)

Copyright (c) 2013 Vic Cherubini, Bright March
