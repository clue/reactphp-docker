<?php

// this simple example displays system wide information from Docker as a simple JSON

use Clue\React\Docker\Client;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Client($loop);

$client->info()->then(function ($info) {
    echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}, 'printf');

$loop->run();
