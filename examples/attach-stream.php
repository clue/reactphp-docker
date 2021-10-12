<?php

// this example shows how the containerAttachStream() call can be used to get the output of the given container.
// demonstrates the streaming attach API, which can be used to dump the container output as it arrives
//
// $ docker run -it --rm --name=foo busybox sh
// $ php examples/attach-stream.php foo

require __DIR__ . '/../vendor/autoload.php';

$container = isset($argv[1]) ? $argv[1] : 'foo';
echo 'Dumping output of container "' . $container . '" (pass as argument to this example)' . PHP_EOL;

$client = new Clue\React\Docker\Client();

// use caret notation for any control characters except \t, \r and \n
$caret = new Clue\CaretNotation\Encoder("\t\r\n");

$stream = $client->containerAttachStream($container, true, true);

$stream->on('data', function ($data) use ($caret) {
    echo $caret->encode($data);
});

$stream->on('error', function (Exception $e) {
    // will be called if either parameter is invalid
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$stream->on('close', function () {
    echo 'CLOSED' . PHP_EOL;
});
