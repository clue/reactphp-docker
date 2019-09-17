<?php

namespace Clue\React\Docker\Io;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * Parser for Docker's own frame format used for bidrectional frames
 *
 * Each frame consists of a simple header containing the stream identifier and the payload length
 * plus the actual payload string.
 *
 * @internal
 * @link https://docs.docker.com/engine/reference/api/docker_remote_api_v1.15/#attach-to-a-container
 */
class ReadableDemultiplexStream extends EventEmitter implements ReadableStreamInterface
{
    private $buffer = '';
    private $closed = false;
    private $multiplexed;
    private $stderrEvent;

    public function __construct(ReadableStreamInterface $multiplexed, $stderrEvent = null)
    {
        $this->multiplexed = $multiplexed;

        if ($stderrEvent === null) {
            $stderrEvent = 'data';
        }

        $this->stderrEvent = $stderrEvent;

        $out = $this;
        $buffer =& $this->buffer;
        $closed =& $this->closed;

        // pass all input data chunks through the parser
        $multiplexed->on('data', array($out, 'push'));

        // forward end event to output (unless parsing is still in progress)
        $multiplexed->on('end', function () use (&$buffer, $out, &$closed) {
            // ignore duplicate end events
            if ($closed) {
                return;
            }

            // buffer must be empty on end, otherwise this is an error situation
            if ($buffer === '') {
                $out->emit('end');
            } else {
                $out->emit('error', array(new \RuntimeException('Stream ended within incomplete multiplexed chunk')));
            }
            $out->close();
        });

        // forward error event to output
        $multiplexed->on('error', function ($error) use ($out) {
            $out->emit('error', array($error));
            $out->close();
        });

        // forward close event to output
        $multiplexed->on('close', function () use ($out) {
            $out->close();
        });
    }

    /**
     * push the given stream chunk into the parser buffer and try to extract all frames
     *
     * @internal
     * @param string $chunk
     */
    public function push($chunk)
    {
        $this->buffer .= $chunk;

        while ($this->buffer !== '') {
            if (!isset($this->buffer[7])) {
                // last header byte not set => no complete header in buffer
                break;
            }

            $header = unpack('Cstream/x/x/x/Nlength', substr($this->buffer, 0, 8));

            if (!isset($this->buffer[7 + $header['length']])) {
                // last payload byte not set => message payload is incomplete
                break;
            }

            $payload = substr($this->buffer, 8, $header['length']);
            $this->buffer = (string)substr($this->buffer, 8 + $header['length']);

            $this->emit(
                ($header['stream'] === 2) ? $this->stderrEvent : 'data',
                array($payload)
            );
        }
    }

    public function pause()
    {
        $this->multiplexed->pause();
    }

    public function resume()
    {
        $this->multiplexed->resume();
    }

    public function isReadable()
    {
        return $this->multiplexed->isReadable();
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
        $this->multiplexed->close();
        $this->buffer = '';

        $this->emit('close');
        $this->removeAllListeners();
    }
}
