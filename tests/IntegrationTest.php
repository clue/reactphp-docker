<?php

namespace Clue\Tests\React\Docker;

use Clue\React\Docker\Client;
use React\Promise\Deferred;
use Psr\Http\Message\ServerRequestInterface;

class IntegrationTest extends TestCase
{
    public function testPingCtorWithExplicitUnixUrlSendsRequestToGivenUnixSocket()
    {
        $loop = \React\EventLoop\Factory::create();

        for ($i = 0; $i < 3; ++$i) {
            $path = sys_get_temp_dir() . '/clue-reactphp-docker.' . mt_rand() . '.sock';
            try {
                $socket = new \React\Socket\Server('unix://' . $path , $loop);
                break;
            } catch (\Exception $e) {
                $path = null;
            }
        }
        if ($path === null) {
            $this->markTestSkipped('Unable to start listening on Unix socket (Windows?)');
        }

        $deferred = new Deferred();
        $http = new \React\Http\Server($loop, function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request->getRequestTarget());
        });

        $http->listen($socket);

        $client = new Client($loop, 'unix://' . $path);
        $client->ping();

        $value = \Clue\React\Block\await($deferred->promise(), $loop, 1.0);
        unlink($path);

        $this->assertEquals('/_ping', $value);
    }

    public function testPingCtorWithExplicitHttpUrlSendsRequestToGivenHttpUrlWithBase()
    {
        $loop = \React\EventLoop\Factory::create();

        $deferred = new Deferred();
        $http = new \React\Http\Server($loop, function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request->getRequestTarget());
        });

        $socket = new \React\Socket\Server(0, $loop);
        $http->listen($socket);

        $client = new Client($loop, str_replace('tcp://', 'http://', $socket->getAddress()) . '/base/');
        $client->ping();

        $value = \Clue\React\Block\await($deferred->promise(), $loop, 1.0);

        $this->assertEquals('/base/_ping', $value);
    }
}
