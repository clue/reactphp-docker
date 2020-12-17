# clue/reactphp-docker

[![CI status](https://github.com/clue/reactphp-docker/workflows/CI/badge.svg)](https://github.com/clue/reactphp-docker/actions)

Async, event-driven access to the [Docker Engine API](https://docs.docker.com/develop/sdk/), built on top of [ReactPHP](https://reactphp.org/).

[Docker](https://www.docker.com/) is a popular open source platform
to run and share applications within isolated, lightweight containers.
The [Docker Engine API](https://docs.docker.com/develop/sdk/)
allows you to control and monitor your containers and images.
Among others, it can be used to list existing images, download new images,
execute arbitrary commands within isolated containers, stop running containers and much more.
This lightweight library provides an efficient way to work with the Docker Engine API
from within PHP. It enables you to work with its images and containers or use
its event-driven model to react to changes and events happening.

* **Async execution of Actions** -
  Send any number of actions (commands) to your Docker daemon in parallel and
  process their responses as soon as results come in.
  The Promise-based design provides a *sane* interface to working with out of order responses.
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](https://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  This library is merely a very thin wrapper around the [Docker Engine API](https://docs.docker.com/develop/sdk/).
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested in the *real world*

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Client](#client)
    * [Commands](#commands)
    * [Promises](#promises)
    * [Blocking](#blocking)
    * [Command streaming](#command-streaming)
    * [TAR streaming](#tar-streaming)
    * [JSON streaming](#json-streaming)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Quickstart example

Once [installed](#install), you can use the following code to access the
Docker API of your local docker daemon:

```php
$loop = React\EventLoop\Factory::create();
$client = new Clue\React\Docker\Client($loop);

$client->imageSearch('clue')->then(function (array $images) {
    var_dump($images);
});

$loop->run();
```

See also the [examples](examples).

## Usage

### Client

The `Client` is responsible for assembling and sending HTTP requests to the Docker Engine API.
It uses an HTTP client bound to the main [`EventLoop`](https://github.com/reactphp/event-loop#usage)
in order to handle async requests:

```php
$loop = React\EventLoop\Factory::create();
$client = new Clue\React\Docker\Client($loop);
```

If your Docker Engine API is not accessible using the default `unix:///var/run/docker.sock`
Unix domain socket path, you may optionally pass an explicit URL like this:

```
// explicitly use given UNIX socket path
$client = new Clue\React\Docker\Client($loop, 'unix:///var/run/docker.sock');

// or connect via TCP/IP to a remote Docker Engine API
$client = new Clue\React\Docker\Client($loop, 'http://10.0.0.2:8000/');
```

#### Commands

All public methods on the `Client` resemble the API described in the [Docker Engine API documentation](https://docs.docker.com/develop/sdk/) like this:

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
[Docker Engine API documentation](https://docs.docker.com/develop/sdk/)
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
you should look into also using [clue/reactphp-block](https://github.com/clue/reactphp-block).

The resulting blocking code could look something like this:

```php
use Clue\React\Block;

$loop = React\EventLoop\Factory::create();
$client = new Clue\React\Docker\Client($loop);

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

Please refer to [clue/reactphp-block](https://github.com/clue/reactphp-block#readme) for more details.

#### Command streaming

The following API endpoints resolve with a buffered string of the command output
(STDOUT and/or STDERR):

```php
$client->containerAttach($container);
$client->containerLogs($container);
$client->execStart($exec);
```

Keep in mind that this means the whole string has to be kept in memory.
If you want to access the individual output chunks as they happen or
for bigger command outputs, it's usually a better idea to use a streaming
approach.

This works for (any number of) commands of arbitrary sizes.
The following API endpoints complement the default Promise-based API and return
a [`Stream`](https://github.com/reactphp/stream) instance instead:

```php
$stream = $client->containerAttachStream($container);
$stream = $client->containerLogsStream($container);
$stream = $client->execStartStream($exec);
```

The resulting stream is a well-behaving readable stream that will emit
the normal stream events:

```php
$stream = $client->execStartStream($exec, $tty);
$stream->on('data', function ($data) {
    // data will be emitted in multiple chunk
    echo $data;
});
$stream->on('close', function () {
    // the stream just ended, this could(?) be a good thing
    echo 'Ended' . PHP_EOL;
});
```

Note that by default the output of both STDOUT and STDERR will be emitted
as normal `data` events. You can optionally pass a custom event name which
will be used to emit STDERR data so that it can be handled separately.
Note that the normal streaming primitives likely do not know about this
event, so special care may have to be taken.
Also note that this option has no effect if you execute with a TTY.

```php
$stream = $client->execStartStream($exec, $tty, 'stderr');
$stream->on('data', function ($data) {
    echo 'STDOUT data: ' . $data;
});
$stream->on('stderr', function ($data) {
    echo 'STDERR data: ' . $data;
});
```

See also the [streaming exec example](examples/exec-stream.php) and the [exec benchmark example](examples/benchmark-exec.php).

The TTY mode should be set depending on whether your command needs a TTY
or not. Note that toggling the TTY mode affects how/whether you can access
the STDERR stream and also has a significant impact on performance for
larger streams (relevant for hundreds of megabytes and more). See also the TTY
mode on the `execStart*()` call.

Running the provided benchmark example on a range of systems, it suggests that
this library can process several gigabytes per second and may in fact outperform
the Docker client and seems to be limited only by the Docker Engine implementation.
Instead of posting more details here, you're encouraged to re-run the benchmarks
yourself and see for yourself.
The key takeway here is: *PHP is faster than you probably thought*.

#### TAR streaming

The following API endpoints resolve with a string in the [TAR file format](https://en.wikipedia.org/wiki/Tar_%28computing%29):

```php
$client->containerExport($container);
$client->containerArchive($container, $path);
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
$stream = $client->containerArchiveStream($container, $path);
```

Accessing individual files in the TAR file format string or stream is out of scope
for this library.
Several libraries are available, one that is known to work is [clue/reactphp-tar](https://github.com/clue/reactphp-tar).

See also the [archive example](examples/archive.php) and the [export example](examples/export.php).

#### JSON streaming

The following API endpoints take advantage of [JSON streaming](https://en.wikipedia.org/wiki/JSON_Streaming):

```php
$client->imageCreate();
$client->imagePush();
$client->events();
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
    function (array $data) {
        // $data is an array of *all* elements in the JSON stream
        var_dump($data);
    },
    function (Exception $error) {
        // an error occurred (possibly after receiving *some* elements)
        echo 'Error: ' . $error->getMessage() . PHP_EOL;
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
$stream = $client->eventsStream();
$stream = $client->containerStatsStream($container);
```

The resulting stream will emit the following events:

* `data`:  for *each* element in the update stream
* `error`: once if an error occurs, will close() stream then
  * Will emit a `RuntimeException` if an individual progress message contains an error message
    or any other `Exception` in case of an transport error, like invalid request etc.
* `close`: once the stream ends (either finished or after "error")

```php
$stream = $client->imageCreateStream('clue/redis-benchmark');
$stream->on('data', function (array $data) {
    // data will be emitted for *each* complete element in the JSON stream
    echo $data['status'] . PHP_EOL;
});
$stream->on('close', function () {
    // the JSON stream just ended, this could(?) be a good thing
    echo 'Ended' . PHP_EOL;
});
```

See also the [pull example](examples/pull.php) and the [push example](examples/push.php).

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
$ composer require clue/docker-react:^1.2
```

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 8+.
It's *highly recommended to use PHP 7+* for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.
