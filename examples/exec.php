<?php
// this simple example executes a "sleep 2" within the given running container

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;
use Clue\React\Docker\ExecHelper;
use React\Stream\Stream;
use Clue\React\Buzz\Message\ResponseException;

$container = isset($argv[1]) ? $argv[1] : 'asd';

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$client->execCreate($container, array('Cmd' => array('sleep', '2'), 'AttachStdout' => true))->then(function ($info) use ($client) {
    echo 'Created with info: ' . json_encode($info) . PHP_EOL;

    return $client->execInspect($info['Id']);
})->then(function ($info) use ($client) {
    echo 'Inspected after creation: ' . json_encode($info, JSON_PRETTY_PRINT) . PHP_EOL;

    return $client->execStart($info['ID'], array())->then(function ($out) use ($client, $info) {
        echo 'Starting returned: ';
        var_dump($out);

        return $client->execInspect($info['ID']);
    });
})->then(function ($info) {
    echo 'Inspected after execution: ' . json_encode($info, JSON_PRETTY_PRINT) . PHP_EOL;
}, function (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;

    if ($e instanceof ResponseException) {
        echo 'Response: ' . $e->getResponse()->getBody() . PHP_EOL;
    }
});

$loop->run();
