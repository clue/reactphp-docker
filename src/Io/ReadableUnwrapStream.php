<?php

namespace Clue\React\Docker\Io;

use Evenement\EventEmitter;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * Readable stream for the given promise which resolves with a readable stream
 *
 * @internal
 * @link https://github.com/reactphp/promise-stream/blob/v1.7.0/src/UnwrapReadableStream.php Based on react/promise-stream
 */
class ReadableUnwrapStream extends EventEmitter implements ReadableStreamInterface
{
    /** @var ?PromiseInterface<ReadableStreamInterface> */
    private $promise;

    /** @var ?ReadableStreamInterface */
    private $input;

    private $paused = false;
    private $closed = false;

    /**
     * @param PromiseInterface<ReadableStreamInterface> $promise
     */
    public function __construct(PromiseInterface $promise)
    {
        $this->promise = $promise;

        $out = $this;
        $input =& $this->input;
        $paused =& $this->paused;

        $promise->then(
            function (ReadableStreamInterface $stream) use ($out, &$input, &$paused) {
                // output already closed while waiting or input already closed => close other side
                if (!$out->isReadable() || !$stream->isReadable()) {
                    $stream->close();
                    $out->close();
                    return;
                }

                $input = $stream;
                if ($paused) {
                    $stream->pause();
                }

                // forward data event to output
                $stream->on('data', function ($data) use ($out) {
                    $out->emit('data', array($data));
                });

                // forward end event to output and close
                $stream->on('end', function () use ($out) {
                    $out->emit('end');
                    $out->close();
                });

                // forward error event to output and close
                $stream->on('error', function ($e) use ($out) {
                    $out->emit('error', array($e));
                    $out->close();
                });

                // close both streams once either side closes
                $stream->on('close', array($out, 'close'));
                $out->on('close', array($stream, 'close'));
            },
            function ($e) use ($out) {
                // ignore rejection after output is closed (cancellation)
                if ($out->isReadable()) {
                    $out->emit('error', array($e));
                    $out->close();
                }
            }
        );
    }

    public function isReadable()
    {
        return !$this->closed;
    }

    public function pause()
    {
        $this->paused = true;
        if ($this->input !== null) {
            $this->input->pause();
        }
    }

    public function resume()
    {
        $this->paused = false;
        if ($this->input !== null) {
            $this->input->resume();
        }
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return Util::pipe($this, $dest, $options);
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        // try to cancel promise once the stream closes
        if ($this->promise !== null && method_exists($this->promise, 'cancel')) {
            $this->promise->cancel();
        }
        $this->promise = null;

        $this->emit('close');
        $this->removeAllListeners();
    }
}
