<?php

// this example shows how the containerExport() call returns a TAR stream
// and how we it can be piped into a output tar file.

use Clue\React\Docker\Client;
use React\Stream\Stream;

require __DIR__ . '/../vendor/autoload.php';

$container = isset($argv[1]) ? $argv[1] : 'asd';
$target = isset($argv[2]) ? $argv[2] : ($container . '.tar');
echo 'Exporting whole container "' . $container . '" to "' . $target .'" (pass as arguments to this example)' . PHP_EOL;

$loop = React\EventLoop\Factory::create();
$client = new Client($loop);

$stream = $client->containerExportStream($container);

$stream->on('error', function ($e = null) {
    // will be called if the container is invalid/does not exist
    echo 'ERROR requesting stream' . PHP_EOL . $e;
});

$out = new Stream(fopen($target, 'w'), $loop);
$out->pause();
$stream->pipe($out);

$loop->run();
