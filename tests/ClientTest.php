<?php

use Clue\React\Buzz\Message\Response;
use Clue\React\Buzz\Message\Body;
use Clue\React\Docker\Client;
use React\Promise\Deferred;

class ClientTest extends TestCase
{
    private $browser;
    private $parser;
    private $client;

    public function setUp()
    {
        $this->browser = $this->getMockBuilder('Clue\React\Buzz\Browser')->disableOriginalConstructor()->getMock();
        $this->parser = $this->getMock('Clue\React\Docker\Io\ResponseParser');
        $this->client = new Client($this->browser, '', $this->parser);
    }

    public function testPing()
    {
        $body = 'OK';
        $this->expectRequestFlow('get', '/_ping', $this->createResponse($body), 'expectPlain');

        $this->expectPromiseResolveWith($body, $this->client->ping());
    }

    public function testContainerCreate()
    {
        $json = array();
        $config = array();
        $this->expectRequestFlow('post', '/containers/create?name=', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->containerCreate($config));
    }

    public function testContainerCreateName()
    {
        $json = array();
        $config = array();
        $this->expectRequestFlow('post', '/containers/create?name=demo', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->containerCreate($config, 'demo'));
    }

    public function testContainerInspect()
    {
        $json = array();
        $this->expectRequestFlow('get', '/containers/123/json', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->containerInspect(123));
    }

    public function testContainerTop()
    {
        $json = array();
        $this->expectRequestFlow('get', '/containers/123/top', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->containerTop(123));
    }

    public function testContainerWait()
    {
        $json = array();
        $this->expectRequestFlow('post', '/containers/123/wait', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->containerWait(123));
    }

    public function testContainerKill()
    {
        $this->expectRequestFlow('post', '/containers/123/kill?signal=', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerKill(123));
    }

    public function testContainerKillSignalName()
    {
        $this->expectRequestFlow('post', '/containers/123/kill?signal=SIGKILL', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerKill(123, 'SIGKILL'));
    }

    public function testContainerKillSignalNumber()
    {
        $this->expectRequestFlow('post', '/containers/123/kill?signal=9', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerKill(123, 9));
    }

    public function testContainerStart()
    {
        $config = array();
        $this->expectRequestFlow('post', '/containers/123/start', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerStart(123, $config));
    }

    public function testContainerStop()
    {
        $this->expectRequestFlow('post', '/containers/123/stop?t=10', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerStop(123, 10));
    }

    public function testContainerRestart()
    {
        $this->expectRequestFlow('post', '/containers/123/restart?t=10', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerRestart(123, 10));
    }

    public function testContainerPause()
    {
        $this->expectRequestFlow('post', '/containers/123/pause', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerPause(123));
    }

    public function testContainerUnpause()
    {
        $this->expectRequestFlow('post', '/containers/123/unpause', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerUnpause(123));
    }

    public function testContainerRemove()
    {
        $this->expectRequestFlow('delete', '/containers/123?v=0&force=0', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerRemove(123, false, false));
    }

    public function testContainerResize()
    {
        $this->expectRequestFlow('get', '/containers/123/resize?w=800&h=600', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerResize(123, 800, 600));
    }

    public function testExecCreate()
    {
        $json = array();
        $config = array();
        $this->expectRequestFlow('post', '/containers/123/exec', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->execCreate(123, $config));
    }

    public function testExecStart()
    {
        $json = array();
        $config = array();
        $this->expectRequestFlow('post', '/exec/123/start', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->execStart(123, $config));
    }

    public function testExecResize()
    {
        $this->expectRequestFlow('get', '/exec/123/resize?w=800&h=600', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->execResize(123, 800, 600));
    }

    private function expectRequestFlow($method, $url, Response $response, $parser)
    {
        $return = (string)$response->getBody();
        if ($parser === 'expectJson') {
            $return = json_decode($return, true);
        }

        $this->browser->expects($this->once())->method($method)->with($this->equalTo($url))->will($this->returnPromise($response));
        $this->parser->expects($this->once())->method($parser)->with($this->equalTo($response))->will($this->returnValue($return));
    }

    private function createResponse($body = '')
    {
        return new Response('HTTP/1.0', 200, 'OK', null, new Body($body));
    }

    private function createResponseJson($json)
    {
        return $this->createResponse(json_encode($json));
    }

    private function returnPromise($for)
    {
        $deferred = new Deferred();
        $deferred->resolve($for);

        return $this->returnValue($deferred->promise());
    }
}
