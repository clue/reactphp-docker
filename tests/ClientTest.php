<?php

namespace Clue\Tests\React\Docker;

use Clue\React\Docker\Client;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Deferred;
use React\Stream\ThroughStream;
use RingCentral\Psr7\Response;

class ClientTest extends TestCase
{
    private $loop;
    private $browser;

    private $parser;
    private $streamingParser;
    private $client;

    public function setUp()
    {
        $this->loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $this->browser = $this->getMockBuilder('Clue\React\Buzz\Browser')->disableOriginalConstructor()->getMock();

        $this->parser = $this->getMockBuilder('Clue\React\Docker\Io\ResponseParser')->getMock();
        $this->streamingParser = $this->getMockBuilder('Clue\React\Docker\Io\StreamingParser')->getMock();

        $this->client = new Client($this->loop);

        $ref = new \ReflectionProperty($this->client, 'browser');
        $ref->setAccessible(true);
        $ref->setValue($this->client, $this->browser);

        $ref = new \ReflectionProperty($this->client, 'parser');
        $ref->setAccessible(true);
        $ref->setValue($this->client, $this->parser);

        $ref = new \ReflectionProperty($this->client, 'streamingParser');
        $ref->setAccessible(true);
        $ref->setValue($this->client, $this->streamingParser);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCtor()
    {
        new Client($this->loop);
    }

    public function testCtorWithExplicitUnixPath()
    {
        $client = new Client($this->loop, 'unix://docker.sock');

        $ref = new \ReflectionProperty($client, 'browser');
        $ref->setAccessible(true);
        $browser = $ref->getValue($client);

        $ref = new \ReflectionProperty($browser, 'baseUri');
        $ref->setAccessible(true);
        $url = $ref->getValue($browser);

        $this->assertEquals('http://localhost/', $url);
    }

    public function testCtorWithExplicitHttpUrl()
    {
        $client = new Client($this->loop, 'http://localhost:8001/');

        $ref = new \ReflectionProperty($client, 'browser');
        $ref->setAccessible(true);
        $browser = $ref->getValue($client);

        $ref = new \ReflectionProperty($browser, 'baseUri');
        $ref->setAccessible(true);
        $url = $ref->getValue($browser);

        $this->assertEquals('http://localhost:8001/', $url);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCtorWithInvalidUrlThrows()
    {
        new Client($this->loop, 'ftp://invalid');
    }

    public function testPing()
    {
        $body = 'OK';
        $this->expectRequestFlow('get', '/_ping', $this->createResponse($body), 'expectPlain');

        $this->expectPromiseResolveWith($body, $this->client->ping());
    }

    public function testEvents()
    {
        $json = array();
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('GET', '/events', $this->createResponseJsonStream($json));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('deferredStream')->with($this->equalTo($stream))->will($this->returnPromise($json));

        $this->expectPromiseResolveWith($json, $this->client->events());
    }

    public function testEventsArgs()
    {
        $json = array();
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('GET', '/events?since=10&until=20&filters=%7B%22image%22%3A%5B%22busybox%22%2C%22ubuntu%22%5D%7D', $this->createResponseJsonStream($json));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('deferredStream')->with($this->equalTo($stream))->will($this->returnPromise($json));

        $this->expectPromiseResolveWith($json, $this->client->events(10, 20, array('image' => array('busybox', 'ubuntu'))));
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

    public function testContainerLogsReturnsPendingPromiseWhenInspectingContainerIsPending()
    {
        $this->browser->expects($this->once())->method('get')->with('/containers/123/json')->willReturn(new \React\Promise\Promise(function () { }));

        $this->streamingParser->expects($this->once())->method('bufferedStream')->with($this->isInstanceOf('React\Stream\ReadableStreamInterface'))->willReturn(new \React\Promise\Promise(function () { }));

        $promise = $this->client->containerLogs('123');

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testContainerLogsRejectsWhenInspectingContainerRejects()
    {
        $this->browser->expects($this->once())->method('get')->with('/containers/123/json')->willReturn(\React\Promise\reject(new \RuntimeException()));

        $this->streamingParser->expects($this->once())->method('bufferedStream')->with($this->isInstanceOf('React\Stream\ReadableStreamInterface'))->willReturn(\React\Promise\reject(new \RuntimeException()));

        $promise = $this->client->containerLogs('123');

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());
    }

    public function testContainerLogsReturnsPendingPromiseWhenInspectingContainerResolvesWithTtyAndContainerLogsArePending()
    {
        $this->browser->expects($this->once())->method('withOptions')->willReturnSelf();
        $this->browser->expects($this->exactly(2))->method('get')->withConsecutive(
            array('/containers/123/json'),
            array('/containers/123/logs?stdout=1&stderr=1')
        )->willReturnOnConsecutiveCalls(
            \React\Promise\resolve(new Response(200, array(), '{"Config":{"Tty":true}}')),
            new \React\Promise\Promise(function () { })
        );

        $this->parser->expects($this->once())->method('expectJson')->willReturn(array('Config' => array('Tty' => true)));
        $this->streamingParser->expects($this->once())->method('bufferedStream')->with($this->isInstanceOf('React\Stream\ReadableStreamInterface'))->willReturn(new \React\Promise\Promise(function () { }));
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->willReturn($this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock());
        $this->streamingParser->expects($this->never())->method('demultiplexStream');

        $promise = $this->client->containerLogs('123');

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testContainerLogsReturnsPendingPromiseWhenInspectingContainerResolvesWithoutTtyAndContainerLogsArePending()
    {
        $this->browser->expects($this->once())->method('withOptions')->willReturnSelf();
        $this->browser->expects($this->exactly(2))->method('get')->withConsecutive(
            array('/containers/123/json'),
            array('/containers/123/logs?stdout=1&stderr=1')
        )->willReturnOnConsecutiveCalls(
            \React\Promise\resolve(new Response(200, array(), '{"Config":{"Tty":false}}')),
            new \React\Promise\Promise(function () { })
        );

        $this->parser->expects($this->once())->method('expectJson')->willReturn(array('Config' => array('Tty' => false)));
        $this->streamingParser->expects($this->once())->method('bufferedStream')->with($this->isInstanceOf('React\Stream\ReadableStreamInterface'))->willReturn(new \React\Promise\Promise(function () { }));
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->willReturn($this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock());
        $this->streamingParser->expects($this->once())->method('demultiplexStream')->willReturn($this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock());

        $promise = $this->client->containerLogs('123');

        $promise->then($this->expectCallableNever(), $this->expectCallableNever());
    }

    public function testContainerLogsResolvesWhenInspectingContainerResolvesWithTtyAndContainerLogsResolves()
    {
        $this->browser->expects($this->once())->method('withOptions')->willReturnSelf();
        $this->browser->expects($this->exactly(2))->method('get')->withConsecutive(
            array('/containers/123/json'),
            array('/containers/123/logs?stdout=1&stderr=1')
        )->willReturnOnConsecutiveCalls(
            \React\Promise\resolve(new Response(200, array(), '{"Config":{"Tty":true}}')),
            \React\Promise\resolve(new Response(200, array(), ''))
        );

        $this->parser->expects($this->once())->method('expectJson')->willReturn(array('Config' => array('Tty' => true)));
        $this->streamingParser->expects($this->once())->method('bufferedStream')->with($this->isInstanceOf('React\Stream\ReadableStreamInterface'))->willReturn(\React\Promise\resolve('output'));
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->willReturn($this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock());
        $this->streamingParser->expects($this->never())->method('demultiplexStream');

        $promise = $this->client->containerLogs('123');

        $promise->then($this->expectCallableOnceWith('output'), $this->expectCallableNever());
    }

    public function testContainerLogsStreamReturnStreamWhenInspectingContainerResolvesWithTtyAndContainerLogsResolves()
    {
        $this->browser->expects($this->once())->method('withOptions')->willReturnSelf();
        $this->browser->expects($this->exactly(2))->method('get')->withConsecutive(
            array('/containers/123/json'),
            array('/containers/123/logs?stdout=1&stderr=1')
        )->willReturnOnConsecutiveCalls(
            \React\Promise\resolve(new Response(200, array(), '{"Config":{"Tty":true}}')),
            \React\Promise\resolve(new Response(200, array(), ''))
        );

        $response = new ThroughStream();
        $this->parser->expects($this->once())->method('expectJson')->willReturn(array('Config' => array('Tty' => true)));
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->willReturn($response);
        $this->streamingParser->expects($this->never())->method('demultiplexStream');

        $stream = $this->client->containerLogsStream('123');

        $stream->on('data', $this->expectCallableOnceWith('output'));
        $response->write('output');
    }

    public function testContainerExport()
    {
        $data = 'tar stream';
        $this->expectRequestFlow('get', '/containers/123/export', $this->createResponse($data), 'expectPlain');

        $this->expectPromiseResolveWith($data, $this->client->containerExport(123));
    }

    public function testContainerExportStream()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

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
        $this->expectRequestFlow('post', '/containers/123/stop', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerStop(123));
    }

    public function testContainerStopTimeout()
    {
        $this->expectRequestFlow('post', '/containers/123/stop?t=10', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerStop(123, 10));
    }

    public function testContainerRestart()
    {
        $this->expectRequestFlow('post', '/containers/123/restart', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerRestart(123));
    }

    public function testContainerRestartTimeout()
    {
        $this->expectRequestFlow('post', '/containers/123/restart?t=10', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerRestart(123, 10));
    }

    public function testContainerRename()
    {
        $this->expectRequestFlow('POST', '/containers/123/rename?name=newname', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerRename(123, 'newname'));
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

    public function testContainerStats()
    {
        $this->expectRequestFlow('GET', '/containers/123/stats?stream=0', $this->createResponse(), 'expectJson');

        $this->expectPromiseResolveWith('', $this->client->containerStats(123));
    }

    public function testContainerStatsStream()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('GET', '/containers/123/stats', $this->createResponse(''));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));

        $this->assertSame($stream, $this->client->containerStatsStream('123'));
    }

    public function testContainerResize()
    {
        $this->expectRequestFlow('POST', '/containers/123/resize?w=800&h=600', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->containerResize(123, 800, 600));
    }

    public function testContainerArchive()
    {
        $data = 'tar stream';
        $this->expectRequestFlow('GET', '/containers/123/archive?path=file.txt', $this->createResponse($data), 'expectPlain');

        $this->expectPromiseResolveWith($data, $this->client->containerArchive('123', 'file.txt'));
    }

    public function testContainerArchiveStream()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('GET', '/containers/123/archive?path=file.txt', $this->createResponse(''));
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->will($this->returnValue($stream));

        $this->assertSame($stream, $this->client->containerArchiveStream('123', 'file.txt'));
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
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('post', '/images/create?fromImage=busybox', $this->createResponseJsonStream($json));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('deferredStream')->with($this->equalTo($stream))->will($this->returnPromise($json));

        $this->expectPromiseResolveWith($json, $this->client->imageCreate('busybox'));
    }

