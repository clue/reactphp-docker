# clue/docker-react [![Build Status](https://travis-ci.org/clue/php-docker-react.svg?branch=master)](https://travis-ci.org/clue/php-docker-react)

Simple async/streaming access to the [Docker](https://www.docker.com/) API, built on top of [React PHP](http://reactphp.org/).

[Docker](https://www.docker.com/) is a popular open source platform
to run and share applications within isolated, lightweight containers.
The [Docker Remote API](https://wiki.asterisk.org/wiki/display/AST/The+Asterisk+Manager+TCP+IP+API)
allows you to control and monitor your containers and images.
Among others, it can be used to list existing images, download new images,
execute arbitrary commands within isolated containers, stop running containers and much more.

* **Async execution of Actions** -
  Send any number of actions (commands) to your Docker daemon in parallel and
  process their responses as soon as results come in.
  The Promise-based design provides a *sane* interface to working with out of bound responses.
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](http://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  This library is merely a very thin wrapper around the Remote API.
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested in the *real world*

> Note: This project is in early alpha stage! Feel free to report any issues you encounter.

## Quickstart example

Once [installed](#install), you can use the following code to access the
Docker API of your local docker daemon:

```php
$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);
$client = $factory->createClient();

$client->version()->then(function ($version) {
    var_dump($version);
});

$loop->run();
```

See also the [examples](examples).

## Usage

### Factory

The `Factory` is responsible for creating your `Client` instance.
It also registers everything with the main `EventLoop`.

```php
$loop = \React\EventLoop\Factory::create();
$factory = new Factory($loop);
```

The `createClient($url = null)` method can be used to create a new `Client`.
It helps with constructing a `Browser` object for the given remote URL.

```php
// create client with default URL (unix:///var/run/docker.sock)
$client = $factory->createClient();

// explicitly use given UNIX socket path
$client = $factory->createClient('unix:///var/run/docker.sock');

// connect via TCP/IP
$client = $factory->createClient('http://10.0.0.2:8000/');
```

### Client

The `Client` is responsible for assembling and sending HTTP requests to the Docker API.
It requires a `Browser` object bound to the main `EventLoop` in order to handle async requests and a base URL.
The recommended way to create a `Client` is using the `Factory` (see above).

All public methods on the `Client` resemble the API described in the [Remote API documentation](https://docs.docker.com/reference/api/docker_remote_api_v1.15/) like this:

```php
$client->containerList($all, $size);
$client->containerCreate($config, $name);
$client->containerStart($name);
$client->containerKill($name, $signal);
$client->containerRemove($name, $v, $force);

$client->imageList($all);
$client->imageSearch($term);
$client->imageCreate($fromImage, $fromSrc, $repo, $tag, $registry, $registryAuth);

$client->info();
$client->version();
```

Listing all available commands is out of scope here, please refer to the [Remote API documentation](https://docs.docker.com/reference/api/docker_remote_api_v1.15/) or the class outline.

Sending requests is async (non-blocking), so you can actually send multiple requests in parallel.
Docker will respond to each request with a response message, the order is not guaranteed.
Sending requests uses a [Promise](https://github.com/reactphp/promise)-based interface that makes it easy to react to when a request is fulfilled (i.e. either successfully resolved or rejected with an error):

```php
$client->version()->then(
    function ($result) {
        var_dump('Result received', $result);
    },
    function (Exception $error) {
        var_dump('There was an error', $error->getMessage());
    }
});
```

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/docker-react": "dev-master"
    }
}
```

## License

MIT
