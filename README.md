# clue/docker-react [![Build Status](https://travis-ci.org/clue/php-docker-react.svg?branch=master)](https://travis-ci.org/clue/php-docker-react)

Simple async/streaming access to the Docker API, built on top of React PHP 

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
