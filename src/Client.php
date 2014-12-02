<?php

namespace Clue\React\Docker;

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Response;
use Clue\React\Docker\Io\ResponseParser;
use React\Promise\PromiseInterface as Promise;

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
    public function __construct(Browser $browser, $url, ResponseParser $parser = null)
    {
        if ($parser === null) {
            $parser = new ResponseParser();
        }

        $this->browser = $browser;
        $this->url = $url;
        $this->parser = $parser;
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
     * @param string $container container ID
     * @return Promise Promise<array>
     * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#list-processes-running-inside-a-container
     */
    public function containerTop($container)
    {
        return $this->browser->get($this->url('/containers/%s/top', $container))->then(array($this->parser, 'expectJson'));
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
    public function containerDelete($container, $v = false, $force = false)
    {
        return $this->browser->delete($this->url('/containers/%s?v=%u&force=%u', $container, $v, $force))->then(array($this->parser, 'expectEmpty'));
    }

    private function url($url)
    {
        $args = func_get_args();
        array_shift($args);

        return $this->url . vsprintf($url, $args);
    }
}
