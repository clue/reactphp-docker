<?php

// this example shows how the containerAttachStream() call can be used to get the output of the given container.
// demonstrates the streaming attach API, which can be used to dump the container output as it arrives
//
// $ docker run -it --rm --name=foo busybox sh
// $ php examples/attach-stream.php foo

use Clue\CaretNotation\Encoder;
use Clue\React\Docker\Client;

require __DIR__ . '/../vendor/autoload.php';

$container = isset($argv[1]) ? $argv[1] : 'foo';
echo 'Dumping output of container "' . $container . '" (pass as argument to this example)' . PHP_EOL;

$loop = React\EventLoop\Factory::create();
$client = new Client($loop);

// use caret notation for any control characters except \t, \r and \n
$caret = new Encoder("\t\r\n");

$stream = $client->containerAttachStream($container, true, true);
$stream->on('data', function ($data) use ($caret) {
    echo $caret->encode($data);
});

$stream->on('error', function (Exception $e) {
    // will be called if either parameter is invalid
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
});

$stream->on('close', function () {
    echo 'CLOSED' . PHP_EOL;
});

$loop->run();
