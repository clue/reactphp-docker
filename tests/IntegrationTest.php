<?php

namespace Clue\Tests\React\Docker;

use Clue\React\Docker\Client;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
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
            return Response::plaintext('OK');
        });

        $http->listen($socket);

        $client = new Client(null, 'unix://' . $path);
        $client->ping();

        $value = \React\Async\await($deferred->promise());
        unlink($path);

        $this->assertEquals('/_ping', $value);

        $socket->close();
    }

    public function testPingCtorWithExplicitHttpUrlSendsRequestToGivenHttpUrlWithBase()
    {
        $deferred = new Deferred();
        $http = new \React\Http\HttpServer(function (ServerRequestInterface $request) use ($deferred) {
            $deferred->resolve($request->getRequestTarget());
            return Response::plaintext('OK');
        });

        $socket = new \React\Socket\SocketServer('127.0.0.1:0');
        $http->listen($socket);

        $client = new Client(null, str_replace('tcp://', 'http://', $socket->getAddress()) . '/base/');
        $client->ping();

        $value = \React\Async\await($deferred->promise());

        $this->assertEquals('/base/_ping', $value);

        $socket->close();
    }
}
