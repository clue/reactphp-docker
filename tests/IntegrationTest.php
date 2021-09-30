<?php

namespace Clue\Tests\React\Docker;

use Clue\React\Docker\Client;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Promise\Deferred;

class IntegrationTest extends TestCase
{
    public function testPingCtorWithExplicitUnixUrlSendsRequestToGivenUnixSocket()
    {
        for ($i = 0; $i < 3; ++$i) {
            $path = sys_get_temp_dir() . '/clue-reactphp-docker.' . mt_rand() . '.sock';
            try {
                $socket = new \React\Socket\SocketServer('unix://' . $path);
                break;
            } catch (\Exception $e) {
                $path = null;
            }
        }
        if ($path === null) {
            $this->markTestSkipped('Unable to start listening on Unix socket (Windows?)');
        }

        $deferred = new Deferred();
        $http = new \React\Http\HttpServer(function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request->getRequestTarget());
        });

        $http->listen($socket);

        $client = new Client(null, 'unix://' . $path);
        $client->ping();

        $value = \Clue\React\Block\await($deferred->promise(), Loop::get(), 1.0);
        unlink($path);

        $this->assertEquals('/_ping', $value);
    }

    public function testPingCtorWithExplicitHttpUrlSendsRequestToGivenHttpUrlWithBase()
    {
        $loop = Loop::get();

        $deferred = new Deferred();
        $http = new \React\Http\HttpServer(function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request->getRequestTarget());
        });

        $socket = new \React\Socket\SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $client = new Client(null, str_replace('tcp://', 'http://', $socket->getAddress()) . '/base/');
        $client->ping();

        $value = \Clue\React\Block\await($deferred->promise(), $loop, 1.0);

        $this->assertEquals('/base/_ping', $value);
    }
}
