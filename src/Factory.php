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

        $sender = Sender::createFromLoopUnix($this->loop, $url);

        $browser = new Browser($this->loop, $sender);

        return new Client($browser);
    }
}
