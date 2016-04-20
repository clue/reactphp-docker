<?php
// this example tries to adjust the TTY size of the given container to 10x10.
// you can check this via "docker logs".

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;

$container = isset($argv[1]) ? $argv[1] : 'asd';

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$client->containerInspect($container)->then(function ($info) use ($client, $container) {
    $size = $info['HostConfig']['ConsoleSize'];

    echo 'Current TTY size is ' . $size[0] . 'x' . $size[1] . PHP_EOL;

    return $client->containerResize($container, $size[0] + 10, $size[1] + 10);
})->then(function () use ($client) {
    echo 'Successfully set' . PHP_EOL;
}, 'printf');


$loop->run();
