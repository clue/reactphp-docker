<?php

use Clue\React\Docker\Io\StreamingParser;
use React\Promise\Deferred;
use React\Stream\ReadableStream;
use React\Promise\CancellablePromiseInterface;
use React\Promise;

class StreamingParserTest extends TestCase
{
    private $parser;

    public function setUp()
    {
        $this->parser = new StreamingParser();
    }

    public function testJsonPassingRejectedPromiseResolvesWithClosedStream()
    {
        $stream = $this->parser->parseJsonStream(Promise\reject());

        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $stream);
        $this->assertFalse($stream->isReadable());
    }

    public function testJsonRejectingPromiseWillEmitErrorAndCloseEvent()
    {
        $deferred = new Deferred();

        $stream = $this->parser->parseJsonStream($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $exception = new RuntimeException();

        $stream->on('error', $this->expectCallableOnceWith($exception));
        $stream->on('close', $this->expectCallableOnce());

        $deferred->reject($exception);

        $this->assertFalse($stream->isReadable());
    }

    public function testJsonResolvingPromiseWillEmitCloseEvent()
    {
        $deferred = new Deferred();

        $stream = $this->parser->parseJsonStream($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $stream->on('error', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());

        $deferred->resolve('data');

        $this->assertFalse($stream->isReadable());
    }

    public function testPlainPassingRejectedPromiseResolvesWithClosedStream()
    {
        $stream = $this->parser->parsePlainStream(Promise\reject());

        $this->assertInstanceOf('React\Stream\ReadableStreamInterface', $stream);
        $this->assertFalse($stream->isReadable());
    }

    public function testDeferredClosedStreamWillReject()
    {
        $stream = $this->getMock('React\Stream\ReadableStreamInterface');
        $stream->expects($this->once())->method('isReadable')->will($this->returnValue(false));

        $promise = $this->parser->deferredStream($stream, 'anything');
        $this->expectPromiseReject($promise);
    }

    public function testDeferredStreamEventsWillBeEmittedAndBuffered()
    {
        $stream = new ReadableStream();

        $promise = $this->parser->deferredStream($stream, 'demo');

        $stream->emit('ignored', array('ignored'));
        $stream->emit('demo', array('a'));
        $stream->emit('demo', array('b'));

        $stream->close();

        $this->expectPromiseResolveWith(array('a', 'b'), $promise);
    }

    public function testDeferredStreamErrorEventWillRejectPromise()
    {
        $stream = new ReadableStream();

        $promise = $this->parser->deferredStream($stream, 'demo');

        $stream->emit('ignored', array('ignored'));

        $stream->emit('demo', array('a'));

        $stream->emit('error', array('value', 'ignord'));

        $stream->close();

        $this->expectPromiseReject($promise);
        $promise->then(null, $this->expectCallableOnceWith('value'));
    }

    public function testDeferredCancelingPromiseWillCloseStream()
    {
        $this->markTestIncomplete();

        $stream = $this->getMock('React\Stream\ReadableStreamInterface');

        $promise = $this->parser->deferredStream($stream, 'anything');
        if (!($promise instanceof CancellablePromiseInterface)) {
            $this->markTestSkipped('Requires Promise v2 API and has no effect on v1 API');
        }

        $stream->expects($this->once())->method('close');
        $promise->cancel();
    }
}
