<?php

namespace Clue\React\Docker;

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Response;

/**
 *
 * @link https://docs.docker.com/reference/api/docker_remote_api_v1.15/#attach-to-a-container
 */
class Client
{
    private $browser;

    public function __construct(Browser $browser)
    {
        $this->browser = $browser;
    }

    public function ping()
    {
        return $this->browser->get('/_ping')->then(array($this, 'expectPlain'));
    }

    public function info()
    {
        return $this->browser->get('/info')->then(array($this, 'expectJson'));
    }

    public function version()
    {
        return $this->browser->get('/version')->then(array($this, 'expectJson'));
    }

    public function containerList($all = false, $size = false)
    {
        return $this->browser->get('/containers/json?all=' . $all . '&size=' . $size)->then(array($this, 'expectJson'));
    }

    public function containerInspect($container)
    {
        return $this->browser->get('/containers/' . $container . '/json')->then(array($this, 'expectJson'));
    }

    public function containerTop($container)
    {
        return $this->browser->get('/containers/' . $container . '/top')->then(array($this, 'expectJson'));
    }

    public function containerWait($container)
    {
        return $this->browser->post('/containers/' . $container . '/wait')->then(array($this, 'expectJson'));
    }

    /**
     *
     *
     * @param string $container
     * @param int    $t         number of seconds to wait before killing the container
     */
    public function containerStop($container, $t)
    {
        return $this->browser->post('/containers/' . $container . '/stop?t=' . $t)->then(array($this, 'expectEmpty'));
    }

    /**
     *
     *
     * @param string $container
     * @param int    $t         number of seconds to wait before killing the container
     */
    public function containerRestart($container, $t)
    {
        return $this->browser->post('/containers/' . $container . '/restart?t=' . $t)->then(array($this, 'expectEmpty'));
    }

    public function containerKill($container, $signal = null)
    {
        return $this->browser->post('/containers/' . $container . '/kill?signal=' . $signal)->then(array($this, 'expectEmpty'));
    }

    public function containerPause($container)
    {
        return $this->browser->post('/containers/' . $container . '/pause')->then(array($this, 'expectEmpty'));
    }

    public function containerUnpause($container)
    {
        return $this->browser->post('/containers/' . $container . '/unpause')->then(array($this, 'expectEmpty'));
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
        return $this->browser->delete('/containers/' . $container . '?v=' . (int)$v . '&force=' . (int)$force)->then(array($this, 'expectEmpty'));
    }

    public function containerResize($container, $w, $h)
    {
        return $this->browser->get('/containers/' . $container . '/resize?w=' . $w . '&h=' . $h)->then(array($this, 'expectEmpty'));
    }

    public function expectPlain(Response $response)
    {
        // text/plain

        return (string)$response->getBody();
    }

    public function expectJson(Response $response)
    {
        // application/json

        return json_decode((string)$response->getBody(), true);
    }

    public function expectEmpty(Response $response)
    {
        // 204 No Content
        // no content-type

        return $this->expectPlain($response);
    }
}
