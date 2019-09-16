<?php

namespace Clue\React\Docker\Io;

use Psr\Http\Message\ResponseInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Promise\Stream;
use React\Stream\ReadableStreamInterface;
use RuntimeException;

/**
 * StreamingParser is a simple helper to work with the streaming body of HTTP response objects
 *
 * @internal
 * @see ResponseParser for working with buffered bodies
 */
class StreamingParser
{
    /**
     * Returns a readable JSON stream for the given ResponseInterface
     *
     * @param PromiseInterface $promise Promise<ResponseInterface>
     * @return ReadableStreamInterface
     * @uses self::parsePlainSream()
     */
    public function parseJsonStream(PromiseInterface $promise)
    {
        // application/json

        return new ReadableJsonStream($this->parsePlainStream($promise));
    }

    /**
     * Returns a readable plain text stream for the given ResponseInterface
     *
     * @param PromiseInterface $promise Promise<ResponseInterface>
     * @return ReadableStreamInterface
     */
    public function parsePlainStream(PromiseInterface $promise)
    {
        // text/plain

        return Stream\unwrapReadable($promise->then(function (ResponseInterface $response) {
            return $response->getBody();
        }));
    }

    /**
     * Returns a readable plain text stream for the given multiplexed stream using Docker's "attach multiplexing protocol"
     *
     * @param ReadableStreamInterface $input
     * @param string $stderrEvent
     * @return ReadableStreamInterface
     */
    public function demultiplexStream(ReadableStreamInterface $input, $stderrEvent = null)
    {
        return new ReadableDemultiplexStream($input, $stderrEvent);
    }

    /**
     * Returns a promise which resolves with the buffered stream contents of the given stream
     *
     * @param ReadableStreamInterface $stream
     * @return PromiseInterface Promise<string, Exception>
     */
    public function bufferedStream(ReadableStreamInterface $stream)
    {
        return Stream\buffer($stream);
    }

    /**
     * Returns a promise which resolves with an array of all "data" events
     *
     * @param ReadableStreamInterface $stream
     * @return PromiseInterface Promise<array, Exception>
     */
    public function deferredStream(ReadableStreamInterface $stream)
    {
        // cancelling the deferred will (try to) close the stream
        $deferred = new Deferred(function () use ($stream) {
            $stream->close();

            throw new RuntimeException('Cancelled');
        });

        if ($stream->isReadable()) {
            // buffer all data events for deferred resolving
            $buffered = array();
            $stream->on('data', function ($data) use (&$buffered) {
                $buffered []= $data;
            });

            // error event rejects
            $stream->on('error', function ($error) use ($deferred) {
                $deferred->reject($error);
            });

            // close event resolves with buffered events (unless already error'ed)
            $stream->on('close', function () use ($deferred, &$buffered) {
                $deferred->resolve($buffered);
            });
        } else {
            $deferred->reject(new RuntimeException('Stream already ended, looks like it could not be opened'));
        }

        return $deferred->promise();
    }
}
