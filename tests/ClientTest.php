<?php

use Clue\React\Buzz\Message\Response;
use Clue\React\Buzz\Message\Body;
use Clue\React\Docker\Client;
use React\Promise\Deferred;
use Clue\React\Buzz\Browser;

class ClientTest extends TestCase
{
    private $loop;
    private $sender;
    private $browser;

    private $parser;
    private $streamingParser;
    private $client;

    public function setUp()
    {
        $this->loop = $this->getMock('React\EventLoop\LoopInterface');
        $this->sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $this->browser = new Browser($this->loop, $this->sender);
        $this->browser = $this->browser->withBase('http://x/');

        $this->parser = $this->getMock('Clue\React\Docker\Io\ResponseParser');
        $this->streamingParser = $this->getMock('Clue\React\Docker\Io\StreamingParser');
        $this->client = new Client($this->browser, $this->parser, $this->streamingParser);
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
        $this->expectRequestFlow('post', '/containers/create', $this->createResponseJson($json), 'expectJson');

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

    public function testContainerTopArgs()
    {
        $json = array();
        $this->expectRequestFlow('get', '/containers/123/top?ps_args=aux', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->containerTop(123, 'aux'));
    }

    public function testContainerChanges()
    {
        $json = array();
        $this->expectRequestFlow('get', '/containers/123/changes', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->containerChanges(123));
    }

    public function testContainerExport()
    {
        $data = 'tar stream';
        $this->expectRequestFlow('get', '/containers/123/export', $this->createResponse($data), 'expectPlain');

        $this->expectPromiseResolveWith($data, $this->client->containerExport(123));
    }

    public function testContainerExportStream()
    {
        $stream = $this->getMock('React\Stream\ReadableStreamInterface');

        $this->expectRequest('get', '/containers/123/export', $this->createResponse(''));
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->will($this->returnValue($stream));

        $this->assertSame($stream, $this->client->containerExportStream(123));
    }

    public function testContainerWait()
    {
        $json = array();
        $this->expectRequestFlow('post', '/containers/123/wait', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->containerWait(123));
    }

    public function testContainerKill()
    {
        $this->expectRequestFlow('post', '/containers/123/kill', $this->createResponse(), 'expectEmpty');

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
        $this->expectRequestFlow('delete', '/containers/123', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerRemove(123, false, false));
    }

    public function testContainerRemoveVolumeForce()
    {
        $this->expectRequestFlow('delete', '/containers/123?v=1&force=1', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerRemove(123, true, true));
    }

    public function testContainerResize()
    {
        $this->expectRequestFlow('get', '/containers/123/resize?w=800&h=600', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerResize(123, 800, 600));
    }

    public function testContainerCopy()
    {
        $data = 'tar stream';
        $this->expectRequestFlow('post', '/containers/123/copy', $this->createResponse($data), 'expectPlain');

        $config = array('Resource' => 'file.txt');
        $this->expectPromiseResolveWith($data, $this->client->containerCopy('123', $config));
    }

    public function testContainerCopyStream()
    {
        $stream = $this->getMock('React\Stream\ReadableStreamInterface');

        $this->expectRequest('post', '/containers/123/copy', $this->createResponse(''));
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->will($this->returnValue($stream));

        $config = array('Resource' => 'file.txt');
        $this->assertSame($stream, $this->client->containerCopyStream('123', $config));
    }

    public function testImageList()
    {
        $json = array();
        $this->expectRequestFlow('get', '/images/json', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->imageList());
    }

    public function testImageListAll()
    {
        $json = array();
        $this->expectRequestFlow('get', '/images/json?all=1', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->imageList(true));
    }

    public function testImageCreate()
    {
        $json = array();
        $stream = $this->getMock('React\Stream\ReadableStreamInterface');

        $this->expectRequest('post', '/images/create?fromImage=busybox', $this->createResponseJsonStream($json));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('deferredStream')->with($this->equalTo($stream), $this->equalTo('progress'))->will($this->returnPromise($json));

        $this->expectPromiseResolveWith($json, $this->client->imageCreate('busybox'));
    }

    public function testImageCreateStream()
    {
        $stream = $this->getMock('React\Stream\ReadableStreamInterface');

        $this->expectRequest('post', '/images/create?fromImage=busybox', $this->createResponseJsonStream(array()));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));

