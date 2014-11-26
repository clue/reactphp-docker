<?php

use Clue\React\Docker\Client;
use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;

class FunctionalClientTest extends TestCase
{
    private $client;

    public function setUp()
    {
        $this->loop = LoopFactory::create();

        $factory = new Factory($this->loop);

        $this->client = $factory->createClient();

        $promise = $this->client->ping();

        try {
            $this->waitFor($promise, $this->loop);
        } catch (Exception $e) {
            $this->markTestSkipped('Unable to connect to docker ' . $e->getMessage());
        }
    }

    public function testPing()
    {
        $this->expectPromiseResolve($this->client->ping(), 'OK');

        $this->loop->run();
    }

    public function testInfo()
    {
        $this->expectPromiseResolve($this->client->info());

        $this->loop->run();
    }

    public function testVersion()
    {
        $this->expectPromiseResolve($this->client->version());

        $this->loop->run();
    }

    public function testContainerList()
    {
        $this->expectPromiseResolve($this->client->containerList());

        $this->loop->run();
    }
}
