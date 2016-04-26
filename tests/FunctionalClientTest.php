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

        $start = microtime(true);

        $promise = $this->client->containerStart($container['Id']);
        $ret = Block\await($promise, $this->loop);

        $this->assertEquals('', $ret);

        $promise = $this->client->containerRemove($container['Id'], false, true);
        $ret = Block\await($promise, $this->loop);

        $this->assertEquals('', $ret);

        $end = microtime(true);

        // get all events between starting and removing for this container
        $promise = $this->client->events($start, $end, array('container' => array($container['Id'])));
        $ret = Block\await($promise, $this->loop);

        // expects "start", "kill", "die", "destroy" events
        $this->assertEquals(4, count($ret));
        $this->assertEquals('start', $ret[0]['status']);
        $this->assertEquals('kill', $ret[1]['status']);
        $this->assertEquals('die', $ret[2]['status']);
        $this->assertEquals('destroy', $ret[3]['status']);
    }

    public function testStartRunning()
    {
        $config = array(
            'Image' => 'busybox',
            'Tty' => true,
            'Cmd' => array('sleep', '10')
        );

        $promise = $this->client->containerCreate($config);
        $container = Block\await($promise, $this->loop);

        $this->assertNotNull($container['Id']);
        $this->assertNull($container['Warnings']);

        $start = microtime(true);

        $promise = $this->client->containerStart($container['Id']);
        $ret = Block\await($promise, $this->loop);

        $this->assertEquals('', $ret);

        return $container['Id'];
    }

    /**
     * @depends testStartRunning
     * @param string $container
     * @return string
     */
    public function testExecCreateWhileRunning($container)
    {
        $promise = $this->client->execCreate($container, array(
            'Cmd' => array('echo', '-n', 'hello', 'world'),
            'AttachStdout' => true,
            'AttachStderr' => true,
            'Tty' => true
        ));
        $exec = Block\await($promise, $this->loop);

        $this->assertTrue(is_array($exec));
        $this->assertTrue(is_string($exec['Id']));

        return $exec['Id'];
    }

    /**
     * @depends testExecCreateWhileRunning
     * @param string $exec
     */
    public function testExecInspectBeforeRunning($exec)
    {
        $promise = $this->client->execInspect($exec);
        $info = Block\await($promise, $this->loop);

        $this->assertTrue(is_array($info));
        $this->assertFalse($info['Running']);
        $this->assertEquals(null, $info['ExitCode']);
    }

    /**
     * @depends testExecCreateWhileRunning
     * @param string $exec
     */
    public function testExecStartWhileRunning($exec)
    {
        $promise = $this->client->execStart($exec, array('Tty' => true));
        $output = Block\await($promise, $this->loop);

        $this->assertEquals('hello world', $output);
    }

    /**
     * @depends testExecCreateWhileRunning
     * @param string $exec
     */
    public function testExecInspectAfterRunning($exec)
    {
        $promise = $this->client->execInspect($exec);
        $info = Block\await($promise, $this->loop);

        $this->assertTrue(is_array($info));
        $this->assertFalse($info['Running']);
        $this->assertEquals(0, $info['ExitCode']);
    }

    /**
     * @depends testStartRunning
     * @param string $container
     */
    public function testRemoveRunning($container)
    {
        $promise = $this->client->containerRemove($container, true, true);
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
