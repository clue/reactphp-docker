<?php

// This example executes a command within the given running container and
// displays how fast it can receive its output.
//
// Before starting the benchmark, you have to start a container first, such as:
//
// $ docker run -it --rm --name=foo busybox sh
// $ php examples/benchmark-exec.php
// $ php examples/benchmark-exec.php foo echo -n hello
//
// Expect this to be significantly faster than the (totally unfair) equivalent:
//
// $ docker exec foo dd if=/dev/zero bs=1M count=1000 | dd of=/dev/null


require __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('xdebug')) {
    echo 'NOTICE: The "xdebug" extension is loaded, this has a major impact on performance.' . PHP_EOL;
}

$container = 'foo';
$cmd = array('dd', 'if=/dev/zero', 'bs=1M', 'count=1000');

if (isset($argv[1])) {
    $container = $argv[1];
    $cmd = array_slice($argv, 2);
}

$client = new Clue\React\Docker\Client();

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

        echo 'Received ' . $bytes . ' bytes in ' . round($time, 1) . 's => ' . round($bytes / $time / 1000000, 1) . ' MB/s' . PHP_EOL;
    });
}, 'printf');
