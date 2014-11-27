<?php

use Clue\React\Buzz\Message\Response;
use Clue\React\Buzz\Message\Body;
use Clue\React\Docker\Client;
use React\Promise\Deferred;

class ClientTest extends TestCase
{
    private $browser;
    private $client;

    public function setUp()
    {
        $this->browser = $this->getMockBuilder('Clue\React\Buzz\Browser')->disableOriginalConstructor()->getMock();
        $this->client = new Client($this->browser);
    }

    public function testPing()
    {
        $this->browser->expects($this->once())->method('get')->with($this->equalTo('/_ping'))->will($this->returnResponse('OK'));

        $this->client->ping();
    }

    public function testContainerInspect()
    {
        $this->browser->expects($this->once())->method('get')->with($this->equalTo('/containers/123/json'))->will($this->returnResponse('{}'));

        $this->client->containerInspect(123);
    }

    public function testContainerTop()
    {
        $this->browser->expects($this->once())->method('get')->with($this->equalTo('/containers/123/top'))->will($this->returnResponse('{}'));

        $this->client->containerTop(123);
    }

    public function testContainerWait()
    {
        $this->browser->expects($this->once())->method('post')->with($this->equalTo('/containers/123/wait'))->will($this->returnResponse('{}'));

        $this->client->containerWait(123);
    }

    public function testContainerKill()
    {
        $this->browser->expects($this->once())->method('post')->with($this->equalTo('/containers/123/kill?signal='))->will($this->returnResponse());

        $this->client->containerKill(123);
    }

    public function testContainerKillSignalName()
    {
        $this->browser->expects($this->once())->method('post')->with($this->equalTo('/containers/123/kill?signal=SIGKILL'))->will($this->returnResponse());

        $this->client->containerKill(123, 'SIGKILL');
    }

    public function testContainerKillSignalNumber()
    {
        $this->browser->expects($this->once())->method('post')->with($this->equalTo('/containers/123/kill?signal=9'))->will($this->returnResponse());

        $this->client->containerKill(123, 9);
    }

    public function testContainerStop()
    {
        $this->browser->expects($this->once())->method('post')->with($this->equalTo('/containers/123/stop?t=10'))->will($this->returnResponse());

        $this->client->containerStop(123, 10);
    }

    public function testContainerRestart()
    {
        $this->browser->expects($this->once())->method('post')->with($this->equalTo('/containers/123/restart?t=10'))->will($this->returnResponse());

        $this->client->containerRestart(123, 10);
    }

    public function testContainerPause()
    {
        $this->browser->expects($this->once())->method('post')->with($this->equalTo('/containers/123/pause'))->will($this->returnResponse());

        $this->client->containerPause(123);
    }

    public function testContainerUnpause()
    {
        $this->browser->expects($this->once())->method('post')->with($this->equalTo('/containers/123/unpause'))->will($this->returnResponse());

        $this->client->containerUnpause(123);
    }

    public function testContainerDelete()
    {
        $this->browser->expects($this->once())->method('delete')->with($this->equalTo('/containers/123?v=0&force=0'))->will($this->returnResponse());

        $this->client->containerDelete(123, false, false);
    }

    public function testContainerResize()
    {
        $this->browser->expects($this->once())->method('get')->with($this->equalTo('/containers/123/resize?w=800&h=600'))->will($this->returnResponse());

        $this->client->containerResize(123, 800, 600);
    }

    private function returnResponse($body = '')
    {
        $deferred = new Deferred();
        $deferred->resolve(new Response('HTTP/1.0', 200, 'OK', null, new Body($body)));

        return $this->returnValue($deferred->promise());
    }
}
