<?php

use Clue\React\Docker\Client;
use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;
use Clue\React\Block;

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
            Block\await($promise, $this->loop);
        } catch (Exception $e) {
            $this->markTestSkipped('Unable to connect to docker ' . $e->getMessage());
        }
    }

    public function testPing()
    {
        $this->expectPromiseResolve($this->client->ping(), 'OK');

        $this->loop->run();
    }

    public function testCreateStartAndRemoveContainer()
    {
        $config = array(
            'Image' => 'busybox',
            'Cmd' => array('echo', 'test')
        );

        $promise = $this->client->containerCreate($config);
        $container = Block\await($promise, $this->loop);

        $this->assertNotNull($container['Id']);
        $this->assertNull($container['Warnings']);

        $promise = $this->client->containerStart($container['Id']);
        $ret = Block\await($promise, $this->loop);

        $this->assertEquals('', $ret);

        $promise = $this->client->containerRemove($container['Id'], false, true);
        $ret = Block\await($promise, $this->loop);

        $this->assertEquals('', $ret);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testContainerRemoveInvalid()
    {
        $promise = $this->client->containerRemove('invalid123');
        Block\await($promise, $this->loop);
    }

    public function testImageSearch()
    {
        $promise = $this->client->imageSearch('clue');
        $ret = Block\await($promise, $this->loop);

        $this->assertGreaterThan(9, count($ret));
    }

    public function testImageTag()
    {
        // create new tag "bb:now" on "busybox:latest"
        $promise = $this->client->imageTag('busybox', 'bb', 'now');
        $ret = Block\await($promise, $this->loop);

        // delete tag "bb:now" again
        $promise = $this->client->imageRemove('bb:now');
        $ret = Block\await($promise, $this->loop);
    }

    public function testImageCreateStreamMissingWillEmitJsonError()
    {
        $stream = $this->client->imageCreateStream('clue/does-not-exist');

        // one "progress" event, but no "data" events
        $stream->on('progress', $this->expectCallableOnce());
        $stream->on('data', $this->expectCallableNever());

        // will emit "error" with JsonProgressException and close
        $stream->on('error', $this->expectCallableOnceParameter('Clue\React\Docker\Io\JsonProgressException'));
        $stream->on('close', $this->expectCallableOnce());

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
