<?php

// this simple example displays system wide information from Docker as a simple JSON

require __DIR__ . '/../vendor/autoload.php';

$client = new Clue\React\Docker\Client();

$client->info()->then(function ($info) {
    echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}, 'printf');
