<?php

namespace Clue\Tests\React\Docker;

use Clue\React\Docker\Factory;
use React\EventLoop\Factory as LoopFactory;

class FactoryTest extends TestCase
{
    private $loop;
    private $browser;
    private $factory;

    public function setUp()
    {
        $this->loop = LoopFactory::create();
        $this->browser = $this->getMockBuilder('Clue\React\Buzz\Browser')->disableOriginalConstructor()->getMock();
        $this->factory = new Factory($this->loop, $this->browser);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCtorDefaultBrowser()
    {
        new Factory($this->loop);
    }

    public function testCreateClientUsesCustomUnixSender()
    {
        $this->browser->expects($this->once())->method('withSender')->will($this->returnValue($this->browser));
        $this->browser->expects($this->once())->method('withBase')->will($this->returnValue($this->browser));

        $client = $this->factory->createClient();

        $this->assertInstanceOf('Clue\React\Docker\Client', $client);
    }

    public function testCreateClientWithHttp()
    {
        $this->browser->expects($this->never())->method('withSender');
        $this->browser->expects($this->once())->method('withBase')->with($this->equalTo('http://localhost:1234/'))->will($this->returnValue($this->browser));

        $this->factory->createClient('http://localhost:1234/');
    }
}
