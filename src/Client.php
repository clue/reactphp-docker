<?php

namespace Clue\React\Docker;

use Clue\React\Docker\Io\ResponseParser;
use Clue\React\Docker\Io\StreamingParser;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use React\Socket\FixedUriConnector;
use React\Socket\UnixConnector;
use React\Stream\ReadableStreamInterface;

/**
 * The Docker Engine API client can be used to control your (local) Docker daemon.
 *
 * This Client implementation provides a very thin wrapper around this
 * Docker Engine API and exposes its exact data models.
 * The Client uses HTTP requests via a local UNIX socket path or remotely via a
 * TLS-backed TCP/IP connection.
 *
 * This client has been tested against a variety of Docker Engine API versions,
 * with support for Docker Engine API v1.40 (and newer) and versions as old as
 * Docker Engine API v1.14. See also https://docs.docker.com/engine/api/version-history/.
 *
 * @link https://docs.docker.com/develop/sdk/
 */
class Client
{
    private $browser;
    private $parser;
    private $streamingParser;

    /**
     *
     * This class takes an optional `LoopInterface|null $loop` parameter that can be used to
     * pass the event loop instance to use for this object. You can use a `null` value
     * here in order to use the [default loop](https://github.com/reactphp/event-loop#loop).
     * This value SHOULD NOT be given unless you're sure you want to explicitly use a
     * given event loop instance.
     *
     * If your Docker Engine API is not accessible using the default `unix:///var/run/docker.sock`
     * Unix domain socket path, you may optionally pass an explicit URL like this:
     *
     * ```php
     * // explicitly use given UNIX socket path
     * $client = new Clue\React\Docker\Client(null, 'unix:///var/run/docker.sock');
     *
     * // or connect via TCP/IP to a remote Docker Engine API
     * $client = new Clue\React\Docker\Client(null, 'http://10.0.0.2:8000/');
     * ```
     *
     * @param ?LoopInterface $loop
     * @param ?string        $url
     * @throws \InvalidArgumentException
     */
    public function __construct($loop = null, $url = null)
    {
        if ($loop !== null && !$loop instanceof LoopInterface) { // manual type check to support legacy PHP < 7.1
            throw new \InvalidArgumentException('Argument #1 ($loop) expected null|React\EventLoop\LoopInterface');
        }

        if ($url === null) {
            $url = 'unix:///var/run/docker.sock';
        }

        $connector = null;
        if (substr($url, 0, 7) === 'unix://') {
            // send everything through a local unix domain socket
            $connector = new FixedUriConnector(
                $url,
                new UnixConnector($loop)
            );

            // pretend all HTTP URLs to be on localhost
            $url = 'http://localhost/';
        }

        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host']) || ($parts['scheme'] !== 'http' && $parts['scheme'] !== 'https')) {
            throw new \InvalidArgumentException('Invalid Docker Engine API URL given');
        }

