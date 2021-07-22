<?php

// This example executes a command within a new container and displays how fast
// it can receive its output.
//
// $ php examples/benchmark-attach.php
// $ php examples/benchmark-attach.php busybox echo -n hello
//
// Expect this to be noticeably faster than the (totally unfair) equivalent:
//
// $ docker run -i --rm --log-driver=none busybox dd if=/dev/zero bs=1M count=1000 status=none | dd of=/dev/null

use Clue\React\Docker\Client;
use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('xdebug')) {
    echo 'NOTICE: The "xdebug" extension is loaded, this has a major impact on performance.' . PHP_EOL;
}

$image = 'busybox';
$cmd = array('dd', 'if=/dev/zero', 'bs=1M', 'count=1000', 'status=none');

if (isset($argv[1])) {
    $image = $argv[1];
    $cmd = array_slice($argv, 2);
}

$client = new Client();

$client->containerCreate(array(
    'Image' => $image,
    'Cmd' => $cmd,
    'Tty' => false,
    'HostConfig' => array(
        'LogConfig' => array(
            'Type' => 'none'
        )
    )
))->then(function ($container) use ($client) {
    $stream = $client->containerAttachStream($container['Id'], false, true);

    // we're creating the container without a log, so first wait for attach stream before starting
    Loop::addTimer(0.1, function () use ($client, $container) {
        $client->containerStart($container['Id'])->then(null, 'printf');
    });

    $start = microtime(true);
    $bytes = 0;
    $stream->on('data', function ($chunk) use (&$bytes) {
        $bytes += strlen($chunk);
    });

    $stream->on('error', 'printf');

    // show stats when stream ends
    $stream->on('close', function () use ($client, &$bytes, $start, $container) {
        $time = microtime(true) - $start;
        $client->containerRemove($container['Id'])->then(null, 'printf');

        echo 'Received ' . $bytes . ' bytes in ' . round($time, 1) . 's => ' . round($bytes / $time / 1000000, 1) . ' MB/s' . PHP_EOL;
    });
}, 'printf');
