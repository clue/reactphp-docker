<?php

// this example shows how the imageCreateStream() call can be used to pull a given image.
// demonstrates the JSON streaming API, individual progress events will be printed as they happen.

require __DIR__ . '/../vendor/autoload.php';

$image = isset($argv[1]) ? $argv[1] : 'clue/redis-benchmark';
echo 'Pulling image "' . $image . '" (pass as argument to this example)' . PHP_EOL;

$client = new Clue\React\Docker\Client();

$stream = $client->imageCreateStream($image);

$stream->on('data', function ($progress) {
    echo 'progress: '. json_encode($progress) . PHP_EOL;
});

$stream->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$stream->on('close', function () {
    echo 'stream closed' . PHP_EOL;
});
