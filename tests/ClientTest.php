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
        $this->client = new Client($this->browser, $this->parser);
    }

    public function testPing()
    {
        $this->expectRequestFlow('get', '/_ping', $this->createResponse('OK'), 'expectPlain');

        $this->client->ping();
    }

    public function testContainerInspect()
    {
        $this->expectRequestFlow('get', '/containers/123/json', $this->createResponse('{}'), 'expectJson');

        $this->client->containerInspect(123);
    }

    public function testContainerTop()
    {
        $this->expectRequestFlow('get', '/containers/123/top', $this->createResponse('{}'), 'expectJson');

        $this->client->containerTop(123);
    }

    public function testContainerWait()
    {
        $this->expectRequestFlow('post', '/containers/123/wait', $this->createResponse('{}'), 'expectJson');

        $this->client->containerWait(123);
    }

    public function testContainerKill()
    {
        $this->expectRequestFlow('post', '/containers/123/kill?signal=', $this->createResponse(), 'expectEmpty');

        $this->client->containerKill(123);
    }

    public function testContainerKillSignalName()
    {
        $this->expectRequestFlow('post', '/containers/123/kill?signal=SIGKILL', $this->createResponse(), 'expectEmpty');

        $this->client->containerKill(123, 'SIGKILL');
    }

    public function testContainerKillSignalNumber()
    {
        $this->expectRequestFlow('post', '/containers/123/kill?signal=9', $this->createResponse(), 'expectEmpty');

        $this->client->containerKill(123, 9);
    }

    public function testContainerStop()
    {
        $this->expectRequestFlow('post', '/containers/123/stop?t=10', $this->createResponse(), 'expectEmpty');

        $this->client->containerStop(123, 10);
    }

    public function testContainerRestart()
    {
        $this->expectRequestFlow('post', '/containers/123/restart?t=10', $this->createResponse(), 'expectEmpty');

        $this->client->containerRestart(123, 10);
    }

    public function testContainerPause()
    {
        $this->expectRequestFlow('post', '/containers/123/pause', $this->createResponse(), 'expectEmpty');

        $this->client->containerPause(123);
    }

    public function testContainerUnpause()
    {
        $this->expectRequestFlow('post', '/containers/123/unpause', $this->createResponse(), 'expectEmpty');

        $this->client->containerUnpause(123);
    }

    public function testContainerDelete()
    {
        $this->expectRequestFlow('delete', '/containers/123?v=0&force=0', $this->createResponse(), 'expectEmpty');

        $this->client->containerDelete(123, false, false);
    }

    public function testContainerResize()
    {
        $this->expectRequestFlow('get', '/containers/123/resize?w=800&h=600', $this->createResponse(), 'expectEmpty');

        $this->client->containerResize(123, 800, 600);
    }

    private function expectRequestFlow($method, $url, Response $response, $parser)
    {
        $this->browser->expects($this->once())->method($method)->with($this->equalTo($url))->will($this->returnPromise($response));
        $this->parser->expects($this->once())->method($parser)->with($this->equalTo($response));
    }

    private function createResponse($body = '')
    {
        return new Response('HTTP/1.0', 200, 'OK', null, new Body($body));
    }

    private function returnPromise($for)
    {
        $deferred = new Deferred();
        $deferred->resolve($for);

        return $this->returnValue($deferred->promise());
    }
}
