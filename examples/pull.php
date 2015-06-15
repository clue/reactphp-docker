<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;

$image = isset($argv[1]) ? $argv[1] : 'clue/redis-benchmark';
echo 'Pulling image "' . $image . '" (pass as argument to this example)' . PHP_EOL;

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$client->imageCreate($image)->then(
    function ($response) {
        echo 'response: ' . json_encode($response) . PHP_EOL;
    },
    'var_dump',
    function ($info) {
        echo 'update: ' . json_encode($info) . PHP_EOL;
    }
);

$loop->run();
