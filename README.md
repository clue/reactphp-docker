# clue/docker-react [![Build Status](https://travis-ci.org/clue/php-docker-react.svg?branch=master)](https://travis-ci.org/clue/php-docker-react)

Simple async/streaming access to the [Docker](https://www.docker.com/) API, built on top of [React PHP](http://reactphp.org/).

[Docker](https://www.docker.com/) is a popular open source platform
to run and share applications within isolated, lightweight containers.
The [Docker Remote API](https://docs.docker.com/reference/api/docker_remote_api_v1.15/)
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

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Factory](#factory)
    * [createClient()](#createclient)
  * [Client](#client)
    * [Commands](#commands)
    * [Promises](#promises)
    * [Blocking](#blocking)
    * [TAR streaming](#tar-streaming)
    * [JSON streaming](#json-streaming)
  * [JsonProgressException](#jsonprogressexception)
* [Install](#install)
* [License](#license)

## Quickstart example

Once [installed](#install), you can use the following code to access the
Docker API of your local docker daemon:

```php
$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);
$client = $factory->createClient();

$client->imageSearch('clue')->then(function ($images) {
    var_dump($images);
});

$loop->run();
```

See also the [examples](examples).

## Usage

### Factory

The `Factory` is responsible for creating your `Client` instance.
It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage).

```php
$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);
```

#### createClient()

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

#### Commands

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

// many, many more…
```

Listing all available commands is out of scope here, please refer to the
[Remote API documentation](https://docs.docker.com/reference/api/docker_remote_api_v1.15/)
or the [class outline](src/Client.php).

Each of these commands supports async operation and either *resolves* with its *results*
or *rejects* with an `Exception`.
Please see the following section about [promises](#promises) for more details.

#### Promises

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

If this looks strange to you, you can also use the more traditional [blocking API](#blocking).

#### Blocking

As stated above, this library provides you a powerful, async API by default.

If, however, you want to integrate this into your traditional, blocking environment,
you should look into also using [clue/block-react](https://github.com/clue/php-block-react).

The resulting blocking code could look something like this:

```php
use Clue\React\Block;

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);
$client = $factory->createClient();

$promise = $client->imageInspect('busybox');

try {
    $results = Block\await($promise, $loop);
    // resporesults successfully received
} catch (Exception $e) {
    // an error occured while performing the request
}
```

Similarly, you can also process multiple commands concurrently and await an array of results:

```php
$promises = array(
    $client->imageInspect('busybox'),
    $client->imageInspect('ubuntu'),
);

$inspections = Block\awaitAll($promises, $loop);
```

Please refer to [clue/block-react](https://github.com/clue/php-block-react#readme) for more details.

#### TAR streaming

The following API endpoints resolve with a string in the [TAR file format](https://en.wikipedia.org/wiki/Tar_%28computing%29):

```php
$client->containerExport($container);
$client->containerCopy($container, $config);
```

Keep in mind that this means the whole string has to be kept in memory.
This is easy to get started and works reasonably well for smaller files/containers.

For bigger containers it's usually a better idea to use a streaming approach,
where only small chunks have to be kept in memory.
This works for (any number of) files of arbitrary sizes.
The following API endpoints complement the default Promise-based API and return
a [`Stream`](https://github.com/reactphp/stream) instance instead:

```php
$stream = $client->containerExportStream($image);
$stream = $client->containerCopyStream($image, $config);
```

Accessing individual files in the TAR file format string or stream is out of scope
for this library.
Several libraries are available, one that is known to work is [clue/tar-react](https://github.com/clue/php-tar-react).

See also the [copy example](examples/copy.php) and the [export example](examples/export.php).

#### JSON streaming

The following API endpoints take advantage of [JSON streaming](https://en.wikipedia.org/wiki/JSON_Streaming):

```php
$client->imageCreate();
$client->imagePush();
```

What this means is that these endpoints actually emit any number of progress
events (individual JSON objects).
At the HTTP level, a common response message could look like this:

```
HTTP/1.1 200 OK
Content-Type: application/json

{"status":"loading","current":1,"total":10}
{"status":"loading","current":2,"total":10}
…
{"status":"loading","current":10,"total":10}
{"status":"done","total":10}
```

The user-facing API hides this fact by resolving with an array of all individual
progress events once the stream ends:

```php
$client->imageCreate('clue/streamripper')->then(
    function ($data) {
        // $data is an array of *all* elements in the JSON stream
    },
    function ($error) {
        // an error occurred (possibly after receiving *some* elements)
        
        if ($error instanceof Io\JsonProgressException) {
            // a progress message (usually the last) contains an error message
        } else {
            // any other error, like invalid request etc.
        }
    }
);
```

Keep in mind that due to resolving with an array of all progress events,
this API has to keep all event objects in memory until the Promise resolves.
This is easy to get started and usually works reasonably well for the above
API endpoints.

If you're dealing with lots of concurrent requests (100+) or 
if you want to access the individual progress events as they happen, you
should consider using a streaming approach instead,
where only individual progress event objects have to be kept in memory.
The following API endpoints complement the default Promise-based API and return
a [`Stream`](https://github.com/reactphp/stream) instance instead:
     
```php
$stream = $client->imageCreateStream();
$stream = $client->imagePushStream();
```

The resulting stream will emit the following events:

* `progress`: for *each* element in the update stream
* `error`:    once if an error occurs, will close() stream then
  * Will emit an [`Io\JsonProgressException`](#jsonprogressexception) if an individual progress message contains an error message
  * Any other `Exception` in case of an transport error, like invalid request etc.
* `close`:    once the stream ends (either finished or after "error")

Please note that the resulting stream does not emit any "data" events, so
you will not be able to pipe() its events into another `WritableStream`.

```php
$stream = $client->imageCreateStream('clue/redis-benchmark');
$stream->on('progress', function ($data) {
    // data will be emitted for *each* complete element in the JSON stream
    echo $data['status'] . PHP_EOL;
});
$stream->on('close', function () {
    // the JSON stream just ended, this could(?) be a good thing
    echo 'Ended' . PHP_EOL;
});
```

See also the [pull example](examples/pull.php) and the [push example](examples/push.php).

### JsonProgressException

The `Io\JsonProgressException` will be thrown by [JSON streaming](#json-streaming)
endpoints if an individual progress message contains an error message.

The `getData()` method can be used to obtain the progress message.

## Install

The recommended way to install this library is [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/docker-react": "~0.2.0"
    }
}
```

## License

MIT
