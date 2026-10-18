<?php

namespace Clue\Tests\React\Docker\Io;

use Clue\React\Docker\Io\ReadableUnwrapStream;
use Clue\Tests\React\Docker\TestCase;
use React\Promise;
use React\Promise\Deferred;
use React\Stream\ThroughStream;

class ReadableUnwrapStreamTest extends TestCase
{
    public function testReturnsReadableStreamForPromise()
    {
        $promise = new Promise\Promise(function () { });
        $stream = new ReadableUnwrapStream($promise);

        $this->assertTrue($stream->isReadable());
    }

    public function testClosingStreamMakesItNotReadable()
    {
        $promise = new Promise\Promise(function () { });
        $stream = new ReadableUnwrapStream($promise);

        $stream->on('close', $this->expectCallableOnce());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('error', $this->expectCallableNever());

        $stream->close();

        $this->assertFalse($stream->isReadable());
    }

    public function testClosingRejectingStreamMakesItNotReadable()
    {
        $deferred = new Deferred();

        $stream = new ReadableUnwrapStream($deferred->promise());

        $stream->on('close', $this->expectCallableOnce());
        $stream->on('end', $this->expectCallableNever());
        $stream->on('error', $this->expectCallableNever());

        $stream->close();

        $this->assertFalse($stream->isReadable());
    }

    public function testClosingStreamWillCancelInputPromiseAndMakeStreamNotReadable()
    {
        $promise = new Promise\Promise(function () { }, $this->expectCallableOnce());
        $stream = new ReadableUnwrapStream($promise);

        $stream->close();

        $this->assertFalse($stream->isReadable());
    }

    public function testClosingStreamWillNotEmitErrorWhenCancellationRejectsInputPromise()
    {
        $promise = new Promise\Promise(function () { }, function () {
            throw new \RuntimeException('Cancelled');
        });
        $stream = new ReadableUnwrapStream($promise);

        $stream->on('error', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());

        $stream->close();
    }

    public function testEmitsErrorWhenPromiseRejects()
    {
        $deferred = new Deferred();

        $stream = new ReadableUnwrapStream($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $stream->on('error', $this->expectCallableOnce());
        $stream->on('end', $this->expectCallableNever());

        $deferred->reject(new \RuntimeException());

        $this->assertFalse($stream->isReadable());
    }

    public function testReturnsClosedStreamIfInputStreamIsClosed()
    {
        $input = new ThroughStream();
        $input->close();

        $stream = new ReadableUnwrapStream(Promise\resolve($input));

        $this->assertFalse($stream->isReadable());
    }

    public function testReturnsStreamThatWillBeClosedWhenPromiseResolvesWithClosedInputStream()
    {
        $input = new ThroughStream();
        $input->close();

        $deferred = new Deferred();

        $stream = new ReadableUnwrapStream($deferred->promise());

        $this->assertTrue($stream->isReadable());

        $stream->on('close', $this->expectCallableOnce());

        $deferred->resolve($input);

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsDataWhenInputEmitsData()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input));

