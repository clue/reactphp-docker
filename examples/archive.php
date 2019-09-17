<?php

// this example shows how the containerArchiveStream() method returns a TAR stream,
// how it can be passed to a TAR decoder and how we can then pipe each
// individual file to the console output.

use Clue\CaretNotation\Encoder;
use Clue\React\Docker\Client;
use Clue\React\Tar\Decoder;
use React\Stream\ReadableStreamInterface;

require __DIR__ . '/../vendor/autoload.php';

$container = isset($argv[1]) ? $argv[1] : 'asd';
$path = isset($argv[2]) ? $argv[2] : '/etc/passwd';
echo 'Container "' . $container . '" dumping "' . $path . '" (pass as arguments to this example)' . PHP_EOL;

$loop = React\EventLoop\Factory::create();
$client = new Client($loop);

$stream = $client->containerArchiveStream($container, $path);

$tar = new Decoder();

// use caret notation for any control characters except \t, \r and \n
$caret = new Encoder("\t\r\n");

$tar->on('entry', function ($header, ReadableStreamInterface $file) use ($caret) {
    // write each entry to the console output
    echo '########## ' . $caret->encode($header['filename']) . ' ##########' . PHP_EOL;
    $file->on('data', function ($chunk) use ($caret) {
        echo $caret->encode($chunk);
    });
});

$tar->on('error', function ($e = null) {
    // should not be invoked, unless the stream is somehow interrupted
    echo 'ERROR processing tar stream' . PHP_EOL . $e;
});
$stream->on('error', function ($e = null) {
    // will be called if either parameter is invalid
    echo 'ERROR requesting stream' . PHP_EOL . $e;
});

$stream->pipe($tar);

$loop->run();
