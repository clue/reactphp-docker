<?php

// this example shows how the containerArchiveStream() method returns a TAR stream,
// how it can be passed to a TAR decoder and how we can then pipe each
// individual file to the console output.

require __DIR__ . '/../vendor/autoload.php';

$container = isset($argv[1]) ? $argv[1] : 'asd';
$path = isset($argv[2]) ? $argv[2] : '/etc/passwd';
echo 'Container "' . $container . '" dumping "' . $path . '" (pass as arguments to this example)' . PHP_EOL;

$client = new Clue\React\Docker\Client();

$stream = $client->containerArchiveStream($container, $path);

$tar = new Clue\React\Tar\Decoder();

$tar->on('entry', function ($header, React\Stream\ReadableStreamInterface $file) {
    // write each entry to the console output
    echo '########## ' . $header['filename'] . ' ##########' . PHP_EOL;
    $file->on('data', function ($chunk) {
        // escape control characters except \t, \r and \n
        echo addcslashes($chunk, "\x00..\x08\x0b\x0c\x0e..\x1f\x7f");
    });
});

$tar->on('error', function (Exception $e) {
    // should not be invoked, unless the stream is somehow interrupted
    echo 'Error in TAR stream: ' . $e->getMessage() . PHP_EOL;
});

$stream->on('error', function (Exception $e) {
    // will be called if either parameter is invalid
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$stream->pipe($tar);
