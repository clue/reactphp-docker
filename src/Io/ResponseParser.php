<?php

namespace Clue\React\Docker\Io;

use Clue\React\Buzz\Message\Response;

class ResponseParser
{
    public function expectPlain(Response $response)
    {
        // text/plain

        return (string)$response->getBody();
    }

    public function expectJson(Response $response)
    {
        // application/json

        return json_decode((string)$response->getBody(), true);
    }

    public function expectEmpty(Response $response)
    {
        // 204 No Content
        // no content-type

        return $this->expectPlain($response);
    }
}
