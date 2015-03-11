<?php

namespace Clue\React\Docker\Io;

use React\Promise\PromiseInterface;
use Clue\JsonStream\StreamingJsonParser;
use React\Promise\Deferred;

class StreamingParser
{
    public function parseResponse(PromiseInterface $promise)
    {
        $parser = new StreamingJsonParser();

        $deferred = new Deferred();

        $promise->then(null, null, function ($progress) use ($parser, $deferred) {
            if (is_array($progress) && isset($progress['response'])) {
                $stream = $progress['response'];
                /* @var $stream React\Stream\Stream */

                // got a streaming HTTP reponse => forward each data chunk to the streaming JSON parser
                $stream->on('data', function ($data) use ($parser, $deferred) {
                    $objects = $parser->push($data);

                    foreach ($objects as $object) {
                        $deferred->progress($object);
                    }
                });
            }
        });

        $promise->then(array($deferred, 'resolve'), array($deferred, 'reject'));

        return $deferred->promise();
    }
}
