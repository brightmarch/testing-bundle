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

            $client = $this->authenticate($admin, 'admin');

            $this->assertContains("Welcome back, Admin", $client->getCrawler()->text());
        }

    }

### Container
Accessing the Container is simple with the method `getContainer()`. The method takes no arguments and returns a container with the following parameters:
* environment: test
* debug: true

### Client

### Authentication

### Database Interaction

### URL

## License
The MIT License (MIT)

Copyright (c) 2013 Vic Cherubini, Bright March