        $stream->on('data', $this->expectCallableOnceWith('hello world'));
        $input->emit('data', array('hello world'));
    }

    public function testEmitsCustomEventWhenInputEmitsCustomEvent()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input), 'stderr');

        $stream->on('data', $this->expectCallableNever());
        $stream->on('stderr', $this->expectCallableOnceWith('hello world'));
        $input->emit('stderr', array('hello world'));
    }

    public function testEmitsDataOnlyOnceWhenCustomEventIsData()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input), 'data');

        $stream->on('data', $this->expectCallableOnceWith('hello world'));
        $input->emit('data', array('hello world'));
    }

    public function testEmitsEndOnlyOnceWhenCustomEventIsEnd()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input), 'end');

        $stream->on('end', $this->expectCallableOnce());
        $input->end();
    }

    public function testEmitsErrorOnlyOnceWhenCustomEventIsError()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input), 'error');

        $stream->on('error', $this->expectCallableOnceWith(new \RuntimeException()));
        $input->emit('error', array(new \RuntimeException()));
    }

    public function testEmitsCloseOnlyOnceWhenCustomEventIsClose()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input), 'close');

        $stream->on('close', $this->expectCallableOnce());
        $input->close();
    }

    public function testEmitsErrorAndClosesWhenInputEmitsError()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input));

        $stream->on('error', $this->expectCallableOnceWith(new \RuntimeException()));
        $stream->on('close', $this->expectCallableOnce());
        $input->emit('error', array(new \RuntimeException()));

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsEndAndClosesWhenInputEmitsEnd()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input));

        $stream->on('end', $this->expectCallableOnce());
        $stream->on('close', $this->expectCallableOnce());
        $input->emit('end', array());

        $this->assertFalse($stream->isReadable());
    }

    public function testEmitsCloseOnlyOnceWhenClosingStreamMultipleTimes()
    {
        $promise = new Promise\Promise(function () { });
        $stream = new ReadableUnwrapStream($promise);

        $stream->on('end', $this->expectCallableNever());
        $stream->on('close', $this->expectCallableOnce());

        $stream->close();
        $stream->close();
    }

    public function testForwardsPauseToInputStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('isReadable')->willReturn(true);
        $input->expects($this->once())->method('pause');

        $stream = new ReadableUnwrapStream(Promise\resolve($input));

        $stream->pause();
    }

    public function testPauseBeforePromiseResolvesWillPauseInputStreamOnceResolved()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('isReadable')->willReturn(true);
        $input->expects($this->once())->method('pause');

        $deferred = new Deferred();
        $stream = new ReadableUnwrapStream($deferred->promise());

        $stream->pause();
        $deferred->resolve($input);
    }

    public function testPauseAndResumeBeforePromiseResolvesWillNotPauseInputStreamOnceResolved()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('isReadable')->willReturn(true);
        $input->expects($this->never())->method('pause');

        $deferred = new Deferred();
        $stream = new ReadableUnwrapStream($deferred->promise());

        $stream->pause();
        $stream->resume();
        $deferred->resolve($input);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPauseAfterCloseHasNoEffect()
    {
        $promise = new Promise\Promise(function () { });
        $stream = new ReadableUnwrapStream($promise);

        $stream->close();
        $stream->pause();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPauseAfterErrorDueToInvalidInputHasNoEffect()
    {
        $stream = new ReadableUnwrapStream(Promise\reject(new \RuntimeException()));

        $stream->pause();
    }

    public function testForwardsResumeToInputStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('isReadable')->willReturn(true);
        $input->expects($this->once())->method('resume');

        $stream = new ReadableUnwrapStream(Promise\resolve($input));

        $stream->resume();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testResumeAfterCloseHasNoEffect()
    {
        $promise = new Promise\Promise(function () { });
        $stream = new ReadableUnwrapStream($promise);

        $stream->close();
        $stream->resume();
    }

    public function testPipingStreamWillForwardDataEvents()
    {
        $input = new ThroughStream();
        $stream = new ReadableUnwrapStream(Promise\resolve($input));

        $output = new ThroughStream();
        $buffer = '';
        $output->on('data', function ($data) use (&$buffer) {
            $buffer .= $data;
        });
        $output->on('end', $this->expectCallableOnce());
        $stream->pipe($output);

        $input->emit('data', array('hello'));
        $input->emit('data', array('world'));
        $input->end();

        $this->assertEquals('helloworld', $buffer);
    }

    public function testClosingStreamWillCloseInputStream()
    {
        $input = $this->getMockBuilder('React\Stream\ReadableStreamInterface')->getMock();
        $input->expects($this->once())->method('isReadable')->willReturn(true);
        $input->expects($this->once())->method('close');

        $stream = new ReadableUnwrapStream(Promise\resolve($input));

        $stream->close();
    }

    public function testClosingStreamWillEmitCloseEventToExistingListenersBeforeClosingInputStream()
    {
        $input = new ThroughStream();

        $deferred = new Deferred();
        $stream = new ReadableUnwrapStream($deferred->promise());

        $readable = null;
        $stream->on('close', function () use ($input, &$readable) {
            $readable = $input->isReadable();
        });

        $deferred->resolve($input);
        $stream->close();

        $this->assertTrue($readable);
        $this->assertFalse($input->isReadable());
    }

    public function testClosingStreamWillCloseStreamIfItIgnoredCancellationAndResolvesLater()
    {
        $input = new ThroughStream();

        $deferred = new Deferred();
        $stream = new ReadableUnwrapStream($deferred->promise());

        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $deferred->resolve($input);

        $this->assertFalse($input->isReadable());
    }

    public function testClosingStreamWillCloseStreamFromCancellationHandler()
    {
        $input = new ThroughStream();

        $promise = new Promise\Promise(function () { }, function ($resolve) use ($input) {
            $resolve($input);
        });

        $stream = new ReadableUnwrapStream($promise);

        $stream->on('close', $this->expectCallableOnce());

        $stream->close();

        $this->assertFalse($input->isReadable());
    }

    public function testCloseShouldRemoveAllListenersAfterCloseEvent()
    {
        $promise = new Promise\Promise(function () { });
        $stream = new ReadableUnwrapStream($promise);

        $stream->on('close', $this->expectCallableOnce());
        $this->assertCount(1, $stream->listeners('close'));

        $stream->close();

        $this->assertCount(0, $stream->listeners('close'));
    }

    public function testCloseShouldRemoveReferenceToPromiseToAvoidGarbageReferences()
    {
        $promise = new Promise\Promise(function () { });
        $stream = new ReadableUnwrapStream($promise);

        $stream->close();

        $ref = new \ReflectionProperty($stream, 'promise');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $this->assertNull($ref->getValue($stream));
    }
}
