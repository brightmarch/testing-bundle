# BrightmarchTestingBundle

This Symfony bundle makes it easy to write functional tests without the headache and without any additional libraries. The bundle allows you to easily create a client for executing requests against your application and access the Symfony Container to retrieve your services. Additionally, you can create authenticated clients to avoid the headache of having to navigate through a sign-in page to visit authenticated sections of your application.

Fixtures are also a cinch with this bundle. You no longer need to write individual classes to hydrate your database fixtures - simply define them in YAML and the bundle will take care of hydrating and persisting them for you!

## Installation
Begin by updating your `composer.json` file with the library name.

```json
{

    "require-dev": {
        "brightmarch/testing-bundle": "2.0.0"
    }

}
```

Install the bundle with Composer.

```bash
composer update --dev brightmarch/testing-bundle
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
        $admin = $this->get('doctrine')
            ->getManager('app')
            ->getRepository('MyAppBundle:UserAccount')
            ->findOneByEmail('admin@myapp.com');

        // The firewall is named 'admin'.
        $client = $this->authenticate($admin, 'admin');
        $client->request('GET', $this->getUrl('my_example_app_admin_panel'));

        $this->assertContains('Welcome back, Admin', $client->getCrawler()->text());
    }

    public function testStatelessApi()
    {
        $user = $this->getFixture('api_user', 'app');

        $client = $this->authenticateStateless($user);
        $client->request('GET', $this->getUrl('my_example_app_api'));

        // Test the JSON response for example.
    }

}
```

### Container
Accessing the Container is simple with the method `getContainer()`. The method takes no arguments and returns a `Symfony\Component\DependencyInjection\Container` object with the following parameters set:

* environment: test
* debug: true

You can also get any service from the container with the method `get()`. The method takes a single argument: the name of the service you wish to retrieve.

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
Starting with version `1.3.0` the `TestCase` class requires you to use a named entity manager. All access to the entity manager for installing and retrieving fixtures must be done through a named manager.

Because the `get()` method is exposed to retrieve any service, you can access the Doctrine entity manager like this:

```php
$_em = $this->get('doctrine')
    ->getManager('app');
```

### Fixtures
Starting with version 2.0.0, this bundle can handle hydrating and installing your database fixtures automatically. You do not need to rely on the Doctrine Fixtures Bundle and instead can write your fixtures in YAML and have the bundle automatically persist them.

The `TestCase` class has a protected method named `installDataFixtures()` that will install the fixtures in a relational database using Doctrine (Mongo is not supported). It takes two parameters, one required and one optional:

* `string $managerName`
* `boolean $append=false`

The `$managerName` parameter is the entity manager to run the entities through. The `$append` parameter determines if the entities should be appended to the database or purged first. By default, all data is purged first.

It is recommended you call the `installDataFixtures()` method in a parent level `setUp()` method so it installs the data fixtures prior to every test execution. This will slow down your tests, but will ensure they are executed in isolation.

To begin, create a file named `fixtures.yml` in the `app/config/` directory of your application. You will need to create two levels of nesting to define your fixtures:

```yml
parameters:
    fixtures:
```

You will place all of your fixture information under the `fixtures` node. For example, if you had an entity that represented users in your application, you could create a fixture named `admin_user` with the following `fixtures.yml` file:

```yml
parameters:
    fixtures:
        admin_user:
            _entity: MyCompany\AppBundle\Entity\User
            username: admin_user
            password: my_password
            full_name: The Boss
            age: 42
            gender: Male
```

When `installDataFixtures()` is called, it will construct a new `User` object, hydrate it by calling `setUsername()`, `setPassword()`, `setFullName()`, `setAge()`, and `setGender()`. It will then be persisted to the database. The fixture will be stored in an internal array under the name `admin_user`.

The field names in your fixture can either use `snake_case` or `camelCase`. For example, `full_name` and `fullName` will both be used to call `setFullName()`.

You can retrieve that fixture in any test by using the `getFixture()` method which takes two required parameters:

* `string $fixture`
* `string $managerName`

The `$fixture` parameter is the name of the fixture to retrieve (`admin_user` for example) and the `$managerName` parameter is the name of the entity manager that is tracking that fixture.

Cross referenced entities can also be configured in the `fixtures.yml` file as well. For example, after you created the `admin_user`, if you needed to create a `product` fixture that references the `admin_user`, you do so by prefixing the value with a tilde:

```yml
parameters:
    fixtures:
        admin_user:
            _entity: MyCompany\AppBundle\Entity\User
            username: admin_user
            password: my_password
            full_name: The Boss
            age: 42
            gender: Male

        product:
            _entity: MyCompany\AppBundle\Entity\Product
            create_user: ~admin_user
            name: My Awesome Gadget
            price: 4299
```

Doctrine expects a `DateTime` object if you have your fields configured as `date` or `datetime` types. If you had a field in your fixtures named `ordered_at` with the value `2015-08-01 15:36:45`, the insert would fail because Doctrine would expect that to be an object.

To get around this, if any value in your fixtures is parseable in the aforementioned format, it will automatically be converted to a `DateTime` object. That is, to say, if `date_create_from_forat('Y-m-d H:i:s', $value)` returns a `DateTime` object, it will be used for that field in the entity.

Because the fixtures will be built in order that they are defined in the file, the `installDataFixtures()` method will know to call `setCreateUser()` on the `Product` class with the `User` object that is an instance of `admin_user`.

Finally, entities are purged in reverse order. In the above example, the `product` is purged first, and the `admin_user` second.

### URL
If you need to generate a URL from a route (a good practice as it allows your URLs to change and your routes to remain constant), you can do so with the `getUrl()` method. It takes three parameters:

* `string $route`
* `array $parameters=[]`
* `boolean $absolute=false`

## License
The MIT License (MIT)

Copyright (c) 2013-2015 Vic Cherubini, Bright March, LLC

[doctrine-fixtures-bundle]: https://packagist.org/packages/doctrine/doctrine-fixtures-bundle
