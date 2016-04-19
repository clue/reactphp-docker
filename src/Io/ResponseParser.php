<?php

namespace Clue\React\Docker\Io;

use Psr\Http\Message\ResponseInterface;

class ResponseParser
{
    public function expectPlain(ResponseInterface $response)
    {
        // text/plain

        return (string)$response->getBody();
    }

    public function expectJson(ResponseInterface $response)
    {
        // application/json

        return json_decode((string)$response->getBody(), true);
    }

    public function expectEmpty(ResponseInterface $response)
    {
        // 204 No Content
        // no content-type

        return $this->expectPlain($response);
    }
}
