# BrightmarchTestingBundle

This Symfony bundle makes it easy to write functional tests without the headache. The bundle allows you to easily create a client for executing requests against your application and access the Container to retrieve your services. Additionally, you can create authenticated clients to avoid the headache of having to navigate through a sign-in page to visit authenticated sections of your application.

## Installation
Begin by updating your `composer.json` file with the library name.

```json
{

    "require-dev": {
        "brightmarch/testing-bundle": "1.2.1"
    }

}
```

Install the bundle with Composer.

```bash
php composer.phar update --dev brightmarch/testing-bundle
```

Add the bundle class to your `app/AppKernel.php` file.

```php
public function registerBundles()
{
    // ...

    if (in_array($this->getEnvironment(), array('dev', 'test'))) {
        // ...
        $bundles[] = new Brightmarch\TestingBundle\BrightmarchTestingBundle();
    }

    return $bundles;
}
```

## Usage
Using the bundle is simple. It comes with a single class, `Brightmarch\TestingBundle\TestCase` that your functional test suites can extend.

### Sample Test Suite

```php
<?php

namespace My\AppBundle\Tests\Controller;

use Brightmarch\TestingBundle\TestCase;

class AdminControllerTest extends TestCase
{

    public function testAdminRequiresAuthentication()
    {
        $client = $this->getClient();
        $client->request('GET', $this->getUrl('my_app_admin_index'));

        $this->assertContains('Sign In', $client->getCrawler()->text());
    }

    public function testAdmin()
    {
        $admin = $this->getEntityManager()
            ->getRepository('MyAppBundle:UserAccount')
            ->findOneByEmail('admin@myapp.com');

        // The firewall is named 'admin'.
        $client = $this->authenticate($admin, 'admin');
        $client->request('GET', $this->getUrl('my_example_app_admin_panel'));

        $this->assertContains('Welcome back, Admin', $client->getCrawler()->text());
    }

    public function testStatelessApi()
    {
        $user = $this->getFixture('api_user');

        $client = $this->authenticateStateless($user);
        $client->request('GET', $this->getUrl('my_example_app_api'));

        // Test the JSON response for example.
    }

}
```

### Container
Accessing the Container is simple with the method `getContainer()`. The method takes no arguments and returns a `Symfony\Component\DependencyInjection\Container` object with the following parameters:

* environment: test
* debug: true

You can also get an array of all Container parameters with the `getContainerParameters()` method. It also takes no arguments an returns a multi-dimensional key-value array of parameters.

### Client
You can construct an HTTP client with the `getClient()` method. It takes a single optional array parameter where you can set additional server parameters.

* `array $server=[]`

### Authentication
Testing authenticated features becomes a chore when continually having to sign in as a user to perform them. The `authenticate()` method makes this simple by mimicking the full authentication process. The method takes two parameters, a user entity that implements the `Symfony\Component\Security\Core\User\UserInterface` interface, and the firewall name from the `app/config/security.yml` file that you are wanting to authenticate.

* `Symfony\Component\Security\Core\User\UserInterface $user`
* `string $firewall`

Please note that the `authenticate()` method returns the client you should use for all future interaction with your application. You do not need to call `getClient()` first.

The `1.2.0` release of this bundle introduced a method named `authenticateStateless()` that allows you to authenticate against a stateless firewall. This is helpful for API testing where your API is stateless and requires authorization for every request. Like the `authenticate()` method, this method returns a client you can use to interact with your API. Because you are interacting through your application through its URLs, you do not need to provide a firewall to authenticate against.

### Database Interaction
You can access the Doctrine EntityManager with the `getEntityManager()` method. The method takes no arguments and returns a `Doctrine\ORM\EntityManager` object. Sorry, no Propel access at this time.

### Fixtures
Assuming you are using the [Doctrine Fixtures Bundle][doctrine-fixtures-bundle], you can call the protected method `installDataFixtures()` to install the data fixtures in your database. This method takes a single required parameter: `$fixtureDirectory` and two optional parameters: `$em` and `$append`. The `$fixtureDirectory` should be an absolute path to where your fixtures are stored in your bundle.

The `$em` parameter allows you to specify separate entity managers. If your application relies on two entity managers, you can manage their fixtures differently. For example, if you are working with a legacy system that doesn't work well with deleting data, you can set `$append` to `true` which will prevent the data from being purged.

If you only have a single entity manager, you can leave the `$em` value empty and the bundle will work fine with the default entity manager.

If you have named your fixtures via `addReference()`, you can access them with the `getFixture()` method. This method takes a single required parameter: `$name` and an optional parameter `$em`.

Because `installDataFixtures()` will clear out your database by default before installing the new fixtures, it makes good sense to put it in a `setUp()` call to ensure each test gets a clean set of fixtures. While this will make your tests slower, it will also make them more accurate.

### URL
If you need to generate a URL from a route (a good practice as it allows your URLs to change and your routes to remain constant), you can do so with the `getUrl()` method. It takes three parameters:

* `string $route`
* `array $parameters=[]`
* `boolean $absolute=false`

## License
The MIT License (MIT)

Copyright (c) 2013-2015 Vic Cherubini, Bright March, LLC

[doctrine-fixtures-bundle]: https://packagist.org/packages/doctrine/doctrine-fixtures-bundle
