<?php

use Clue\React\Docker\Io\ResponseParser;
use Clue\React\Buzz\Message\Response;
use Clue\React\Buzz\Message\Body;

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
        return new Response('HTTP/1.0', 200, 'OK', array(), new Body($body));
    }

}