    public function testImageCreateStream()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

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
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('post', '/images/123/push', $this->createResponseJsonStream($json));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('deferredStream')->with($this->equalTo($stream))->will($this->returnPromise($json));

        $this->expectPromiseResolveWith($json, $this->client->imagePush('123'));
    }

    public function testImagePushStream()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('post', '/images/123/push', $this->createResponseJsonStream(array()));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));

        $this->assertSame($stream, $this->client->imagePushStream('123'));
    }

    public function testImagePushCustomRegistry()
    {
        // TODO: verify headers
        $auth = array();
        $json = array();
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('post', '/images/demo.acme.com%3A5000/123/push?tag=test', $this->createResponseJsonStream($json));
        $this->streamingParser->expects($this->once())->method('parseJsonStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('deferredStream')->with($this->equalTo($stream))->will($this->returnPromise($json));

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
        $this->expectRequestFlow('post', '/containers/123/exec', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->execCreate(123, array('env')));
    }

    public function testExecCreateStringCommand()
    {
        $json = array();
        $this->expectRequestFlow('post', '/containers/123/exec', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->execCreate(123, 'env'));
    }

    public function testExecDetached()
    {
        $body = '';
        $this->expectRequestFlow('POST', '/exec/123/start', $this->createResponse($body), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->execStartDetached(123, true));
    }

    public function testExecStart()
    {
        $data = 'hello world';
        $config = array();
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('POST', '/exec/123/start', $this->createResponse($data));
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('demultiplexStream')->with($stream)->willReturn($stream);
        $this->streamingParser->expects($this->once())->method('bufferedStream')->with($this->equalTo($stream))->willReturn(\React\Promise\resolve($data));

        $this->expectPromiseResolveWith($data, $this->client->execStart(123, $config));
    }

    public function testExecStartStreamWithoutTtyWillDemultiplex()
    {
        $config = array();
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('POST', '/exec/123/start', $this->createResponse());
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('demultiplexStream')->with($stream)->willReturn($stream);

        $this->assertSame($stream, $this->client->execStartStream(123, $config));
    }

    public function testExecStartStreamWithTtyWillNotDemultiplex()
    {
        $config = array('Tty' => true);
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('POST', '/exec/123/start', $this->createResponse());
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->never())->method('demultiplexStream');

        $this->assertSame($stream, $this->client->execStartStream(123, $config));
    }

    public function testExecStartStreamWithCustomStderrEvent()
    {
        $config = array();
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();

        $this->expectRequest('POST', '/exec/123/start', $this->createResponse());
        $this->streamingParser->expects($this->once())->method('parsePlainStream')->will($this->returnValue($stream));
        $this->streamingParser->expects($this->once())->method('demultiplexStream')->with($stream, 'stderr')->willReturn($stream);

        $this->assertSame($stream, $this->client->execStartStream(123, $config, 'stderr'));
    }

    public function testExecResize()
    {
        $this->expectRequestFlow('POST', '/exec/123/resize?w=800&h=600', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->execResize(123, 800, 600));
    }

    public function testExecInspect()
    {
        $json = array();
        $this->expectRequestFlow('get', '/exec/123/json', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->execInspect(123));
    }

    public function testNetworkList()
    {
        $json = array();
        $this->expectRequestFlow('get', '/networks', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->networkList());
    }

    public function testNetworkInspect()
    {
        $json = array();
        $this->expectRequestFlow('get', '/networks/123', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->networkInspect(123));
    }

    public function testNetworkRemove()
    {
        $json = array();
        $this->expectRequestFlow('delete', '/networks/123', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->networkRemove(123));
    }

    public function testNetworkCreate()
    {
        $json = array();
        $config = array();
        $this->expectRequestFlow('post', '/networks/create', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->networkCreate($config));
    }

    public function testNetworkConnect()
    {
        $json = array();
        $config = array();
        $this->expectRequestFlow('post', '/networks/123/connect', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->networkConnect(123, $config));
    }

    public function testNetworkDisconnect()
    {
        $json = array();
        $this->expectRequestFlow('post', '/networks/123/disconnect', $this->createResponse(), 'expectEmpty');

        $this->expectPromiseResolveWith('', $this->client->networkDisconnect(123, 'abc'));
    }

    public function testNetworkPrune()
    {
        $json = array();
        $this->expectRequestFlow('post', '/networks/prune', $this->createResponseJson($json), 'expectJson');

        $this->expectPromiseResolveWith($json, $this->client->networkPrune());
    }

    private function expectRequestFlow($method, $url, ResponseInterface $response, $parser)
    {
        $return = (string)$response->getBody();
        if ($parser === 'expectJson') {
            $return = json_decode($return, true);
        }

        $this->expectRequest($method, $url, $response);
        $this->parser->expects($this->once())->method($parser)->with($this->equalTo($response))->will($this->returnValue($return));
    }

    private function expectRequest($method, $url, ResponseInterface $response)
    {
        $this->browser->expects($this->any())->method('withOptions')->willReturnSelf();
        $this->browser->expects($this->once())->method(strtolower($method))->with($url)->willReturn(\React\Promise\resolve($response));
    }

    private function createResponse($body = '')
    {
        return new Response(200, array(), $body);
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
