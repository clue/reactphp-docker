<?php

namespace Clue\React\Docker\Io;

/**
 * Parser for Docker's own frame format used for bidrectional frames
 *
 * Each frame consists of a simple header containing the stream identifier and the payload length
 * plus the actual payload string.
 *
 * @internal
 * @link https://docs.docker.com/engine/reference/api/docker_remote_api_v1.15/#attach-to-a-container
 */
class MultiplexStreamParser
{
    private $buffer = '';

    /**
     * push the given stream chunk into the parser buffer and try to extract all frames
     *
     * The given $callback parameter will be invoked for each individual frame
     * with the following signature: $callback($stream, $payload)
     *
     * @param string   $chunk
     * @param callable $callback
     */
    public function push($chunk, $callback)
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

            $callback($header['stream'], $payload);
        }
    }

    /**
     * checks whether the incoming frame buffer is empty
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return ($this->buffer === '');
    }

    /**
     * creates a new outgoing frame
     *
     * @param int    $stream
     * @param string $payload
     * @return string
     */
    public function createFrame($stream, $payload)
    {
        return pack('CxxxN', $stream, strlen($payload)) . $payload;
    }
}
