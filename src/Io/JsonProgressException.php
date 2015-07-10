<?php

namespace Clue\React\Docker\Io;

use RuntimeException;

/**
 * Will be used for JSON streaming endpoints where an individal progress event contains an "error" key
 */
class JsonProgressException extends RuntimeException
{
    private $data;

    public function __construct($data, $message = null, $code = null, $previous = null)
    {
        if ($message === null) {
            $message = $data['error'];
        }
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    public function getData()
    {
        return $data;
    }
}
