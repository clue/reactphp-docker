# Changelog

## 1.4.0 (2022-12-01)

*   Feature: Add support for PHP 8.1 and PHP 8.2.
    (#78 by @dinooo13)

*   Feature: Forward compatibility with upcoming Promise v3.
    (#76 by @clue)

*   Feature: Simplify usage by supporting new default loop.
    (#71 by @clue)

    ```php
    // old (still supported)
    $client = new Clue\React\Docker\Client($loop);

    // new (using default loop)
    $client = new Clue\React\Docker\Client();
    ```

*   Feature: Add commit API endpoint.
    (#74 by @dinooo13)

    ```php
    $client->containerCommit($container)->then(function (array $image) {
        var_dump($image);
    }, function (Exception $e) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    });
    ```

*   Improve documentation and examples, update to use new reactphp/async package and new HTTP and Socket API.
    (#70 by @PaulRotmann, #72 by @SimonFrings, #73 by @clue and #77 by @dinooo13)

*   Improve test suite and ensure 100% code coverage.
    (#80, #81 by @clue and #79 by @dinooo13)

## 1.3.0 (2020-12-17)

*   Feature: Update to reactphp/http v1.0.0.
    (#64 by @clue)

*   Improve test suite and add `.gitattributes` to exclude dev files from exports.
    Add PHP 8 support, update to PHPUnit 9 and simplify test setup.
    (#62, #65, #66 and #67 by @SimonFrings)

## 1.2.0 (2020-03-31)

*   Feature: Add `containerAttach()` and `containerAttachStream()` API methods.
    (#61 by @clue)

*   Improve test suite and fix failing tests with new Docker Engine API.
    (#60 by @clue)

## 1.1.0 (2020-02-11)

*   Feature: Add network API methods.
    (#57 by @tjoussen)

*   Improve test suite by testing against PHP 7.4 and simplify test matrix
    and add support / sponsorship info.
    (#58 and #59 by @clue)

## 1.0.0 (2019-09-19)

*   First stable release, now following SemVer!
    See [**release announcement**](https://clue.engineering/2019/introducing-reactphp-docker).

*   Feature: Update all ReactPHP dependencies to latest versions and
    significantly improve performance (see included benchmark examples).
    (#51 and #56 by @clue)

*   Feature / BC break: Replace `Factory` with simplified `Client` constructor.
    (#49 by @clue)

    ```php
    // old
    $factory = new Clue\React\Docker\Factory($loop);
    $client = $factory->createClient($url);

    // new
    $client = new Clue\React\Docker\Client($loop, $url);
    ```

*   Feature / BC break: Change JSON stream to always report `data` events instead of `progress`,
    follow strict stream semantics, support backpressure and improve error handling.
    (#27 and #50 by @clue)

    ```php
    // old: all JSON streams use custom "progress" event
    $stream = $client->eventsStream();
    $stream->on('progress', function ($data) {
        var_dump($data);
    });

    // new: all streams use default "data" event
    $stream = $client->eventsStream();
    $stream->on('data', function ($data) {
        var_dump($data);
    });

    // new: stream follows stream semantics and supports stream composition
    $stream = $client->eventsStream();
    $stream->pipe($logger);
    ```

*   Feature / BC break: Add `containerArchive()` and `containerArchiveStream()` methods and
    remove deprecated `containerCopy()` and `containerCopyStream()` and
    remove deprecated HostConfig parameter from `containerStart()`.
    (#42, #48 and #55 by @clue)

    ```php
    // old
    $client->containerCopy($container, array('Resource' => $path));

    // new
    $client->containerArchive($container, $path);
    ```

*   Feature / BC break: Change `execCreate()` method to accept plain params instead of config object.
    (#38 and #39 by @clue)

*   Feature / BC break: Change `execStart()` method to resolve with buffered string contents.
    (#35 and #40)

*   Feature: Add `execStartDetached()` method to resolve without waiting for exec data.
    (#38 by @clue)

*   Feature: Add `execStartStream()` method to return stream of exec data.
    (#37 and #40)

*   Feature: Add `execInspect()` method.
    (#34 by @clue)

*   Feature: Add `containerLogs()` and `containerLogsStream()` methods.
    (#53 and #54 by @clue)

*   Feature: Add `containerStats()` and `containerStatsStream()` methods.
    (#52 by @clue)

*   Feature: Add `events()` and `eventsStream()` methods
    (#32 by @clue)

*   Feature: Add `containerRename()` method.
    (#43 by @clue)

*   Feature: Timeout `$t` is optional for `containerStop()` and `containerRestart()`.
    (#28 by @clue)

*   Fix: The `containerResize()` and  `execResize()` to issue `POST` request to resize TTY.
    (#29 and #30 by @clue)

*   Improve test suite by adding PHPUnit to `require-dev`, support PHPUnit 7 - legacy PHPUnit 4
    and test against legacy PHP 5.3 through PHP 7.3,
    improve documentation and update project homepage.
    (#31, #46 and #47 by @clue)

## 0.2.0 (2015-08-11)

* Feature: Add streaming API for existing endpoints (TAR and JSON streaming).
  ([#9](https://github.com/clue/php-docker-react/pull/9))
  * JSON streaming endpoints now resolve with an array of progress messages
  * Reject Promise if progress messages indicate an error

* Feature: Omit empty URI parameters and refactor to use URI templates internally
  ([#23](https://github.com/clue/php-docker-react/pull/23))

* Improved documentation, more SOLID code base and updated dependencies.

## 0.1.0 (2014-12-08)

* First tagged release

## 0.0.0 (2014-11-26)

* Initial concept