        $this->assertSame($stream, $this->client->imageCreateStream('busybox'));
    }

    public function testImageInspect()
    {
        $json = array();
        $this->expectRequestFlow('get', '/images/123/json', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->imageInspect('123'));
    }

    public function testImageHistory()
    {
        $json = array();
        $this->expectRequestFlow('get', '/images/123/history', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->imageHistory('123'));
    }

    public function testImagePush()
    {
        $json = array();
        $stream = $this->getMock('React\Stream\ReadableStreamInterface');

        $this->expectRequest('post', '/images/123/push', $this->createResponseJsonStream($json));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('deferredStream')->with($this->equalTo($stream), $this->equalTo('progress'))->will($this->returnPromise($json));

        $this->expectPromiseResolveWith($json, $this->client->imagePush('123'));
    }

    public function testImagePushStream()
    {
        $stream = $this->getMock('React\Stream\ReadableStreamInterface');

        $this->expectRequest('post', '/images/123/push', $this->createResponseJsonStream(array()));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));

        $this->assertSame($stream, $this->client->imagePushStream('123'));
    }

    public function testImagePushCustomRegistry()
    {
        // TODO: verify headers
        $auth = array();
        $json = array();
        $stream = $this->getMock('React\Stream\ReadableStreamInterface');

        $this->expectRequest('post', '/images/demo.acme.com:5000/123/push?tag=test', $this->createResponseJsonStream($json));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('deferredStream')->with($this->equalTo($stream), $this->equalTo('progress'))->will($this->returnPromise($json));

        $this->expectPromiseResolveWith($json, $this->client->imagePush('123', 'test', 'demo.acme.com:5000', $auth));
    }

    public function testImageTag()
    {
        $this->expectRequestFlow('post', '/images/123/tag?repo=test', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->imageTag('123', 'test'));
    }

    public function testImageTagNameForce()
    {
        $this->expectRequestFlow('post', '/images/123/tag?repo=test&tag=tag&force=1', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->imageTag('123', 'test', 'tag', true));
    }

    public function testImageRemove()
    {
        $this->expectRequestFlow('delete', '/images/123', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->imageRemove('123'));
    }

    public function testImageRemoveForceNoprune()
    {
        $this->expectRequestFlow('delete', '/images/123?force=1&noprune=1', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->imageRemove('123', true, true));
    }

    public function testImageSearch()
    {
        $json = array();
        $this->expectRequestFlow('get', '/images/search?term=clue', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->imageSearch('clue'));
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

        $this->expectRequest($method, $url, $response);
        $this->parser->expects($this->once())->method($parser)->with($this->equalTo($response))->will($this->returnValue($return));
    }

    private function expectRequest($method, $url, Response $response)
    {
        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function ($request) use ($that, $method, $url) {
            $that->assertEquals(strtoupper($method), $request->getMethod());
            $that->assertEquals('http://x' . $url, (string)$request->getUri());

            return true;
        }))->will($this->returnPromise($response));
    }

    private function createResponse($body = '')
    {
        return new Response('HTTP/1.0', 200, 'OK', array(), new Body($body));
    }

    private function createResponseJson($json)
    {
        return $this->createResponse(json_encode($json));
    }

    private function createResponseJsonStream($json)
    {
        return $this->createResponse(implode('', array_map('json_encode', $json)));
    }

    private function returnPromise($for)
    {
        $deferred = new Deferred();
        $deferred->resolve($for);

        return $this->returnValue($deferred->promise());
    }
}
