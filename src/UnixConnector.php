<?php

namespace Clue\React\Docker;

use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use RuntimeException;

/**
 * dummy unix domain socket connector
 *
 * The path to connect to is set once during instantiation, the actual
 * target host is then ignored.
 *
 * Unix domain sockets use atomic operations, so we can as well emulate
 * async behavior.
 *
 * Use a small delay of 0.001s to avoid a race condition in the http library,
 * see https://github.com/clue/php-buzz-react/issues/19
 */
class UnixConnector implements ConnectorInterface
{
    private $loop;
    private $path;

    public function __construct(LoopInterface $loop, $path)
    {
        $this->loop = $loop;
        $this->path = $path;
    }

    public function create($host, $port)
    {
        $deferred = new Deferred();

        $path = $this->path;
        $loop = $this->loop;
        $this->loop->addTimer(0.001, function() use ($deferred, $path, $loop) {
            $resource = @stream_socket_client($this->path, $errno, $errstr, 1.0);

            if (!$resource) {
                $deferred->reject(new RuntimeException('Unable to connect to unix domain socket path: ' . $errstr, $errno));
            } else {
                $deferred->resolve(new Stream($resource, $this->loop));
            }
        });

        return $deferred->promise();
    }
}
