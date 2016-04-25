<?php

namespace Clue\React\Docker;

use Clue\React\Buzz\Browser;
use Clue\React\Docker\Io\ResponseParser;
use React\Promise\PromiseInterface;
use Clue\React\Docker\Io\StreamingParser;
use React\Stream\ReadableStreamInterface;
use Rize\UriTemplate;

/**
 * The Docker Remote API client can be used to control your (local) Docker daemon.
 *
 * This Client implementation provides a very thin wrapper around this
 * Remote API and exposes its exact data models.
 * The Client uses HTTP requests via a local UNIX socket path or remotely via a
 * TLS-backed TCP/IP connection.
 *
 * Primarily tested against v1.15 API, but should also work against
 * other versions (in particular newer ones).
 *
 * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/
 */
class Client
{
    private $browser;
    private $parser;
    private $streamingParser;
    private $uri;

    /**
     * Instantiate new Client
     *
     * SHOULD NOT be called manually, see Factory::createClient() instead
     *
     * @param Browser              $browser         Browser instance to use, requires correct Sender and base URI
     * @param ResponseParser|null  $parser          (optional) ResponseParser instance to use
     * @param StreamingParser|null $streamingParser (optional) StreamingParser instance to use
     * @param UriTemplate|null     $uri             (optional) UriTemplate instance to use
     * @see Factory::createClient()
     */
    public function __construct(Browser $browser, ResponseParser $parser = null, StreamingParser $streamingParser = null, UriTemplate $uri = null)
    {
        if ($parser === null) {
            $parser = new ResponseParser();
        }

        if ($streamingParser === null) {
            $streamingParser = new StreamingParser();
        }

        if ($uri === null) {
            $uri = new UriTemplate();
        }

        $this->browser = $browser;
        $this->parser = $parser;
        $this->streamingParser = $streamingParser;
        $this->uri = $uri;
    }

