<?php

use Clue\React\Docker\Io\MultiplexStreamParser;

class MultiplexStreamParserTest extends TestCase
{
    private $parser;

    public function setUp()
    {
        $this->parser = new MultiplexStreamParser();
    }

    public function testEmpty()
    {
        $this->assertTrue($this->parser->isEmpty());
    }

    public function testCompleteFrame()
    {
        $frame = $this->parser->createFrame(1, 'test');

        $this->assertEquals("\x01\x00\x00\x00" . "\x00\x00\x00\x04" . "test", $frame);
    }

    public function testParseFrame()
    {
        $frame = $this->parser->createFrame(1, 'hello world');
        $this->parser->push($frame, $this->expectCallableOnceWith(1, 'hello world'));

        $this->assertTrue($this->parser->isEmpty());
    }

    public function testIncompleteHeader()
    {
        $this->parser->push("\x01\0\0\0", $this->expectCallableNever());
        $this->assertFalse($this->parser->isEmpty());

        return $this->parser;
    }

    /**
     * @depends testIncompleteHeader
     * @param MultiplexStreamParser $parser
     */
    public function testIncompletePayload(MultiplexStreamParser $parser)
    {
        $parser->push("\0\0\0\x04te", $this->expectCallableNever());
        $this->assertFalse($parser->isEmpty());

        return $parser;
    }

    /**
     * @depends testIncompletePayload
     * @param MultiplexStreamParser $parser
     */
    public function testChunkedFrame(MultiplexStreamParser $parser)
    {
        $parser->push('st', $this->expectCallableOnceWith(1, 'test'));

        $this->assertTrue($this->parser->isEmpty());
    }
}
