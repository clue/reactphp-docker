<?php
// this simple example displays system wide information from Docker as a simple JSON

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$client->info()->then(function ($info) {
    echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}, 'printf');

$loop->run();
