<?php

namespace Clue\React\Docker;

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Response;
use Clue\React\Docker\Io\ResponseParser;
use React\Promise\PromiseInterface as Promise;
use Clue\React\Docker\Io\StreamingParser;
use React\Stream\ReadableStreamInterface;

/**
 * Docker Remote API client
 *
 * The Remote API can be used to control your local Docker daemon.
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

    /**
     * Instantiate new Client
     *
     * SHOULD NOT be called manually, see Factory::createClient() instead
     *
     * @param Browser              $browser         Browser instance to use, requires correct Sender and base URI
     * @param ResponseParser|null  $parser          (optional) ResponseParser instance to use
     * @param StreamingParser|null $streamingParser (optional) StreamingParser instance to use
     * @see Factory::createClient()
     */
    public function __construct(Browser $browser, ResponseParser $parser = null, StreamingParser $streamingParser = null)
    {
        if ($parser === null) {
            $parser = new ResponseParser();
        }

        if ($streamingParser === null) {
            $streamingParser = new StreamingParser();
        }

        $this->browser = $browser;
        $this->parser = $parser;
        $this->streamingParser = $streamingParser;
    }

    /**
     * Ping the docker server
     *
     * @return Promise Promise<string> "OK"
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#ping-the-docker-server
     */
    public function ping()
    {
        return $this->browser->get($this->browser->resolve('/_ping'))->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Display system-wide information
     *
     * @return Promise Promise<array> system info (see link)
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#display-system-wide-information
     */
    public function info()
    {
        return $this->browser->get($this->browser->resolve('/info'))->then(array($this->parser, 'expectJson'));
    }

    /**
     * Show the docker version information
     *
     * @return Promise Promise<array> version info (see link)
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#show-the-docker-version-information
     */
    public function version()
    {
        return $this->browser->get($this->browser->resolve('/version'))->then(array($this->parser, 'expectJson'));
    }

    /**
     * List containers
     *
     * @param boolean $all
     * @param boolean $size
     * @return Promise Promise<array> list of container objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#list-containers
     */
    public function containerList($all = false, $size = false)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @return Promise Promise<array> container properties `array('Id' => $containerId', 'Warnings' => array())`
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#create-a-container
     */
    public function containerCreate($config, $name = null)
    {
        return $this->postJson(
            $this->browser->resolve(
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
     * @return Promise Promise<array> container properties
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#inspect-a-container
     */
    public function containerInspect($container)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @return Promise Promise<array>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#list-processes-running-inside-a-container
     */
    public function containerTop($container, $ps_args = null)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @return Promise Promise<array>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#inspect-changes-on-a-containers-filesystem
     */
    public function containerChanges($container)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @return Promise Promise<string> tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#export-a-container
     * @link https://github.com/clue/php-tar-react library clue/tar-react
     * @see self::containerExportStream()
     */
    public function containerExport($container)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
            $this->browser->get(
                $this->browser->resolve(
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
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#resize-a-container-tty
     */
    public function containerResize($container, $w, $h)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#start-a-container
     */
    public function containerStart($container, $config = array())
    {
        return $this->postJson(
            $this->browser->resolve(
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
     * @param string $container container ID
     * @param int    $t         number of seconds to wait before killing the container
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#stop-a-container
     */
    public function containerStop($container, $t)
    {
        return $this->browser->post(
            $this->browser->resolve(
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
     * @param string $container container ID
     * @param int    $t         number of seconds to wait before killing the container
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#restart-a-container
     */
    public function containerRestart($container, $t)
    {
        return $this->browser->post(
            $this->browser->resolve(
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
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#kill-a-container
     */
    public function containerKill($container, $signal = null)
    {
        return $this->browser->post(
            $this->browser->resolve(
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
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#pause-a-container
     */
    public function containerPause($container)
    {
        return $this->browser->post(
            $this->browser->resolve(
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
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#unpause-a-container
     */
    public function containerUnpause($container)
    {
        return $this->browser->post(
            $this->browser->resolve(
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
     * @return Promise Promise<array> `array('StatusCode' => 0)` (see link)
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#wait-a-container
     */
    public function containerWait($container)
    {
        return $this->browser->post(
            $this->browser->resolve(
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
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#remove-a-container
     */
    public function containerRemove($container, $v = false, $force = false)
    {
        return $this->browser->delete(
            $this->browser->resolve(
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
     * @return Promise Promise<string> tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#copy-files-or-folders-from-a-container
     * @link https://github.com/clue/php-tar-react library clue/tar-react
     * @see self::containerCopyStream()
     */
    public function containerCopy($container, $config)
    {
        return $this->postJson(
            $this->browser->resolve(
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
            $this->postJson(
                $this->browser->resolve(
                    '/containers/{container}/copy',
                    array(
                        'container' => $container
                    )
                ),
                $config
            )
        );
    }

    /**
     * List images
     *
     * @param boolean $all
     * @return Promise Promise<array> list of image objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#list-images
     * @todo support $filters param
     */
    public function imageList($all = false)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @return Promise Promise<array> stream of message objects
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
            $this->browser->post(
                $this->browser->resolve(
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
     * @return Promise Promise<array> image properties
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#inspect-an-image
     */
    public function imageInspect($image)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @return Promise Promise<array> list of image history objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#get-the-history-of-an-image
     */
    public function imageHistory($image)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @return Promise Promise<array> list of image push messages
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
            $this->browser->post(
                $this->browser->resolve(
                    '/images{+registry}/{image}/push{?tag}',
                    array(
                        'registry' => ($registry === null ? '' : ('/' . $registry)),
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
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#tag-an-image-into-a-repository
     */
    public function imageTag($image, $repo, $tag = null, $force = false)
    {
        return $this->browser->post(
            $this->browser->resolve(
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
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#remove-an-image
     */
    public function imageRemove($image, $force = false, $noprune = false)
    {
        return $this->browser->delete(
            $this->browser->resolve(
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
     * @return Promise Promise<array> list of image search result objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#search-images
     */
    public function imageSearch($term)
    {
        return $this->browser->get(
            $this->browser->resolve(
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
     * @param string $container container ID
     * @param array  $config    `array('Cmd' => 'date')` (see link)
     * @return Promise Promise<array> with exec ID in the form of `array("Id" => $execId)`
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#exec-create
     */
    public function execCreate($container, $config)
    {
        return $this->postJson(
            $this->browser->resolve(
                '/containers/{container}/exec',
                array(
                    'container' => $container
                )
            ),
            $config
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Starts a previously set up exec instance id.
     *
     * If detach is true, this API returns after starting the exec command.
     * Otherwise, this API sets up an interactive session with the exec command.
     *
     * @param string $exec   exec ID
     * @param array  $config (see link)
     * @return Promise Promise<array> stream of message objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#exec-start
     */
    public function execStart($exec, $config)
    {
        return $this->postJson(
            $this->browser->resolve(
                '/exec/{exec}/start',
                array(
                    'exec' => $exec
                )
            ),
            $config
        )->then(array($this->parser, 'expectJson'));
    }

    /**
     * Resizes the tty session used by the exec command id.
     *
     * This API is valid only if tty was specified as part of creating and starting the exec command.
     *
     * @param string $exec exec ID
     * @param int    $w    TTY width
     * @param int    $h    TTY height
     * @return Promise Promise<null>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#exec-resize
     */
    public function execResize($exec, $w, $h)
    {
        return $this->browser->get(
            $this->browser->resolve(
                '/exec/{exec}/resize{?w,h}',
                array(
                    'exec' => $exec,
                    'w' => $w,
                    'h' => $h
                )
            )
        )->then(array($this->parser, 'expectEmpty'));
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
