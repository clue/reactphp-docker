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

        $connector = new UnixConnector($this->loop, $url);

        $ref = new \ReflectionClass('React\HttpClient\Client');
        if ($ref->getConstructor()->getNumberOfRequiredParameters() == 2) {
            // react/http-client:0.4 removed the $loop parameter
            $http = new HttpClient($connector, $connector);
        } else {
            $http = new HttpClient($this->loop, $connector, $connector);
        }

        $sender = new Sender($http);

        $browser = new Browser($this->loop, $sender);

        return new Client($browser);
    }
}
