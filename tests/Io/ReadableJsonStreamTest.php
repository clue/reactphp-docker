<?php

namespace Clue\Tests\React\Docker\Io;

use Clue\React\Docker\Io\ReadableJsonStream;
use Clue\Tests\React\Docker\TestCase;
use React\Stream\ThroughStream;

class ReadableJsonStreamTest extends TestCase
{
    private $stream;
    private $parser;

    public function setUp()
    {
        $this->stream = new ThroughStream();
        $this->parser = new ReadableJsonStream($this->stream);
    }

    public function testStreamWillForwardEndAndClose()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableOnce());

        $this->stream->emit('end');

        $this->assertFalse($this->parser->isReadable());
    }

    public function testStreamWillForwardErrorAndClose()
    {
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());

        $this->stream->emit('error', array(new \RuntimeException('Test')));

        $this->assertFalse($this->parser->isReadable());
    }

    public function testStreamWillEmitErrorWhenEndingWithinStream()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('close', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());

        $this->stream->emit('data', array('['));
        $this->stream->emit('end');

        $this->assertFalse($this->parser->isReadable());
    }

    public function testStreamWillEmitDataOnCompleteArray()
    {
        $this->parser->on('data', $this->expectCallableOnceWith(array(1, 2)));

        $this->stream->emit('data', array("[1,2]"));
    }

    public function testStreamWillEmitErrorOnCompleteErrorObject()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->stream->emit('data', array("{\"error\":\"message\"}"));
    }

    public function testStreamWillEmitErrorOnInvalidData()
    {
        $this->parser->on('data', $this->expectCallableNever());
        $this->parser->on('error', $this->expectCallableOnce());
        $this->parser->on('end', $this->expectCallableNever());
        $this->parser->on('close', $this->expectCallableOnce());

        $this->stream->emit('data', array("oops"));
    }

    public function testStreamWillNotEmitDataOnIncompleteArray()
    {
        $this->parser->on('data', $this->expectCallableNever());

        $this->stream->emit('data', array("[1,2"));
    }

    public function testStreamWillEmitDataOnCompleteArrayChunked()
    {
        $this->parser->on('data', $this->expectCallableOnceWith(array(1,2)));

        $this->stream->emit('data', array("[1,"));
        $this->stream->emit('data', array("2]"));
    }

    public function testStreamWillEmitDataTwiceOnOneChunkWithTwoCompleteArrays()
    {
        $mock = $this->createCallableMock();
        $mock->expects($this->exactly(2))->method('__invoke');

        $this->parser->on('data',$mock);

        $this->stream->emit('data', array("[1][2]"));
    }

    public function testCloseFromDataEventWillStopEmittingFurtherDataEvents()
    {
        $parser = $this->parser;
        $this->parser->on('data', function () use ($parser) {
            $parser->close();
        });

        $this->parser->on('data', $this->expectCallableOnceWith(array(1)));

        $this->stream->emit('data', array("[1][2]"));
    }

    public function testCloseTwiceWillEmitCloseOnceAndRemoveAllListeners()
    {
        $this->parser->on('close', $this->expectCallableOnce());

        $this->parser->close();
        $this->parser->close();

        $this->assertEquals(array(), $this->parser->listeners('close'));
    }

    public function testPipeWillBeForwardedToTargetStream()
    {
        $target = new ThroughStream();
        $target->on('pipe', $this->expectCallableOnceWith($this->parser));

        $this->parser->pipe($target);
    }

    public function testPauseWillBeForwarded()
    {
        $this->stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->stream->expects($this->once())->method('pause');
        $this->parser = new ReadableJsonStream($this->stream);

        $this->parser->pause();
    }

    public function testResumeWillBeForwarded()
    {
        $this->stream = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $this->stream->expects($this->once())->method('resume');
        $this->parser = new ReadableJsonStream($this->stream);

        $this->parser->resume();
    }
}
