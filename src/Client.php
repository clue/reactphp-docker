<?php

namespace Clue\React\Docker;

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Response;
use Clue\React\Docker\Io\ResponseParser;

/**
 *
 * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#attach-to-a-container
 */
class Client
{
    private $browser;
    private $url;
    private $parser;

    public function __construct(Browser $browser, $url, ResponseParser $parser = null)
    {
        if ($parser === null) {
            $parser = new ResponseParser();
        }

        $this->browser = $browser;
        $this->url = $url;
        $this->parser = $parser;
    }

    public function ping()
    {
        return $this->browser->get($this->url('/_ping'))->then(array($this->parser, 'expectPlain'));
    }

    public function info()
    {
        return $this->browser->get($this->url('/info'))->then(array($this->parser, 'expectJson'));
    }

    public function version()
    {
        return $this->browser->get($this->url('/version'))->then(array($this->parser, 'expectJson'));
    }

    public function containerList($all = false, $size = false)
    {
        return $this->browser->get($this->url('/containers/json?all=%u&size=%u', $all, $size))->then(array($this->parser, 'expectJson'));
    }

    public function containerInspect($container)
    {
        return $this->browser->get($this->url('/containers/%s/json', $container))->then(array($this->parser, 'expectJson'));
    }

    public function containerTop($container)
    {
        return $this->browser->get($this->url('/containers/%s/top', $container))->then(array($this->parser, 'expectJson'));
    }

    public function containerWait($container)
    {
        return $this->browser->post($this->url('/containers/%s/wait', $container))->then(array($this->parser, 'expectJson'));
    }

    /**
     *
     *
     * @param string $container
     * @param int    $t         number of seconds to wait before killing the container
     */
    public function containerStop($container, $t)
    {
        return $this->browser->post($this->url('/containers/%s/stop?t=%u', $container, $t))->then(array($this->parser, 'expectEmpty'));
    }

    /**
     *
     *
     * @param string $container
     * @param int    $t         number of seconds to wait before killing the container
     */
    public function containerRestart($container, $t)
    {
        return $this->browser->post($this->url('/containers/%s/restart?t=%u', $container, $t))->then(array($this->parser, 'expectEmpty'));
    }

    public function containerKill($container, $signal = null)
    {
        return $this->browser->post($this->url('/containers/%s/kill?signal=%s', $container, $signal))->then(array($this->parser, 'expectEmpty'));
    }

    public function containerPause($container)
    {
        return $this->browser->post($this->url('/containers/%s/pause', $container))->then(array($this->parser, 'expectEmpty'));
    }

    public function containerUnpause($container)
    {
        return $this->browser->post($this->url('/containers/%s/unpause', $container))->then(array($this->parser, 'expectEmpty'));
    }

    /**
     *
     *
     * @param string $container
     * @param string $v          Remove the volumes associated to the container. Default false
     * @param string $force      Kill then remove the container. Default false
     */
    public function containerDelete($container, $v = false, $force = false)
    {
        return $this->browser->delete($this->url('/containers/%s?v=%u&force=%u', $container, $v, $force))->then(array($this->parser, 'expectEmpty'));
    }

    public function containerResize($container, $w, $h)
    {
        return $this->browser->get($this->url('/containers/%s/resize?w=%u&h=%u', $container, $w, $h))->then(array($this->parser, 'expectEmpty'));
    }

    private function url($url)
    {
        $args = func_get_args();
        array_shift($args);

        return $this->url . vsprintf($url, $args);
    }
}
