<?php

namespace Clue\React\Docker\Io;

use Clue\JsonStream\StreamingJsonParser;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
use Evenement\EventEmitter;

/**
 * Parser for Docker's JSON stream format used for log messages etc.
 *
 * @internal
 */
class ReadableJsonStream extends EventEmitter implements ReadableStreamInterface
{
    private $closed = false;
    private $input;
    private $parser;

    public function __construct(ReadableStreamInterface $input)
    {
        $this->input = $input;
        $this->parser = $parser = new StreamingJsonParser();
        if (!$input->isReadable()) {
            $this->close();
            return;
        }

        // pass all input data chunks through the parser
        $input->on('data', array($this, 'handleData'));

        // forward end event to output
        $out = $this;
        $closed =& $this->closed;
        $input->on('end', function () use ($out, $parser, &$closed) {
            // ignore duplicate end events
            if ($closed) {
                return;
            }

            if ($parser->isEmpty()) {
                $out->emit('end');
            } else {
                $out->emit('error', array(new \RuntimeException('Stream ended within incomplete JSON data')));
            }
            $out->close();
        });

        // forward error event to output
        $input->on('error', function ($error) use ($out) {
            $out->emit('error', array($error));
            $out->close();
        });

        // forward close event to output
        $input->on('close', function () use ($out) {
            $out->close();
        });
    }

    /**
     * push the given stream chunk into the parser buffer and try to extract all JSON messages
     *
     * @internal
     * @param string $data
     */
    public function handleData($data)
    {
        // forward each data chunk to the streaming JSON parser
        try {
            $objects = $this->parser->push($data);
        } catch (\Exception $e) {
            $this->emit('error', array($e));
            $this->close();
            return;
        }

        foreach ($objects as $object) {
            // stop emitting data if stream is already closed
            if ($this->closed) {
                return;
            }

            if (isset($object['error'])) {
                $this->emit('error', array(new \RuntimeException($object['error'])));
                $this->close();
                return;
            }
            $this->emit('data', array($object));
        }
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
    }

    public function isReadable()
    {
        return $this->input->isReadable();
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

        // closing output stream closes input stream
        $this->input->close();

        $this->emit('close');
        $this->removeAllListeners();
    }
}
