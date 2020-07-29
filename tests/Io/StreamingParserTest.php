<?php

namespace Clue\Tests\React\Docker\Io;

use Clue\React\Docker\Io\StreamingParser;
use Clue\Tests\React\Docker\TestCase;
use React\Promise;
use React\Promise\CancellablePromiseInterface;
use React\Promise\Deferred;
use React\Stream\ThroughStream;

class StreamingParserTest extends TestCase
{
    private $parser;

    /**
     * @before
     */
    public function setUpParser()
    {
        $this->parser = new StreamingParser();
    }

    public function testJsonPassingRejectedPromiseResolvesWithClosedStream()
    {
        $stream = $this->parser->parseJsonStream(Promise\reject(new \RuntimeException()));

        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $stream);
        $this->assertFalse($stream->isReadable());
    }

    public function testJsonRejectingPromiseWillEmitErrorAndCloseEvent()
    {
        $deferred = new Deferred();

        $stream = $this->parser->parseJsonStream($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $exception = new \RuntimeException();

        $stream->on('error', $this->expectCallableOnceWith($exception));
        $stream->on('close', $this->expectCallableOnce());

        $deferred->reject($exception);

        $this->assertFalse($stream->isReadable());
    }

    public function testJsonResolvingPromiseWithWrongValueWillEmitErrorAndCloseEvent()
    {
        $deferred = new Deferred();

        $stream = $this->parser->parseJsonStream($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());

        $deferred->resolve('not a stream');

        $this->assertFalse($stream->isReadable());
    }

    public function testPlainPassingRejectedPromiseResolvesWithClosedStream()
    {
        $stream = $this->parser->parsePlainStream(Promise\reject(new \RuntimeException()));

        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $stream);
        $this->assertFalse($stream->isReadable());
    }

    public function testDeferredClosedStreamWillReject()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $stream->expects($this->once())->method('isReadable')->will($this->returnValue(false));

        $promise = $this->parser->deferredStream($stream);
        $this->expectPromiseReject($promise);
    }

    public function testDeferredStreamEventsWillBeEmittedAndBuffered()
    {
        $stream = new ThroughStream();

        $promise = $this->parser->deferredStream($stream);

        $stream->emit('ignored', array('ignored'));
        $stream->emit('data', array('a'));
        $stream->emit('data', array('b'));

        $stream->close();

        $this->expectPromiseResolveWith(array('a', 'b'), $promise);
    }

    public function testDeferredStreamErrorEventWillRejectPromise()
    {
        $stream = new ThroughStream();

        $promise = $this->parser->deferredStream($stream);

        $stream->emit('ignored', array('ignored'));

        $stream->emit('data', array('a'));

        $stream->emit('error', array(new \RuntimeException()));

        $stream->close();

        $this->expectPromiseReject($promise);
        $promise->then(null, $this->expectCallableOnceWith($this->isInstanceOf('RuntimeException')));
    }

    public function testDeferredCancelingPromiseWillCloseStream()
    {
        $stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $stream->expects($this->once())->method('isReadable')->willReturn(true);

        $promise = $this->parser->deferredStream($stream);
        if (!($promise instanceof CancellablePromiseInterface)) {
            $this->markTestSkipped('Requires Promise v2 API and has no effect on v1 API');
        }

        $stream->expects($this->once())->method('close');
        $promise->cancel();
    }

    public function testDemultiplexStreamWillReturnReadable()
    {
        $stream = new ThroughStream();

        $out = $this->parser->demultiplexStream($stream);

        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $out);
    }
}
