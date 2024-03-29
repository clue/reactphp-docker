<?php

// this simple example executes a "sleep 2" within the given running container

require __DIR__ . '/../vendor/autoload.php';

$container = 'asd';
//$cmd = array('echo', 'hello world');
//$cmd = array('sleep', '2');
$cmd = array('sh', '-c', 'echo -n hello && sleep 1 && echo world && sleep 1 && env');
//$cmd = array('cat', 'invalid-path');

if (isset($argv[1])) {
    $container = $argv[1];
    $cmd = array_slice($argv, 2);
}

$client = new Clue\React\Docker\Client();

$client->execCreate($container, $cmd)->then(function ($info) use ($client) {
    echo 'Created with info: ' . json_encode($info) . PHP_EOL;

    return $client->execInspect($info['Id']);
})->then(function ($info) use ($client) {
    echo 'Inspected after creation: ' . json_encode($info, JSON_PRETTY_PRINT) . PHP_EOL;

    return $client->execStart($info['ID'])->then(function ($out) use ($client, $info) {
        echo 'Starting returned: ';
        var_dump($out);

        return $client->execInspect($info['ID']);
    });
})->then(function ($info) {
    echo 'Inspected after execution: ' . json_encode($info, JSON_PRETTY_PRINT) . PHP_EOL;
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
