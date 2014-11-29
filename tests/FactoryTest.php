<?php

use Clue\React\Docker\Factory;
use React\EventLoop\Factory as LoopFactory;

class FactoryTest extends TestCase
{
    private $loop;
    private $factory;

    public function setUp()
    {
        $this->loop = LoopFactory::create();
        $this->factory = new Factory($this->loop);
    }

    public function testCreateClientDefault()
    {
        $client = $this->factory->createClient();

        $this->assertInstanceOf('Clue\React\Docker\Client', $client);
    }
}
