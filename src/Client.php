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
 * Primarily tested against current v1.15 API, but should also work against
 * other versions.
 *
 * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/
 */
class Client
{
    private $browser;
    private $url;
    private $parser;
    private $streamingParser;

    /**
     * Instantiate new Client
     *
     * SHOULD NOT be called manually, see Factory::createClient() instead
     *
     * @param Browser             $browser
     * @param string              $url
     * @param ResponseParser|null $parser
     * @see Factory::createClient()
     */
    public function __construct(Browser $browser, $url, ResponseParser $parser = null, StreamingParser $streamingParser = null)
    {
        if ($parser === null) {
            $parser = new ResponseParser();
        }

        if ($streamingParser === null) {
            $streamingParser = new StreamingParser();
        }

        $this->browser = $browser;
        $this->url = $url;
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
        return $this->browser->get($this->url('/_ping'))->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Display system-wide information
     *
     * @return Promise Promise<array> system info (see link)
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#display-system-wide-information
     */
    public function info()
    {
        return $this->browser->get($this->url('/info'))->then(array($this->parser, 'expectJson'));
    }

    /**
     * Show the docker version information
     *
     * @return Promise Promise<array> version info (see link)
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#show-the-docker-version-information
     */
    public function version()
    {
        return $this->browser->get($this->url('/version'))->then(array($this->parser, 'expectJson'));
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
        return $this->browser->get($this->url('/containers/json?all=%u&size=%u', $all, $size))->then(array($this->parser, 'expectJson'));
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
        return $this->postJson($this->url('/containers/create?name=%s', $name), $config)->then(array($this->parser, 'expectJson'));
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
        return $this->browser->get($this->url('/containers/%s/json', $container))->then(array($this->parser, 'expectJson'));
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
        return $this->browser->get($this->url('/containers/%s/top?ps_args=%s', $container, $ps_args))->then(array($this->parser, 'expectJson'));
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
        return $this->browser->get($this->url('/containers/%s/changes', $container))->then(array($this->parser, 'expectJson'));
    }

    /**
     * Export the contents of container id
     *
     * @param string $container container ID
     * @return Promise Promise<string> tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#export-a-container
     * @see self::containerExportStream()
     */
    public function containerExport($container)
    {
        return $this->browser->get($this->url('/containers/%s/export', $container))->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Export the contents of container id
     *
     * The resulting stream is a well-behaving readable stream that will emit
     * the normal stream events.
     *
     * @param string $container container ID
     * @return ReadableStreamInterface tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#export-a-container
     * @see self::containerExport()
     */
    public function containerExportStream($container)
    {
        return $this->streamingParser->parsePlainStream($this->browser->get($this->url('/containers/%s/export', $container)));
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
        return $this->browser->get($this->url('/containers/%s/resize?w=%u&h=%u', $container, $w, $h))->then(array($this->parser, 'expectEmpty'));
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
        return $this->postJson($this->url('/containers/%s/start', $container), $config)->then(array($this->parser, 'expectEmpty'));
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
        return $this->browser->post($this->url('/containers/%s/stop?t=%u', $container, $t))->then(array($this->parser, 'expectEmpty'));
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
        return $this->browser->post($this->url('/containers/%s/restart?t=%u', $container, $t))->then(array($this->parser, 'expectEmpty'));
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
        return $this->browser->post($this->url('/containers/%s/kill?signal=%s', $container, $signal))->then(array($this->parser, 'expectEmpty'));
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
        return $this->browser->post($this->url('/containers/%s/pause', $container))->then(array($this->parser, 'expectEmpty'));
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
        return $this->browser->post($this->url('/containers/%s/unpause', $container))->then(array($this->parser, 'expectEmpty'));
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
        return $this->browser->post($this->url('/containers/%s/wait', $container))->then(array($this->parser, 'expectJson'));
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
        return $this->browser->delete($this->url('/containers/%s?v=%u&force=%u', $container, $v, $force))->then(array($this->parser, 'expectEmpty'));
    }

    /**
     * Copy files or folders of container id
     *
     * @param string $container container ID
     * @param array  $config    resources to copy `array('Resource' => 'file.txt')` (see link)
     * @return Promise Promise<string> tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#copy-files-or-folders-from-a-container
     * @see self::containerCopyStream()
     */
    public function containerCopy($container, $config)
    {
        return $this->postJson($this->url('/containers/%s/copy', $container), $config)->then(array($this->parser, 'expectPlain'));
    }

    /**
     * Copy files or folders of container id
     *
     * The resulting stream is a well-behaving readable stream that will emit
     * the normal stream events.
     *
     * @param string $container container ID
     * @param array  $config    resources to copy `array('Resource' => 'file.txt')` (see link)
     * @return ReadableStreamInterface tar stream
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#copy-files-or-folders-from-a-container
     * @see self::containerCopy()
     */
    public function containerCopyStream($container, $config)
    {
        return $this->streamingParser->parsePlainStream($this->postJson($this->url('/containers/%s/copy', $container), $config));
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
        return $this->browser->get($this->url('/images/json?all=%u', $all))->then(array($this->parser, 'expectJson'));
    }

    /**
     * Create an image, either by pulling it from the registry or by importing it
     *
     * Will resolve with an array of all progress events. These can also be
     * accessed via the Promise progress handler.
     *
     * @param string|null $fromImage    name of the image to pull
     * @param string|null $fromSrc      source to import, - means stdin
     * @param string|null $repo         repository
     * @param string|null $tag          (optional) (obsolete) tag, use $repo and $fromImage in the "name:tag" instead
     * @param string|null $registry     the registry to pull from
     * @param array|null  $registryAuth AuthConfig object (to send as X-Registry-Auth header)
     * @return Promise Promise<array> stream of message objects
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#create-an-image
     * @see self::imageCreateStream()
     */
    public function imageCreate($fromImage = null, $fromSrc = null, $repo = null, $tag = null, $registry = null, $registryAuth = null)
    {
        $stream = $this->imageCreateStream($fromImage, $fromSrc, $repo, $tag, $registry, $registryAuth);

        return $this->streamingParser->deferredStream($stream, 'progress');
    }

    /**
     * Create an image, either by pulling it from the registry or by importing it
     *
     * The resulting stream will emit the following events:
     * - progress: for *each* element in the update stream
     * - error:    once if an error occurs, will close() stream then
     * - close:    once the stream ends (either finished or after "error")
     *
     * Please note that the resulting stream does not emit any "data" events, so
     * you will not be able to pipe() its events into another `WritableStream`.
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
     */
    public function imageCreateStream($fromImage = null, $fromSrc = null, $repo = null, $tag = null, $registry = null, $registryAuth = null)
    {
        return $this->streamingParser->parseJsonStream($this->browser->post(
            $this->url('/images/create?fromImage=%s&fromSrc=%s&repo=%s&tag=%s&registry=%s', $fromImage, $fromSrc, $repo, $tag, $registry),
            $this->authHeaders($registryAuth)
        ));
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
        return $this->browser->get($this->url('/images/%s/json', $image))->then(array($this->parser, 'expectJson'));
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
        return $this->browser->get($this->url('/images/%s/history', $image))->then(array($this->parser, 'expectJson'));
    }

    /**
     * Push the image name on the registry
     *
     * @param string      $image        image ID
     * @param string|null $tag          (optional) the tag to associate with the image on the registry
     * @param string|null $registry     (optional) the registry to push to (e.g. `registry.acme.com:5000`)
     * @param array|null  $registryAuth (optional) AuthConfig object (to send as X-Registry-Auth header)
     * @return Promise Promise<array> list of image push messages
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#push-an-image-on-the-registry
     */
    public function imagePush($image, $tag = null, $registry = null, $registryAuth = null)
    {
        $path = '/images' . ($registry === null ? '' : ('/' . $registry)) . '/%s/push?tag=%s';
        return $this->browser->post($this->url($path, $image, $tag), $this->authHeaders($registryAuth))->then(array($this->parser, 'expectJson'));
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
        return $this->browser->post($this->url('/images/%s/tag?repo=%s&tag=%s&force=%u', $image, $repo, $tag, $force))->then(array($this->parser, 'expectEmpty'));
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
        return $this->browser->delete($this->url('/images/%s?force=%u&noprune=%u', $image, $force, $noprune))->then(array($this->parser, 'expectEmpty'));
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
        return $this->browser->get($this->url('/images/search?term=%s', $term))->then(array($this->parser, 'expectJson'));
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
        return $this->postJson($this->url('/containers/%s/exec', $container), $config)->then(array($this->parser, 'expectJson'));
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
        return $this->postJson($this->url('/exec/%s/start', $exec), $config)->then(array($this->parser, 'expectJson'));
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
        return $this->browser->get($this->url('/exec/%s/resize?w=%u&h=%u', $exec, $w, $h))->then(array($this->parser, 'expectEmpty'));
    }

    private function url($url)
    {
        $args = func_get_args();
        array_shift($args);

        return $this->url . vsprintf($url, $args);
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

    private function authHeaders($registryAuth)
    {
        $headers = array();
        if ($registryAuth !== null) {
            $headers['X-Registry-Auth'] = base64_encode($this->json($registryAuth));
        }

        return $headers;
    }
}