        $browser = new Browser($connector, $loop);
        $this->browser = $browser->withBase($url);
        $this->parser = new ResponseParser();
        $this->streamingParser = new StreamingParser();
    }

    /**
     * Ping the docker server
     *
     * @return PromiseInterface Promise<string> "OK"
     * @link https://docs.docker.com/engine/api/v1.40/#operation/SystemPing
     */
    public function ping()
    {
        // GET /_ping
        return $this->browser->get('_ping')->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Display system-wide information
     *
     * @return PromiseInterface Promise<array> system info (see link)
     * @link https://docs.docker.com/engine/api/v1.40/#operation/SystemInfo
     */
    public function info()
    {
        // GET /info
        return $this->browser->get('info')->then(array($this->parser, 'expectJson'));
    }

    /**
     * Show the docker version information
     *
     * @return PromiseInterface Promise<array> version info (see link)
     * @link https://docs.docker.com/engine/api/v1.40/#operation/SystemVersion
     */
    public function version()
    {
        // GET /version
        return $this->browser->get('version')->then(array($this->parser, 'expectJson'));
    }

    /**
     * Get container events from docker
     *
     * This is a JSON streaming API endpoint that resolves with an array of all
     * individual progress events.
     *
     * If you need to access the individual progress events as they happen, you
     * should consider using `eventsStream()` instead.
     *
     * Note that this method will buffer all events until the stream closes.
     * This means that you SHOULD pass a timestamp for `$until` so that this
     * method only polls the given time interval and then resolves.
     *
     * The optional `$filters` parameter can be used to only get events for
     * certain event types, images and/or containers etc. like this:
     * <code>
     * $filters = array(
     *     'image' => array('ubuntu', 'busybox'),
     *     'event' => array('create')
     * );
     * </code>
     *
     * @param float|null $since timestamp used for polling
     * @param float|null $until timestamp used for polling
     * @param array    $filters (optional) filters to apply (requires Docker Engine API v1.16+ / Docker v1.4+)
     * @return PromiseInterface Promise<array> array of event objects
     * @link https://docs.docker.com/engine/api/v1.40/#operation/SystemEvents
     * @uses self::eventsStream()
     * @see self::eventsStream()
     */
    public function events($since = null, $until = null, $filters = array())
    {
        return $this->streamingParser->deferredStream(
            $this->eventsStream($since, $until, $filters)
        );
    }

    /**
     * Get container events from docker
     *
     * This is a JSON streaming API endpoint that returns a stream instance.
     *
     * The resulting stream will emit the following events:
     * - data:  for *each* element in the update stream
     * - error: once if an error occurs, will close() stream then
     * - close: once the stream ends (either finished or after "error")
     *
     * The optional `$filters` parameter can be used to only get events for
     * certain event types, images and/or containers etc. like this:
     * <code>
     * $filters = array(
     *     'image' => array('ubuntu', 'busybox'),
     *     'event' => array('create')
     * );
     * </code>
     *
     * @param float|null $since   timestamp used for polling
     * @param float|null $until   timestamp used for polling
     * @param array      $filters (optional) filters to apply (requires Docker Engine API v1.16+ / Docker v1.4+)
     * @return ReadableStreamInterface stream of event objects
     * @link https://docs.docker.com/engine/api/v1.40/#operation/SystemEvents
     * @see self::events()
     */
    public function eventsStream($since = null, $until = null, $filters = array())
    {
        // GET /events{?since,until,filters}
        return $this->streamingParser->parseJsonStream(
            $this->browser->requestStreaming(
                'GET',
                'events' . $this->queryString(array(
                    'since' => $since,
                    'until' => $until,
                    'filters' => $filters ? json_encode($filters) : null
                ))
            )
        );
    }

    /**
     * List containers
     *
     * @param boolean $all
     * @param boolean $size
     * @return PromiseInterface Promise<array> list of container objects
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerList
     */
    public function containerList($all = false, $size = false)
    {
        // GET /containers/json{?all,size}
        return $this->browser->get(
            'containers/json' . $this->queryString(array(
                'all' => $all ? 1 : null,
                'size' => $size ? 1 : null
            ))
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Create a container
     *
     * @param array       $config e.g. `array('Image' => 'busybox', 'Cmd' => 'date')` (see link)
     * @param string|null $name   (optional) name to assign to this container
     * @return PromiseInterface Promise<array> container properties `array('Id' => $containerId', 'Warnings' => array())`
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerCreate
     */
    public function containerCreate($config, $name = null)
    {
        // POST /containers/create{?name}
        return $this->postJson(
            'containers/create' . $this->queryString(array(
                'name' => $name
            )),
            $config
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Return low-level information on the container id
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<array> container properties
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerInspect
     */
    public function containerInspect($container)
    {
        // GET /containers/{container}/json
        return $this->browser->get(
            'containers/' . rawurlencode($container) . '/json'
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * List processes running inside the container id
     *
     * @param string      $container container ID
     * @param string|null $ps_args   (optional) ps arguments to use (e.g. aux)
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerTop
     */
    public function containerTop($container, $ps_args = null)
    {
        // GET /containers/{container}/top{?ps_args}
        return $this->browser->get(
            'containers/' . rawurlencode($container) . '/top' . $this->queryString(array(
                'ps_args' => $ps_args
            ))
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Get stdout and stderr logs from the container id
     *
     * This resolves with a string containing the log output, i.e. STDOUT
     * and STDERR as requested.
     *
     * Keep in mind that this means the whole string has to be kept in memory.
     * For bigger container logs it's usually a better idea to use a streaming
     * approach, see containerLogsStream() for more details.
     * In particular, the same also applies for the $follow flag. It can be used
     * to follow the container log messages as long as the container is running.
     *
     * Note that this endpoint works only for containers with the "json-file" or
     * "journald" logging drivers.
     *
     * Note that this endpoint internally has to check the `containerInspect()`
     * endpoint first in order to figure out the TTY settings to properly decode
     * the raw log output.
     *
     * @param string   $container  container ID
     * @param boolean  $follow     1/True/true or 0/False/false, return stream. Default false
     * @param boolean  $stdout     1/True/true or 0/False/false, show stdout log. Default true
     * @param boolean  $stderr     1/True/true or 0/False/false, show stderr log. Default true
     * @param int      $since      UNIX timestamp (integer) to filter logs. Specifying a timestamp will only output log-entries since that timestamp. Default: 0 (unfiltered) (requires API v1.19+ / Docker v1.7+)
     * @param boolean  $timestamps 1/True/true or 0/False/false, print timestamps for every log line. Default false
     * @param int|null $tail       Output specified number of lines at the end of logs: all or <number>. Default all
     * @return PromiseInterface Promise<string> log output string
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerLogs
     * @uses self::containerLogsStream()
     * @see self::containerLogsStream()
     */
    public function containerLogs($container, $follow = false, $stdout = true, $stderr = true, $since = 0, $timestamps = false, $tail = null)
    {
        return $this->streamingParser->bufferedStream(
            $this->containerLogsStream($container, $follow, $stdout, $stderr, $since, $timestamps, $tail)
        );
    }

    /**
     * Get stdout and stderr logs from the container id
     *
     * This is a streaming API endpoint that returns a readable stream instance
     * containing the the log output, i.e. STDOUT and STDERR as requested.
     *
     * This works for container logs of arbitrary sizes as only small chunks have to
     * be kept in memory.
     *
     * This is particularly useful for the $follow flag. It can be used
     * to follow the container log messages as long as the container is running.
     *
     * Note that by default the output of both STDOUT and STDERR will be emitted
     * as normal "data" events. You can optionally pass a custom event name which
     * will be used to emit STDERR data so that it can be handled separately.
     * Note that the normal streaming primitives likely do not know about this
     * event, so special care may have to be taken.
     * Also note that this option has no effect if the container has been
     * created with a TTY.
     *
     * Note that this endpoint works only for containers with the "json-file" or
     * "journald" logging drivers.
     *
     * Note that this endpoint internally has to check the `containerInspect()`
     * endpoint first in order to figure out the TTY settings to properly decode
     * the raw log output.
     *
     * @param string   $container   container ID
     * @param boolean  $follow      1/True/true or 0/False/false, return stream. Default false
     * @param boolean  $stdout      1/True/true or 0/False/false, show stdout log. Default true
     * @param boolean  $stderr      1/True/true or 0/False/false, show stderr log. Default true
     * @param int      $since       UNIX timestamp (integer) to filter logs. Specifying a timestamp will only output log-entries since that timestamp. Default: 0 (unfiltered) (requires API v1.19+ / Docker v1.7+)
     * @param boolean  $timestamps  1/True/true or 0/False/false, print timestamps for every log line. Default false
     * @param int|null $tail        Output specified number of lines at the end of logs: all or <number>. Default all
     * @param string   $stderrEvent custom event to emit for STDERR data (otherwise emits as "data")
     * @return ReadableStreamInterface log output stream
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerLogs
     * @see self::containerLogs()
     */
    public function containerLogsStream($container, $follow = false, $stdout = true, $stderr = true, $since = 0, $timestamps = false, $tail = null, $stderrEvent = null)
    {
        $parser = $this->streamingParser;
        $browser = $this->browser;

        // GET /containers/{container}/logs{?follow,stdout,stderr,since,timestamps,tail}
        $url = 'containers/' . rawurlencode($container) . '/logs' . $this->queryString(array(
            'follow' => $follow ? 1 : null,
            'stdout' => $stdout ? 1 : null,
            'stderr' => $stderr ? 1 : null,
            'since' => ($since === 0) ? null : $since,
            'timestamps' => $timestamps ? 1 : null,
            'tail' => $tail
        ));

        // first inspect container to check TTY setting, then request logs with appropriate log parser
        return \React\Promise\Stream\unwrapReadable($this->containerInspect($container)->then(function ($info) use ($url, $browser, $parser, $stderrEvent) {
            $stream = $parser->parsePlainStream($browser->requestStreaming('GET', $url));

            if (!$info['Config']['Tty']) {
                $stream = $parser->demultiplexStream($stream, $stderrEvent);
            }

            return $stream;
        }));
    }

    /**
     * Inspect changes on container id's filesystem
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerChanges
     */
    public function containerChanges($container)
    {
        // GET /containers/{container}/changes
        return $this->browser->get(
            'containers/' . rawurlencode($container) . '/changes'
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Export the contents of container id
     *
     * This resolves with a string in the TAR file format containing all files
     * in the container.
     *
     * Keep in mind that this means the whole string has to be kept in memory.
     * For bigger containers it's usually a better idea to use a streaming
     * approach, see containerExportStream() for more details.
     *
     * Accessing individual files in the TAR file format string is out of scope
     * for this library. Several libraries are available, one that is known to
     * work is clue/reactphp-tar (see links).
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<string> tar stream
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerExport
     * @link https://github.com/clue/reactphp-tar
     * @see self::containerExportStream()
     */
    public function containerExport($container)
    {
        // GET /containers/{container}/export
        return $this->browser->get(
            'containers/' . rawurlencode($container) . '/export'
        )->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Export the contents of container id
     *
     * This returns a stream in the TAR file format containing all files
     * in the container.
     *
     * This works for containers of arbitrary sizes as only small chunks have to
     * be kept in memory.
     *
     * Accessing individual files in the TAR file format stream is out of scope
     * for this library. Several libraries are available, one that is known to
     * work is clue/reactphp-tar (see links).
     *
     * The resulting stream is a well-behaving readable stream that will emit
     * the normal stream events.
     *
     * @param string $container container ID
     * @return ReadableStreamInterface tar stream
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerExport
     * @link https://github.com/clue/reactphp-tar
     * @see self::containerExport()
     */
    public function containerExportStream($container)
    {
        // GET /containers/{container}/export
        return $this->streamingParser->parsePlainStream(
            $this->browser->requestStreaming(
                'GET',
                'containers/' . rawurlencode($container) . '/export'
            )
        );
    }

    /**
     * Returns a container’s resource usage statistics.
     *
     * This is a JSON API endpoint that resolves with a single stats info.
     *
     * If you want to monitor live stats events as they happen, you
     * should consider using `imageStatsStream()` instead.
     *
     * Available as of Docker Engine API v1.19 (Docker v1.7), use `containerStatsStream()` on legacy versions
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<array> JSON stats
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerStats
     * @see self::containerStatsStream()
     */
    public function containerStats($container)
    {
        // GET /containers/{container}/stats{?stream}
        return $this->browser->get(
            'containers/' . rawurlencode($container) . '/stats' . $this->queryString(array(
                'stream' => 0
            ))
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Returns a live stream of a container’s resource usage statistics.
     *
     * The resulting stream will emit the following events:
     * - data:  for *each* element in the stats stream
     * - error: once if an error occurs, will close() stream then
     * - close: once the stream ends (either finished or after "error")
     *
     * Available as of Docker Engine API v1.17 (Docker v1.5)
     *
     * @param string $container container ID
     * @return ReadableStreamInterface JSON stats stream
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerStats
     * @see self::containerStats()
     */
    public function containerStatsStream($container)
    {
        // GET /containers/{container}/stats
        return $this->streamingParser->parseJsonStream(
            $this->browser->requestStreaming(
                'GET',
                'containers/' . rawurlencode($container) . '/stats'
            )
        );
    }

    /**
     * Resize the TTY of container id
     *
     * @param string $container container ID
     * @param int    $w         TTY width
     * @param int    $h         TTY height
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerResize
     */
    public function containerResize($container, $w, $h)
    {
        // POST /containers/{container}/resize{?w,h}
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/resize' . $this->queryString(array(
                'w' => $w,
                'h' => $h
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Start the container id
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerStart
     */
    public function containerStart($container)
    {
        // POST /containers/{container}/start
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/start'
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Stop the container id
     *
     * @param string   $container container ID
     * @param null|int $t         (optional) number of seconds to wait before killing the container
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerStop
     */
    public function containerStop($container, $t = null)
    {
        // POST /containers/{container}/stop{?t}
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/stop' . $this->queryString(array(
                't' => $t
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Restart the container id
     *
     * @param string   $container container ID
     * @param null|int $t         (optional) number of seconds to wait before killing the container
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerRestart
     */
    public function containerRestart($container, $t = null)
    {
        // POST /containers/{container}/restart{?t}
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/restart' . $this->queryString(array(
                't' => $t
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Kill the container id
     *
     * @param string          $container container ID
     * @param string|int|null $signal    (optional) signal name or number
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerKill
     */
    public function containerKill($container, $signal = null)
    {
        // POST /containers/{container}/kill{?signal}
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/kill' . $this->queryString(array(
                'signal' => $signal
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Rename the container id
     *
     * Requires Docker Engine API v1.17+ / Docker v1.5+
     *
     * @param string $container container ID
     * @param string $name      new name for the container
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerRename
     */
    public function containerRename($container, $name)
    {
        // POST /containers/{container}/rename{?name}
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/rename' . $this->queryString(array(
                'name' => $name
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Pause the container id
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerPause
     */
    public function containerPause($container)
    {
        // POST /containers/{container}/pause
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/pause'
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Unpause the container id
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerUnpause
     */
    public function containerUnpause($container)
    {
        // POST /containers/{container}/unpause
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/unpause'
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Attach to a container to read its output.
     *
     * This resolves with a string containing the container output, i.e. STDOUT
     * and STDERR as requested.
     *
     * Keep in mind that this means the whole string has to be kept in memory.
     * For a larger container output it's usually a better idea to use a streaming
     * approach, see `containerAttachStream()` for more details.
     * In particular, the same also applies for the `$stream` flag. It can be used
     * to follow the container output as long as the container is running.
     *
     * Note that this endpoint internally has to check the `containerInspect()`
     * endpoint first in order to figure out the TTY settings to properly decode
     * the raw container output.
     *
     * @param string $container container ID
     * @param bool   $logs      replay previous logs before attaching. Default false
     * @param bool   $stream    continue streaming. Default false
     * @param bool   $stdout    attach to stdout. Default true
     * @param bool   $stderr    attach to stderr. Default true
     * @return PromiseInterface Promise<string> container output string
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerAttach
     * @uses self::containerAttachStream()
     * @see self::containerAttachStream()
     */
    public function containerAttach($container, $logs = false, $stream = false, $stdout = true, $stderr = true)
    {
        return $this->streamingParser->bufferedStream(
            $this->containerAttachStream($container, $logs, $stream, $stdout, $stderr)
        );
    }

    /**
     * Attach to a container to read its output.
     *
     * This is a streaming API endpoint that returns a readable stream instance
     * containing the container output, i.e. STDOUT and STDERR as requested.
     *
     * This works for container output of arbitrary sizes as only small chunks have to
     * be kept in memory.
     *
     * This is particularly useful for the `$stream` flag. It can be used to
     * follow the container output as long as the container is running. Either
     * the `$stream` or `$logs` parameter must be `true` for this endpoint to do
     * anything meaningful.
     *
     * Note that by default the output of both STDOUT and STDERR will be emitted
     * as normal "data" events. You can optionally pass a custom event name which
     * will be used to emit STDERR data so that it can be handled separately.
     * Note that the normal streaming primitives likely do not know about this
     * event, so special care may have to be taken.
     * Also note that this option has no effect if the container has been
     * created with a TTY.
     *
     * Note that this endpoint internally has to check the `containerInspect()`
     * endpoint first in order to figure out the TTY settings to properly decode
     * the raw container output.
     *
     * Note that this endpoint intentionally does not expose the `$stdin` flag.
     * Access to STDIN will be exposed as a dedicated API endpoint in a future
     * version.
     *
     * @param string $container   container ID
     * @param bool   $logs        replay previous logs before attaching. Default false
     * @param bool   $stream      continue streaming. Default false
     * @param bool   $stdout      attach to stdout. Default true
     * @param bool   $stderr      attach to stderr. Default true
     * @param string $stderrEvent custom event to emit for STDERR data (otherwise emits as "data")
     * @return ReadableStreamInterface container output stream
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerAttach
     * @see self::containerAttach()
     */
    public function containerAttachStream($container, $logs = false, $stream = false, $stdout = true, $stderr = true, $stderrEvent = null)
    {
        $parser = $this->streamingParser;
        $browser = $this->browser;

        // POST /containers/{container}/attach{?logs,stream,stdout,stderr}
        $url = 'containers/' . rawurlencode($container) . '/attach' . $this->queryString(array(
            'logs' => $logs ? 1 : null,
            'stream' => $stream ? 1 : null,
            'stdout' => $stdout ? 1 : null,
            'stderr' => $stderr ? 1 : null
        ));

        // first inspect container to check TTY setting, then attach with appropriate log parser
        return \React\Promise\Stream\unwrapReadable($this->containerInspect($container)->then(function ($info) use ($url, $browser, $parser, $stderrEvent) {
            $stream = $parser->parsePlainStream($browser->requestStreaming('POST', $url));

            if (!$info['Config']['Tty']) {
                $stream = $parser->demultiplexStream($stream, $stderrEvent);
            }

            return $stream;
        }));
    }

    /**
     * Block until container id stops, then returns the exit code
     *
     * @param string $container container ID
     * @param string|null $condition (optional) Wait until a container state reaches the given condition
     * @return PromiseInterface Promise<array> `array('StatusCode' => 0)` (see link)
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerWait
     */
    public function containerWait($container, $condition = null)
    {
        // POST /containers/{container}/wait{?condition}
        return $this->browser->post(
            'containers/' . rawurlencode($container) . '/wait' . $this->queryString(array(
                'condition' => $condition
            ))
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Remove the container id from the filesystem
     *
     * @param string  $container container ID
     * @param boolean $v         Remove the volumes associated to the container. Default false
     * @param boolean $force     Kill then remove the container. Default false
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerRemove
     */
    public function containerRemove($container, $v = false, $force = false)
    {
        // DELETE /containers/{container}{?v,force}
        return $this->browser->delete(
            'containers/' . rawurlencode($container) . $this->queryString(array(
                'v' => $v ? 1 : null,
                'force' => $force ? 1 : null
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Get a tar archive of a resource in the filesystem of container id.
     *
     * This resolves with a string in the TAR file format containing all files
     * specified in the given $path.
     *
     * Keep in mind that this means the whole string has to be kept in memory.
     * For bigger containers it's usually a better idea to use a streaming approach,
     * see containerArchiveStream() for more details.
     *
     * Accessing individual files in the TAR file format string is out of scope
     * for this library. Several libraries are available, one that is known to
     * work is clue/reactphp-tar (see links).
     *
     * Available as of Docker Engine API v1.20 (Docker v1.8)
     *
     * @param string $container container ID
     * @param string $resource  path to file or directory to archive
     * @return PromiseInterface Promise<string> tar stream
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerArchive
     * @link https://github.com/clue/reactphp-tar
     * @see self::containerArchiveStream()
     */
    public function containerArchive($container, $path)
    {
        // GET /containers/{container}/archive{?path}
        return $this->browser->get(
            'containers/' . rawurlencode($container) . '/archive' . $this->queryString(array(
                'path' => $path
            ))
        )->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Get a tar archive of a resource in the filesystem of container id.
     *
     * This returns a stream in the TAR file format containing all files
     * specified in the given $path.
     *
     * This works for (any number of) files of arbitrary sizes as only small chunks have to
     * be kept in memory.
     *
     * Accessing individual files in the TAR file format stream is out of scope
     * for this library. Several libraries are available, one that is known to
     * work is clue/reactphp-tar (see links).
     *
     * The resulting stream is a well-behaving readable stream that will emit
     * the normal stream events.
     *
     * Available as of Docker Engine API v1.20 (Docker v1.8)
     *
     * @param string $container container ID
     * @param string $path      path to file or directory to archive
     * @return ReadableStreamInterface tar stream
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerArchive
     * @link https://github.com/clue/reactphp-tar
     * @see self::containerArchive()
     */
    public function containerArchiveStream($container, $path)
    {
        // GET /containers/{container}/archive{?path}
        return $this->streamingParser->parsePlainStream(
            $this->browser->requestStreaming(
                'GET',
                'containers/' . rawurlencode($container) . '/archive' . $this->queryString(array(
                    'path' => $path
                ))
            )
        );
    }

    /**
     * List images
     *
     * @param boolean $all
     * @return PromiseInterface Promise<array> list of image objects
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImageList
     * @todo support $filters param
     */
    public function imageList($all = false)
    {
        // GET /images/json{?all}
        return $this->browser->get(
            'images/json' . $this->queryString(array(
                'all' => $all ? 1 : null
            ))
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Create an image, either by pulling it from the registry or by importing it
     *
     * This is a JSON streaming API endpoint that resolves with an array of all
     * individual progress events.
     *
     * If you want to access the individual progress events as they happen, you
     * should consider using `imageCreateStream()` instead.
     *
     * Pulling a private image from a remote registry will likely require authorization, so make
     * sure to pass the $registryAuth parameter, see `self::authHeaders()` for
     * more details.
     *
     * @param string|null $fromImage    name of the image to pull
     * @param string|null $fromSrc      source to import, - means stdin
     * @param string|null $repo         repository
     * @param string|null $tag          (optional) (obsolete) tag, use $repo and $fromImage in the "name:tag" instead
     * @param string|null $registry     the registry to pull from
     * @param array|null  $registryAuth AuthConfig object (to send as X-Registry-Auth header)
     * @return PromiseInterface Promise<array> stream of message objects
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImageCreate
     * @uses self::imageCreateStream()
     */
    public function imageCreate($fromImage = null, $fromSrc = null, $repo = null, $tag = null, $registry = null, $registryAuth = null)
    {
        return $this->streamingParser->deferredStream(
            $this->imageCreateStream($fromImage, $fromSrc, $repo, $tag, $registry, $registryAuth)
        );
    }

    /**
     * Create an image, either by pulling it from the registry or by importing it
     *
     * This is a JSON streaming API endpoint that returns a stream instance.
     *
     * The resulting stream will emit the following events:
     * - data:  for *each* element in the update stream
     * - error: once if an error occurs, will close() stream then
     * - close: once the stream ends (either finished or after "error").
     *
     * Pulling a private image from a remote registry will likely require authorization, so make
     * sure to pass the $registryAuth parameter, see `self::authHeaders()` for
     * more details.
     *
     * @param string|null $fromImage    name of the image to pull
     * @param string|null $fromSrc      source to import, - means stdin
     * @param string|null $repo         repository
     * @param string|null $tag          (optional) (obsolete) tag, use $repo and $fromImage in the "name:tag" instead
     * @param string|null $registry     the registry to pull from
     * @param array|null  $registryAuth AuthConfig object (to send as X-Registry-Auth header)
     * @return ReadableStreamInterface
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImageCreate
     * @see self::imageCreate()
     * @uses self::authHeaders()
     */
    public function imageCreateStream($fromImage = null, $fromSrc = null, $repo = null, $tag = null, $registry = null, $registryAuth = null)
    {
        // POST /images/create{?fromImage,fromSrc,repo,tag,registry}
        return $this->streamingParser->parseJsonStream(
            $this->browser->requestStreaming(
                'POST',
                'images/create' . $this->queryString(array(
                    'fromImage' => $fromImage,
                    'fromSrc' => $fromSrc,
                    'repo' => $repo,
                    'tag' => $tag,
                    'registry' => $registry
                )),
                $this->authHeaders($registryAuth)
            )
        );
    }

    /**
     * Return low-level information on the image name
     *
     * @param string $image image ID
     * @return PromiseInterface Promise<array> image properties
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImageInspect
     */
    public function imageInspect($image)
    {
        // GET /images/{image}/json
        return $this->browser->get(
            'images/' . rawurlencode($image) . '/json'
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Return the history of the image name
     *
     * @param string $image image ID
     * @return PromiseInterface Promise<array> list of image history objects
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImageHistory
     */
    public function imageHistory($image)
    {
        // GET /images/{image}/history
        return $this->browser->get(
            'images/' . rawurlencode($image) . '/history'
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Push the image name on the registry
     *
     * This is a JSON streaming API endpoint that resolves with an array of all
     * individual progress events.
     *
     * If you need to access the individual progress events as they happen, you
     * should consider using `imagePushStream()` instead.
     *
     * Pushing to a remote registry will likely require authorization, so make
     * sure to pass the $registryAuth parameter, see `self::authHeaders()` for
     * more details.
     *
     * @param string      $image        image ID
     * @param string|null $tag          (optional) the tag to associate with the image on the registry
     * @param string|null $registry     (optional) the registry to push to (e.g. `registry.acme.com:5000`)
     * @param array|null  $registryAuth (optional) AuthConfig object (to send as X-Registry-Auth header)
     * @return PromiseInterface Promise<array> list of image push messages
     * @uses self::imagePushStream()
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImagePush
     */
    public function imagePush($image, $tag = null, $registry = null, $registryAuth = null)
    {
        return $this->streamingParser->deferredStream(
            $this->imagePushStream($image, $tag, $registry, $registryAuth)
        );
    }

    /**
     * Push the image name on the registry
     *
     * This is a JSON streaming API endpoint that returns a stream instance.
     *
     * The resulting stream will emit the following events:
     * - data:  for *each* element in the update stream
     * - error: once if an error occurs, will close() stream then
     * - close: once the stream ends (either finished or after "error")
     *
     * Pushing to a remote registry will likely require authorization, so make
     * sure to pass the $registryAuth parameter, see `self::authHeaders()` for
     * more details.
     *
     * @param string      $image        image ID
     * @param string|null $tag          (optional) the tag to associate with the image on the registry
     * @param string|null $registry     (optional) the registry to push to (e.g. `registry.acme.com:5000`)
     * @param array|null  $registryAuth (optional) AuthConfig object (to send as X-Registry-Auth header)
     * @return ReadableStreamInterface stream of image push messages
     * @uses self::authHeaders()
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImagePush
     */
    public function imagePushStream($image, $tag = null, $registry = null, $registryAuth = null)
    {
        // POST /images{/registry}/{image}/push{?tag}
        return $this->streamingParser->parseJsonStream(
            $this->browser->requestStreaming(
                'POST',
                'images' . ($registry !== null ? '/' . rawurlencode($registry) : '') . '/' . rawurlencode($image) . '/push' . $this->queryString(array(
                    'tag' => $tag
                )),
                $this->authHeaders($registryAuth)
            )
        );
    }

    /**
     * Tag the image name into a repository
     *
     * @param string      $image  image ID
     * @param string      $repo   The repository to tag in
     * @param string|null $tag    The new tag name
     * @param boolean     $force  1/True/true or 0/False/false, default false
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImageTag
     */
    public function imageTag($image, $repo, $tag = null, $force = false)
    {
        // POST /images/{image}/tag{?repo,tag,force}
        return $this->browser->post(
            'images/' . rawurlencode($image) . '/tag' . $this->queryString(array(
                'repo' => $repo,
                'tag' => $tag,
                'force' => $force ? 1 : null
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Remove the image name from the filesystem
     *
     * @param string  $image   image ID
     * @param boolean $force   1/True/true or 0/False/false, default false
     * @param boolean $noprune 1/True/true or 0/False/false, default false
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImageRemove
     */
    public function imageRemove($image, $force = false, $noprune = false)
    {
        // DELETE /images/{image}{?force,noprune}
        return $this->browser->delete(
            'images/' . rawurlencode($image) . $this->queryString(array(
                'force' => $force ? 1 : null,
                'noprune' => $noprune ? 1 : null
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Search for an image on Docker Hub.
     *
     * @param string $term term to search
     * @return PromiseInterface Promise<array> list of image search result objects
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ImageSearch
     */
    public function imageSearch($term)
    {
        // GET /images/search{?term}
        return $this->browser->get(
            'images/search' . $this->queryString(array(
                'term' => $term
            ))
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Create a new image from a container
     *
     * @param ?string $container The ID or name of the container to commit
     * @param ?string $repo      Repository name for the created image
     * @param ?string $tag       Tag name for the created image
     * @param ?string $comment   Commit message
     * @param ?string $author    Author of the image (e.g., John Hannibal Smith <hannibal@a-team.com>)
     * @param bool    $pause      Whether to pause the container before committing, default: true
     * @param ?string $changes   Dockerfile's instructions to apply while committing
     * @param array   $config     The container configuration (body of the request)
     * @return PromiseInterface
     * @link https://docs.docker.com/engine/api/v1.41/#operation/ImageCommit
     */
    public function containerCommit($container, $repo = null, $tag = null, $comment = null, $author = null, $pause = true, $changes = null, array $config = array())
    {
        // POST /commit{?container,repo,tag,comment,author,pause,changes}
        return $this->postJson(
            'commit' . $this->queryString(array(
                'container' => $container,
                'repo' => $repo,
                'tag' => $tag,
                'comment' => $comment,
                'author' => $author,
                'pause' => $pause ? 1 : 0,
                'changes' => $changes
            )),
            $config
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Sets up an exec instance in a running container id
     *
     * The $command should be given as an array of strings (the command plus
     * arguments). Alternatively, you can also pass a single command line string
     * which will then be wrapped in a shell process.
     *
     * The TTY mode should be set depending on whether your command needs a TTY
     * or not. Note that toggling the TTY mode affects how/whether you can access
     * the STDERR stream and also has a significant impact on performance for
     * larger streams (relevant for 100 MiB and above). See also the TTY mode
     * on the `execStart*()` call:
     * - create=false, start=false:
     *     STDOUT/STDERR are multiplexed into separate streams + quite fast.
     *     This is the default mode, also for `docker exec`.
     * - create=true,  start=true:
     *     STDOUT and STDERR are mixed into a single stream + relatively slow.
     *     This is how `docker exec -t` works internally.
     * - create=false, start=true
     *     STDOUT is streamed, STDERR can not be accessed at all + fastest mode.
     *     This looks strange to you? It probably is. See also the benchmarking example.
     * - create=true, start=false
     *     STDOUT/STDERR are multiplexed into separate streams + relatively slow
     *     This looks strange to you? It probably is. Consider using the first option instead.
     *
     * @param string       $container  container ID
     * @param string|array $cmd        Command to run specified as an array of strings or a single command string
     * @param boolean      $tty        TTY mode
     * @param boolean      $stdin      attaches to STDIN of the exec command
     * @param boolean      $stdout     attaches to STDOUT of the exec command
     * @param boolean      $stderr     attaches to STDERR of the exec command
     * @param string|int   $user       user-specific exec, otherwise defaults to main container user (requires Docker Engine API v1.19+ / Docker v1.7+)
     * @param boolean      $privileged privileged exec with all capabilities enabled (requires Docker Engine API v1.19+ / Docker v1.7+)
     * @return PromiseInterface Promise<array> with exec ID in the form of `array("Id" => $execId)`
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ContainerExec
     */
    public function execCreate($container, $cmd, $tty = false, $stdin = false, $stdout = true, $stderr = true, $user = '', $privileged = false)
    {
        if (!is_array($cmd)) {
            $cmd = array('sh', '-c', (string)$cmd);
        }

        // POST /containers/{container}/exec
        return $this->postJson(
            'containers/' . rawurlencode($container) . '/exec',
            array(
                'Cmd' => $cmd,
                'Tty' => !!$tty,
                'AttachStdin' => !!$stdin,
                'AttachStdout' => !!$stdout,
                'AttachStderr' => !!$stderr,
                'User' => (string) $user,
                'Privileged' => !!$privileged,
            )
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Starts a previously set up exec instance id.
     *
     * This resolves with a string of the command output, i.e. STDOUT and STDERR
     * as set up in the `execCreate()` call.
     *
     * Keep in mind that this means the whole string has to be kept in memory.
     * If you want to access the individual output chunks as they happen or
     * for bigger command outputs, it's usually a better idea to use a streaming
     * approach, see `execStartStream()` for more details.
     *
     * @param string  $exec exec ID
     * @param boolean $tty  tty mode
     * @return PromiseInterface Promise<string> buffered exec data
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ExecStart
     * @uses self::execStartStream()
     * @see self::execStartStream()
     * @see self::execStartDetached()
     */
    public function execStart($exec, $tty = false)
    {
        return $this->streamingParser->bufferedStream(
            $this->execStartStream($exec, $tty)
        );
    }

    /**
     * Starts a previously set up exec instance id.
     *
     * This resolves after starting the exec command, but without waiting for
     * the command output (detached mode).
     *
     * @param string  $exec exec ID
     * @param boolean $tty  tty mode
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ExecStart
     * @see self::execStart()
     * @see self::execStartStream()
     */
    public function execStartDetached($exec, $tty = false)
    {
        // POST /exec/{exec}/start
        return $this->browser->post(
            'exec/' . rawurlencode($exec) . '/start',
            array(
                'Content-Type' => 'application/json'
            ),
            $this->json(array(
                'Detach' => true,
                'Tty' => !!$tty
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Starts a previously set up exec instance id.
     *
     * This is a streaming API endpoint that returns a readable stream instance
     * containing the command output, i.e. STDOUT and STDERR as set up in the
     * `execCreate()` call.
     *
     * This works for command output of any size as only small chunks have to
     * be kept in memory.
     *
     * Note that by default the output of both STDOUT and STDERR will be emitted
     * as normal "data" events. You can optionally pass a custom event name which
     * will be used to emit STDERR data so that it can be handled separately.
     * Note that the normal streaming primitives likely do not know about this
     * event, so special care may have to be taken.
     * Also note that this option has no effect if you execute with a TTY.
     *
     * @param string  $exec        exec ID
     * @param boolean $tty         tty mode
     * @param string  $stderrEvent custom event to emit for STDERR data (otherwise emits as "data")
     * @return ReadableStreamInterface stream of exec data
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ExecStart
     * @see self::execStart()
     * @see self::execStartDetached()
     */
    public function execStartStream($exec, $tty = false, $stderrEvent = null)
    {
        // POST /exec/{exec}/start
        $stream = $this->streamingParser->parsePlainStream(
            $this->browser->requestStreaming(
                'POST',
                'exec/' . rawurlencode($exec) . '/start',
                array(
                    'Content-Type' => 'application/json'
                ),
                $this->json(array(
                    'Tty' => !!$tty
                ))
            )
        );

        // this is a multiplexed stream unless this is started with a TTY
        if (!$tty) {
            $stream = $this->streamingParser->demultiplexStream($stream, $stderrEvent);
        }

        return $stream;
    }

    /**
     * Resizes the tty session used by the exec command id.
     *
     * This API is valid only if tty was specified as part of creating and starting the exec command.
     *
     * @param string $exec exec ID
     * @param int    $w    TTY width
     * @param int    $h    TTY height
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ExecResize
     */
    public function execResize($exec, $w, $h)
    {
        // POST /exec/{exec}/resize{?w,h}
        return $this->browser->post(
            'exec/' . rawurlencode($exec) . '/resize' . $this->queryString(array(
                'w' => $w,
                'h' => $h
            ))
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Returns low-level information about the exec command id.
     *
     * Requires Docker Engine API v1.16+ / Docker v1.4+
     *
     * @param string $exec exec ID
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/ExecInspect
     */
    public function execInspect($exec)
    {
        // GET /exec/{exec}/json
        return $this->browser->get(
            'exec/' . rawurlencode($exec) . '/json'
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * List networks.
     *
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/NetworkList
     */
    public function networkList()
    {
        // GET /networks
        return $this->browser->get(
            'networks'
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Inspect network.
     *
     * @param string $network The network id or name
     *
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/NetworkInspect
     */
    public function networkInspect($network)
    {
        // GET /networks/{network}
        return $this->browser->get(
            'networks/' . rawurlencode($network)
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Remove network.
     *
     * @param string $network The network id or name
     *
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/NetworkRemove
     */
    public function networkRemove($network)
    {
        // DELETE /networks/{network}
        return $this->browser->delete(
            'networks/' . rawurlencode($network)
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Create network.
     *
     * @param string $name   The network name
     * @param array  $config (optional) The network configuration
     *
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/NetworkCreate
     */
    public function networkCreate($name, $config = array())
    {
        $config['Name'] = $name;

        // POST /networks/create
        return $this->postJson(
            'networks/create',
            $config
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Connect container to network
     *
     * @param string $network        The network id or name
     * @param string $container      The id or name of the container to connect to network
     * @param array  $endpointConfig (optional) Configuration for a network endpoint
     *
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/NetworkConnect
     */
    public function networkConnect($network, $container, $endpointConfig = array())
    {
        // POST /networks/{network}/connect
        return $this->postJson(
            'networks/' . rawurlencode($network) . '/connect',
            array(
                'Container' => $container,
                'EndpointConfig' => $endpointConfig ? $endpointConfig : null
            )
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Disconnect container from network.
     *
     * @param string $network The id or name of network
     * @param string $container The id or name of container to disconnect
     * @param bool $force (optional) Force the disconnect
     *
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/NetworkDisconnect
     */
    public function networkDisconnect($network, $container, $force = false)
    {
        // POST /networks/{network}/disconnect
        return $this->postJson(
            'networks/' . rawurlencode($network) . '/disconnect',
            array(
                'Container' => $container,
                'Force' => (bool) $force
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Remove all unused networks.
     *
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/api/v1.40/#operation/NetworkPrune
     */
    public function networkPrune()
    {
        // POST /networks/prune
        return $this->postJson(
            'networks/prune',
            array()
        )->then(array($this->parser, 'expectJson'));
    }

    private function postJson($url, $data)
    {
        $body = $this->json($data);
        $headers = array('Content-Type' => 'application/json', 'Content-Length' => strlen($body));

        return $this->browser->post($url, $headers, $body);
    }

    private function json($data)
    {
        if ($data === array()) {
            return '{}';
        }
        return json_encode($data);
    }

    /**
     * Helper function to send an AuthConfig object via the X-Registry-Auth header
     *
     * If your API call returns a "500 Internal Server Error" response with the
     * message "EOF", it probably means that the endpoint requires authorization
     * and you did not supply this header.
     *
     * Description from Docker's docs (see links):
     *
     * AuthConfig, set as the X-Registry-Auth header, is currently a base64url
     * encoded (JSON) string with the following structure:
     * ```
     * {"username": "string", "password": "string", "email": "string", "serveraddress" : "string", "auth": ""}
     * ```
     *
     * Notice that auth is to be left empty, serveraddress is a domain/ip without
     * protocol, and that double quotes (instead of single ones) are required.
     *
     * @param array $registryAuth
     * @return array
     * @link https://docs.docker.com/engine/api/v1.40/#section/Authentication for details about the AuthConfig object
     * @link https://github.com/docker/docker/issues/9315 for error description
     */
    private function authHeaders($registryAuth)
    {
        $headers = array();
        if ($registryAuth !== null) {
            $headers['X-Registry-Auth'] = strtr(base64_encode($this->json($registryAuth)), '+/', '-_');
        }

        return $headers;
    }

    /**
     * Internal helper function used to build the query string and omit `null` values
     *
     * Uses `rawurlencode()` because `http_build_query()` would encode `~` and spaces
     * differently without `PHP_QUERY_RFC3986`, which requires PHP 5.4+.
     *
     * @param array<string,mixed> $params
     * @return string query string with leading `?` or empty string if there is no query
     */
    private function queryString(array $params)
    {
        $query = array();
        foreach ($params as $key => $value) {
            if ($value !== null) {
                $query[] = $key . '=' . rawurlencode($value);
            }
        }

        return $query ? '?' . implode('&', $query) : '';
    }
}
