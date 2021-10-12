<?php

// this example shows how the containerLogsStream() call can be used to get the logs of the given container.
// demonstrates the streaming logs API, which can be used to dump the logs as they arrive

require __DIR__ . '/../vendor/autoload.php';

$container = isset($argv[1]) ? $argv[1] : 'asd';
echo 'Dumping logs (last 100 lines) of container "' . $container . '" (pass as argument to this example)' . PHP_EOL;

$client = new Clue\React\Docker\Client();

// use caret notation for any control characters except \t, \r and \n
$caret = new Clue\CaretNotation\Encoder("\t\r\n");

$stream = $client->containerLogsStream($container, true, true, true, 0, false, 100);

$stream->on('data', function ($data) use ($caret) {
    echo $caret->encode($data);
});

$stream->on('error', function (Exception $e) {
    // will be called if either parameter is invalid
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$stream->on('close', function ($e = null) {
    echo 'CLOSED' . PHP_EOL . $e;
});
