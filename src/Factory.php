<?php

namespace Clue\React\Docker;

use React\EventLoop\LoopInterface;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Io\Sender;
use React\HttpClient\Client as HttpClient;
use Clue\React\Docker\Io\UnixConnector;

class Factory
{
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function createClient($url = null)
    {
        if ($url === null) {
            $url = 'unix:///var/run/docker.sock';
        }

        $sender = null;

        if (substr($url, 0, 7) === 'unix://') {
            // send everything through a local unix domain socket
            $sender = Sender::createFromLoopUnix($this->loop, $url);

            // pretend all HTTP URLs to be on localhost
            $url = 'http://localhost';
        }

        $browser = new Browser($this->loop, $sender);

        return new Client($browser, $url);
    }
}
