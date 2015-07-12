<?php
// this example shows how the imageCreateStream() call can be used to pull a given image.
// demonstrates the JSON streaming API, individual progress events will be printed as they happen.

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;

$image = isset($argv[1]) ? $argv[1] : 'clue/redis-benchmark';
echo 'Pulling image "' . $image . '" (pass as argument to this example)' . PHP_EOL;

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$stream = $client->imageCreateStream($image);

$stream->on('progress', function ($progress) {
    echo 'progress: '. json_encode($progress) . PHP_EOL;
});

$stream->on('close', function () {
    echo 'stream closed' . PHP_EOL;
});

$loop->run();
