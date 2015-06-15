<?php

namespace Clue\React\Docker\Io;

use React\Promise\PromiseInterface;
use Clue\JsonStream\StreamingJsonParser;
use React\Promise\Deferred;
use React\Stream\ReadableStream;
use React\Stream\ReadableStreamInterface;
use RuntimeException;
use React\Promise\CancellablePromiseInterface;

class StreamingParser
{
    public function parseJsonStream(PromiseInterface $promise)
    {
        // TODO: assert expect tcp stream

        $parser = new StreamingJsonParser();

        $out = new ReadableStream();

        // try to cancel promise once the stream closes
        if ($promise instanceof CancellablePromiseInterface) {
            $out->on('close', function() use ($promise) {
                $promise->cancel();
            });
        }

        $promise->then(
            function ($response) use ($out) {
                $out->close();
            },
            function ($error) use ($out) {
                $out->emit('error', array($error, $out));
                $out->close();
            },
            function ($progress) use ($parser, $out) {
                if (is_array($progress) && isset($progress['responseStream'])) {
                    $stream = $progress['responseStream'];
                    /* @var $stream React\Stream\Stream */

                    // hack to do not buffer stream contents in body
                    $stream->removeAllListeners('data');

                    // got a streaming HTTP reponse => forward each data chunk to the streaming JSON parser
                    $stream->on('data', function ($data) use ($parser, $out) {
                        $objects = $parser->push($data);

                        foreach ($objects as $object) {
                            $out->emit('progress', array($object, $out));
                        }
                    });
                }
            }
        );

        return $out;
    }

    public function deferredStream(ReadableStreamInterface $stream, $progressEventName)
    {
        // cancelling the deferred will (try to) close the stream
        $deferred = new Deferred(function () use ($stream) {
            $stream->close();

            throw new RuntimeException('Cancelled');
        });

        if ($stream->isReadable()) {
            // buffer all data events and emit as progress
            $buffered = array();
            $stream->on($progressEventName, function ($data) use ($deferred, &$buffered) {
                $buffered []= $data;
                $deferred->progress($data);
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
