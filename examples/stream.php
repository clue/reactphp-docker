<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;

$image = isset($argv[1]) ? $argv[1] : 'clue/redis-benchmark';

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$client->imageCreate($image)->then(
    function ($response) {
        echo 'response: '; var_dump($response);
    },
    'var_dump',
    function ($info) {
        echo 'update: ' . json_encode($info) . PHP_EOL;
    }
);

$loop->run();
