<?php

namespace Clue\Tests\React\Docker\Io;

use Clue\React\Docker\Io\ResponseParser;
use Clue\Tests\React\Docker\TestCase;
use RingCentral\Psr7\Response;

class ResponseParserTest extends TestCase
{
    private $parser;

    public function setUp()
    {
        $this->parser = new ResponseParser();
    }

    public function testPlain()
    {
        $body = 'OK';
        $this->assertEquals($body, $this->parser->expectPlain($this->createResponse($body)));
    }

    public function testJson()
    {
        $json = array('test' => 'value');
        $this->assertEquals($json, $this->parser->expectJson($this->createResponse(json_encode($json))));
    }

    public function testEmpty()
    {
        $this->assertEquals('', $this->parser->expectEmpty($this->createResponse()));
    }

    private function createResponse($body = '')
    {
        return new Response(200, array(), $body);
    }

}
