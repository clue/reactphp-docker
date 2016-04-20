<?php

namespace Clue\React\Docker\Io;

use React\Promise\PromiseInterface;
use Clue\JsonStream\StreamingJsonParser;
use React\Promise\Deferred;
use React\Stream\ReadableStream;
use React\Stream\ReadableStreamInterface;
use RuntimeException;
use React\Promise\CancellablePromiseInterface;
use Clue\React\Promise\Stream;
use Psr\Http\Message\ResponseInterface;

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

        $in = $this->parsePlainStream($promise);
        $out = new ReadableStream();

        // invalid/closing input stream => return closed output stream
        if (!$in->isReadable()) {
            $out->close();

            return $out;
        }

        // forward each data chunk to the streaming JSON parser
        $parser = new StreamingJsonParser();
        $in->on('data', function ($data) use ($parser, $out) {
            $objects = $parser->push($data);

            foreach ($objects as $object) {
                if (isset($object['error'])) {
                    $out->emit('error', array(new JsonProgressException($object), $out));
                    $out->close();
                    return;
                }
                $out->emit('progress', array($object, $out));
            }
        });

        // forward error and make sure stream closes
        $in->on('error', function ($error) use ($out) {
            $out->emit('error', array($error, $out));
            $out->close();
        });

        // closing either stream closes the other one
        $in->on('close', array($out, 'close'));
        $out->on('close', array($in, 'close'));

        return $out;
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
     * Returns a promise which resolves with an array of all "progress" events
     *
     * @param ReadableStreamInterface $stream
     * @param string                  $progressEventName the name of the event to collect
     * @return PromiseInterface Promise<array, Exception>
     */
    public function deferredStream(ReadableStreamInterface $stream, $progressEventName)
    {
        // cancelling the deferred will (try to) close the stream
        $deferred = new Deferred(function () use ($stream) {
            $stream->close();

            throw new RuntimeException('Cancelled');
        });

        if ($stream->isReadable()) {
            // buffer all data events for deferred resolving
            $buffered = array();
            $stream->on($progressEventName, function ($data) use (&$buffered) {
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
