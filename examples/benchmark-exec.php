<?php

// this simple example executes a command within the given running container and
// displays how fast it can receive its output.
// expect this to be significantly faster than the (totally unfair) equivalent:
// $ docker exec asd dd if=/dev/zero bs=1M count=1000 | dd of=/dev/null

use Clue\React\Docker\Client;

require __DIR__ . '/../vendor/autoload.php';

$container = 'asd';
$cmd = array('dd', 'if=/dev/zero', 'bs=1M', 'count=1000');

if (isset($argv[1])) {
    $container = $argv[1];
    $cmd = array_slice($argv, 2);
}

$loop = React\EventLoop\Factory::create();
$client = new Client($loop);

$client->execCreate($container, $cmd)->then(function ($info) use ($client) {
    $stream = $client->execStartStream($info['Id'], true);

    $start = microtime(true);
    $bytes = 0;
    $stream->on('data', function ($chunk) use (&$bytes) {
        $bytes += strlen($chunk);
    });

    $stream->on('error', 'printf');

    // show stats when stream ends
    $stream->on('close', function () use ($client, &$bytes, $start) {
        $time = microtime(true) - $start;

        echo 'Received ' . $bytes . ' bytes in ' . round($time, 1) . 's => ' . round($bytes / $time / 1024 / 1024, 1) . ' MiB/s' . PHP_EOL;
    });
}, 'printf');

$loop->run();
