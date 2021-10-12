<?php

// this example shows how the containerExport() call returns a TAR stream
// and how we it can be piped into a output tar file.

require __DIR__ . '/../vendor/autoload.php';

if (DIRECTORY_SEPARATOR === '\\') {
    exit('File I/O not supported on Windows' . PHP_EOL);
}

$container = isset($argv[1]) ? $argv[1] : 'asd';
$target = isset($argv[2]) ? $argv[2] : ($container . '.tar');
echo 'Exporting whole container "' . $container . '" to "' . $target .'" (pass as arguments to this example)' . PHP_EOL;

$client = new Clue\React\Docker\Client();

$stream = $client->containerExportStream($container);

$stream->on('error', function (Exception $e) {
    // will be called if the container is invalid/does not exist
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$out = new React\Stream\WritableResourceStream(fopen($target, 'w'));
$stream->pipe($out);