    /**
     * Ping the docker server
     *
     * @return PromiseInterface Promise<string> "OK"
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#ping-the-docker-server
     */
    public function ping()
    {
        return $this->browser->get('/_ping')->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Display system-wide information
     *
     * @return PromiseInterface Promise<array> system info (see link)
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#display-system-wide-information
     */
    public function info()
    {
        return $this->browser->get('/info')->then(array($this->parser, 'expectJson'));
    }

    /**
     * Show the docker version information
     *
     * @return PromiseInterface Promise<array> version info (see link)
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#show-the-docker-version-information
     */
    public function version()
    {
        return $this->browser->get('/version')->then(array($this->parser, 'expectJson'));
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
     * @param array    $filters (optional) filters to apply (requires API v1.16+ / Docker v1.4+)
     * @return PromiseInterface Promise<array> array of event objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#monitor-dockers-events
     * @uses self::eventsStream()
     * @see self::eventsStream()
     */
    public function events($since = null, $until = null, $filters = array())
    {
        return $this->streamingParser->deferredStream(
            $this->eventsStream($since, $until, $filters),
            'progress'
        );
    }

    /**
     * Get container events from docker
     *
     * This is a JSON streaming API endpoint that returns a stream instance.
     *
     * The resulting stream will emit the following events:
     * - progress: for *each* element in the update stream
     * - error:    once if an error occurs, will close() stream then
     * - close:    once the stream ends (either finished or after "error")
     *
     * Please note that the resulting stream does not emit any "data" events, so
     * you will not be able to pipe() its events into another `WritableStream`.
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
     * @param array      $filters (optional) filters to apply (requires API v1.16+ / Docker v1.4+)
     * @return ReadableStreamInterface stream of event objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#monitor-dockers-events
     * @see self::events()
     */
    public function eventsStream($since = null, $until = null, $filters = array())
    {
        return $this->streamingParser->parseJsonStream(
            $this->browser->withOptions(array('streaming' => true))->get(
                $this->uri->expand(
                    '/events{?since,until,filters}',
                    array(
                        'since' => $since,
                        'until' => $until,
                        'filters' => $filters ? json_encode($filters) : null
                    )
                )
            )
        );
    }

    /**
     * List containers
     *
     * @param boolean $all
     * @param boolean $size
     * @return PromiseInterface Promise<array> list of container objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#list-containers
     */
    public function containerList($all = false, $size = false)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/containers/json{?all,size}',
                array(
                    'all' => $this->boolArg($all),
                    'size' => $this->boolArg($size)
                )
            )
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Create a container
     *
     * @param array       $config e.g. `array('Image' => 'busybox', 'Cmd' => 'date')` (see link)
     * @param string|null $name   (optional) name to assign to this container
     * @return PromiseInterface Promise<array> container properties `array('Id' => $containerId', 'Warnings' => array())`
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#create-a-container
     */
    public function containerCreate($config, $name = null)
    {
        return $this->postJson(
            $this->uri->expand(
                '/containers/create{?name}',
                array(
                    'name' => $name
                )
            ),
            $config
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Return low-level information on the container id
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<array> container properties
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#inspect-a-container
     */
    public function containerInspect($container)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/containers/{container}/json',
                array(
                    'container' => $container
                )
            )
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * List processes running inside the container id
     *
     * @param string      $container container ID
     * @param string|null $ps_args   (optional) ps arguments to use (e.g. aux)
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#list-processes-running-inside-a-container
     */
    public function containerTop($container, $ps_args = null)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/containers/{container}/top{?ps_args}',
                array(
                    'container' => $container,
                    'ps_args' => $ps_args
                )
            )
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Inspect changes on container id's filesystem
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#inspect-changes-on-a-containers-filesystem
     */
    public function containerChanges($container)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/containers/{container}/changes',
                array(
                    'container' => $container
                )
            )
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
     * work is clue/tar-react (see links).
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<string> tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#export-a-container
     * @link https://github.com/clue/php-tar-react library clue/tar-react
     * @see self::containerExportStream()
     */
    public function containerExport($container)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/containers/{container}/export',
                array(
                    'container' => $container
                )
            )
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
     * work is clue/tar-react (see links).
     *
     * The resulting stream is a well-behaving readable stream that will emit
     * the normal stream events.
     *
     * @param string $container container ID
     * @return ReadableStreamInterface tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#export-a-container
     * @link https://github.com/clue/php-tar-react library clue/tar-react
     * @see self::containerExport()
     */
    public function containerExportStream($container)
    {
        return $this->streamingParser->parsePlainStream(
            $this->browser->withOptions(array('streaming' => true))->get(
                $this->uri->expand(
                    '/containers/{container}/export',
                    array(
                        'container' => $container
                    )
                )
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#resize-a-container-tty
     */
    public function containerResize($container, $w, $h)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/containers/{container}/resize{?w,h}',
                array(
                    'container' => $container,
                    'w' => $w,
                    'h' => $h,
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Start the container id
     *
     * @param string $container container ID
     * @param array  $config    (optional) start config (see link)
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#start-a-container
     */
    public function containerStart($container, $config = array())
    {
        return $this->postJson(
            $this->uri->expand(
                '/containers/{container}/start',
                array(
                    'container' => $container
                )
            ),
            $config
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Stop the container id
     *
     * @param string   $container container ID
     * @param null|int $t         (optional) number of seconds to wait before killing the container
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#stop-a-container
     */
    public function containerStop($container, $t = null)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/containers/{container}/stop{?t}',
                array(
                    'container' => $container,
                    't' => $t
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Restart the container id
     *
     * @param string   $container container ID
     * @param null|int $t         (optional) number of seconds to wait before killing the container
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#restart-a-container
     */
    public function containerRestart($container, $t = null)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/containers/{container}/restart{?t}',
                array(
                    'container' => $container,
                    't' => $t
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Kill the container id
     *
     * @param string          $container container ID
     * @param string|int|null $signal    (optional) signal name or number
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#kill-a-container
     */
    public function containerKill($container, $signal = null)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/containers/{container}/kill{?signal}',
                array(
                    'container' => $container,
                    'signal' => $signal
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Pause the container id
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#pause-a-container
     */
    public function containerPause($container)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/containers/{container}/pause',
                array(
                    'container' => $container
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Unpause the container id
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#unpause-a-container
     */
    public function containerUnpause($container)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/containers/{container}/unpause',
                array(
                    'container' => $container
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Block until container id stops, then returns the exit code
     *
     * @param string $container container ID
     * @return PromiseInterface Promise<array> `array('StatusCode' => 0)` (see link)
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#wait-a-container
     */
    public function containerWait($container)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/containers/{container}/wait',
                array(
                    'container' => $container
                )
            )
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Remove the container id from the filesystem
     *
     * @param string  $container container ID
     * @param boolean $v         Remove the volumes associated to the container. Default false
     * @param boolean $force     Kill then remove the container. Default false
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#remove-a-container
     */
    public function containerRemove($container, $v = false, $force = false)
    {
        return $this->browser->delete(
            $this->uri->expand(
                '/containers/{container}{?v,force}',
                array(
                    'container' => $container,
                    'v' => $this->boolArg($v),
                    'force' => $this->boolArg($force)
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Copy files or folders of container id
     *
     * This resolves with a string in the TAR file format containing all files
     * specified in the $config array.
     *
     * Keep in mind that this means the whole string has to be kept in memory.
     * For bigger containers it's usually a better idea to use a streaming approach,
     * see containerCopyStream() for more details.
     *
     * Accessing individual files in the TAR file format string is out of scope
     * for this library. Several libraries are available, one that is known to
     * work is clue/tar-react (see links).
     *
     * @param string $container container ID
     * @param array  $config    resources to copy `array('Resource' => 'file.txt')` (see link)
     * @return PromiseInterface Promise<string> tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#copy-files-or-folders-from-a-container
     * @link https://github.com/clue/php-tar-react library clue/tar-react
     * @see self::containerCopyStream()
     */
    public function containerCopy($container, $config)
    {
        return $this->postJson(
            $this->uri->expand(
                '/containers/{container}/copy',
                array(
                    'container' => $container
                )
            ),
            $config
        )->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Copy files or folders of container id
     *
     * This returns a stream in the TAR file format containing all files
     * specified in the $config array.
     *
     * This works for (any number of) files of arbitrary sizes as only small chunks have to
     * be kept in memory.
     *
     * Accessing individual files in the TAR file format stream is out of scope
     * for this library. Several libraries are available, one that is known to
     * work is clue/tar-react (see links).
     *
     * The resulting stream is a well-behaving readable stream that will emit
     * the normal stream events.
     *
     * @param string $container container ID
     * @param array  $config    resources to copy `array('Resource' => 'file.txt')` (see link)
     * @return ReadableStreamInterface tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#copy-files-or-folders-from-a-container
     * @link https://github.com/clue/php-tar-react library clue/tar-react
     * @see self::containerCopy()
     */
    public function containerCopyStream($container, $config)
    {
        return $this->streamingParser->parsePlainStream(
            $this->browser->withOptions(array('streaming' => true))->post(
                $this->uri->expand(
                    '/containers/{container}/copy',
                    array(
                        'container' => $container
                    )
                ),
                array(
                    'Content-Type' => 'application/json'
                ),
                $this->json($config)
            )
        );
    }

    /**
     * List images
     *
     * @param boolean $all
     * @return PromiseInterface Promise<array> list of image objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#list-images
     * @todo support $filters param
     */
    public function imageList($all = false)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/images/json{?all}',
                array(
                    'all' => $this->boolArg($all)
                )
            )
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#create-an-image
     * @uses self::imageCreateStream()
     */
    public function imageCreate($fromImage = null, $fromSrc = null, $repo = null, $tag = null, $registry = null, $registryAuth = null)
    {
        $stream = $this->imageCreateStream($fromImage, $fromSrc, $repo, $tag, $registry, $registryAuth);

        return $this->streamingParser->deferredStream($stream, 'progress');
    }

    /**
     * Create an image, either by pulling it from the registry or by importing it
     *
     * This is a JSON streaming API endpoint that returns a stream instance.
     *
     * The resulting stream will emit the following events:
     * - progress: for *each* element in the update stream
     * - error:    once if an error occurs, will close() stream then
     * - close:    once the stream ends (either finished or after "error")
     *
     * Please note that the resulting stream does not emit any "data" events, so
     * you will not be able to pipe() its events into another `WritableStream`.
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#create-an-image
     * @see self::imageCreate()
     * @uses self::authHeaders()
     */
    public function imageCreateStream($fromImage = null, $fromSrc = null, $repo = null, $tag = null, $registry = null, $registryAuth = null)
    {
        return $this->streamingParser->parseJsonStream(
            $this->browser->withOptions(array('streaming' => true))->post(
                $this->uri->expand(
                    '/images/create{?fromImage,fromSrc,repo,tag,registry}',
                    array(
                        'fromImage' => $fromImage,
                        'fromSrc' => $fromSrc,
                        'repo' => $repo,
                        'tag' => $tag,
                        'registry' => $registry
                    )
                ),
                $this->authHeaders($registryAuth)
            )
        );
    }

    /**
     * Return low-level information on the image name
     *
     * @param string $image image ID
     * @return PromiseInterface Promise<array> image properties
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#inspect-an-image
     */
    public function imageInspect($image)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/images/{image}/json',
                array(
                    'image' => $image
                )
            )
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Return the history of the image name
     *
     * @param string $image image ID
     * @return PromiseInterface Promise<array> list of image history objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#get-the-history-of-an-image
     */
    public function imageHistory($image)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/images/{image}/history',
                array(
                    'image' => $image
                )
            )
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#push-an-image-on-the-registry
     */
    public function imagePush($image, $tag = null, $registry = null, $registryAuth = null)
    {
        $stream = $this->imagePushStream($image, $tag, $registry, $registryAuth);

        return $this->streamingParser->deferredStream($stream, 'progress');
    }

    /**
     * Push the image name on the registry
     *
     * This is a JSON streaming API endpoint that returns a stream instance.
     *
     * The resulting stream will emit the following events:
     * - progress: for *each* element in the update stream
     * - error:    once if an error occurs, will close() stream then
     * - close:    once the stream ends (either finished or after "error")
     *
     * Please note that the resulting stream does not emit any "data" events, so
     * you will not be able to pipe() its events into another `WritableStream`.
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#push-an-image-on-the-registry
     */
    public function imagePushStream($image, $tag = null, $registry = null, $registryAuth = null)
    {
        return $this->streamingParser->parseJsonStream(
            $this->browser->withOptions(array('streaming' => true))->post(
                $this->uri->expand(
                    '/images{/registry}/{image}/push{?tag}',
                    array(
                        'registry' => $registry,
                        'image' => $image,
                        'tag' => $tag
                    )
                ),
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#tag-an-image-into-a-repository
     */
    public function imageTag($image, $repo, $tag = null, $force = false)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/images/{image}/tag{?repo,tag,force}',
                array(
                    'image' => $image,
                    'repo' => $repo,
                    'tag' => $tag,
                    'force' => $this->boolArg($force)
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Remove the image name from the filesystem
     *
     * @param string  $image   image ID
     * @param boolean $force   1/True/true or 0/False/false, default false
     * @param boolean $noprune 1/True/true or 0/False/false, default false
     * @return PromiseInterface Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#remove-an-image
     */
    public function imageRemove($image, $force = false, $noprune = false)
    {
        return $this->browser->delete(
            $this->uri->expand(
                '/images/{image}{?force,noprune}',
                array(
                    'image' => $image,
                    'force' => $this->boolArg($force),
                    'noprune' => $this->boolArg($noprune)
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Search for an image on Docker Hub.
     *
     * @param string $term term to search
     * @return PromiseInterface Promise<array> list of image search result objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#search-images
     */
    public function imageSearch($term)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/images/search{?term}',
                array(
                    'term' => $term
                )
            )
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
     * @param string|int   $user       user-specific exec, otherwise defaults to main container user (requires API v1.19+ / Docker v1.7+)
     * @param boolean      $privileged privileged exec with all capabilities enabled (requires API v1.19+ / Docker v1.7+)
     * @return PromiseInterface Promise<array> with exec ID in the form of `array("Id" => $execId)`
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#exec-create
     */
    public function execCreate($container, $cmd, $tty = false, $stdin = false, $stdout = true, $stderr = true, $user = '', $privileged = false)
    {
        if (!is_array($cmd)) {
            $cmd = array('sh', '-c', (string)$cmd);
        }

        return $this->postJson(
            $this->uri->expand(
                '/containers/{container}/exec',
                array(
                    'container' => $container
                )
            ),
            array(
                'Cmd' => $cmd,
                'Tty' => !!$tty,
                'AttachStdin' => !!$stdin,
                'AttachStdout' => !!$stdout,
                'AttachStderr' => !!$stderr,
                'User' => $user,
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#exec-start
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#exec-start
     * @see self::execStart()
     * @see self::execStartStream()
     */
    public function execStartDetached($exec, $tty = false)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/exec/{exec}/start',
                array(
                    'exec' => $exec
                )
            ),
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
     * @param string  $exec exec ID
     * @param boolean $tty  tty mode
     * @return ReadableStreamInterface stream of exec data
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#exec-start
     * @see self::execStart()
     * @see self::execStartDetached()
     */
    public function execStartStream($exec, $tty = false)
    {
        $stream = $this->streamingParser->parsePlainStream(
            $this->browser->withOptions(array('streaming' => true))->post(
                $this->uri->expand(
                    '/exec/{exec}/start',
                    array(
                        'exec' => $exec
                    )
                ),
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
            $stream = $this->streamingParser->demultiplexStream($stream);
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
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#exec-resize
     */
    public function execResize($exec, $w, $h)
    {
        return $this->browser->post(
            $this->uri->expand(
                '/exec/{exec}/resize{?w,h}',
                array(
                    'exec' => $exec,
                    'w' => $w,
                    'h' => $h
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Returns low-level information about the exec command id.
     *
     * Requires API v1.16+ / Docker v1.4+
     *
     * @param string $exec exec ID
     * @return PromiseInterface Promise<array>
     * @link https://docs.docker.com/engine/reference/api/docker_remote_api_v1.16/#exec-inspect
     */
    public function execInspect($exec)
    {
        return $this->browser->get(
            $this->uri->expand(
                '/exec/{exec}/json',
                array(
                    'exec' => $exec
                )
            )
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
     * AuthConfig, set as the X-Registry-Auth header, is currently a Base64
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
     * @link https://docs.docker.com/reference/api/docker_remote_api/ for details about the AuthConfig object
     * @link https://github.com/docker/docker/issues/9315 for error description
     */
    private function authHeaders($registryAuth)
    {
        $headers = array();
        if ($registryAuth !== null) {
            $headers['X-Registry-Auth'] = base64_encode($this->json($registryAuth));
        }

        return $headers;
    }

    /**
     * Internal helper function used to pass boolean true values to endpoints and omit boolean false values
     *
     * @param boolean $value
     * @return int|null returns the integer `1` for boolean true values and a `null` for boolean false values
     * @see Browser::resolve()
     */
    private function boolArg($value)
    {
        return ($value ? 1 : null);
    }
}
