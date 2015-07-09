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
    private $browser;

    public function __construct(LoopInterface $loop, Browser $browser = null)
    {
        if ($browser === null) {
            $browser = new Browser($loop);
        }

        $this->loop = $loop;
        $this->browser = $browser;
    }

    public function createClient($url = null)
    {
        if ($url === null) {
            $url = 'unix:///var/run/docker.sock';
        }

        $browser = $this->browser;

        if (substr($url, 0, 7) === 'unix://') {
            // send everything through a local unix domain socket
            $sender = Sender::createFromLoopUnix($this->loop, $url);
            $browser = $browser->withSender($sender);

            // pretend all HTTP URLs to be on localhost
            $url = 'http://localhost/';
        }

        return new Client($browser->withBase($url));
    }
}
