<?php

// this example shows how the imagePush() call can be used to publish a given image.
// this requires authorization and this example includes some invalid defaults.

require __DIR__ . '/../vendor/autoload.php';

$image = isset($argv[1]) ? $argv[1] : 'asd';
$auth = json_decode('{"username": "string", "password": "string", "email": "string", "serveraddress" : "string", "auth": ""}');
echo 'Pushing image "' . $image . '" (pass as argument to this example)' . PHP_EOL;

$client = new Clue\React\Docker\Client();

$client->imagePush($image, null, null, $auth)->then(function ($result) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}, 'printf');
