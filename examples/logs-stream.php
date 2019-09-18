<?php

// this example shows how the containerLogsStream() call can be used to get the logs of the given container.
// demonstrates the streaming logs API, which can be used to dump the logs as they arrive

use Clue\CaretNotation\Encoder;
use Clue\React\Docker\Client;

require __DIR__ . '/../vendor/autoload.php';

$container = isset($argv[1]) ? $argv[1] : 'asd';
echo 'Dumping logs (last 100 lines) of container "' . $container . '" (pass as argument to this example)' . PHP_EOL;

$loop = React\EventLoop\Factory::create();
$client = new Client($loop);

// use caret notation for any control characters except \t, \r and \n
$caret = new Encoder("\t\r\n");

$stream = $client->containerLogsStream($container, true, true, true, 0, false, 100);
$stream->on('data', function ($data) use ($caret) {
    echo $caret->encode($data);
});

$stream->on('error', function ($e = null) {
    // will be called if either parameter is invalid
    echo 'ERROR requesting stream' . PHP_EOL . $e;
});

$stream->on('close', function ($e = null) {
    echo 'CLOSED' . PHP_EOL . $e;
});

$loop->run();
