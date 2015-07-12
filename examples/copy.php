<?php
// this example shows how the containerCopy() call returns a TAR stream,
// how it can be passed to a TAR decoder and how we can then pipe each
// individual file to the console output.

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;
use Clue\React\Tar\Decoder;
use React\Stream\ReadableStreamInterface;
use Clue\CaretNotation\Encoder;

$container = isset($argv[1]) ? $argv[1] : 'asd';
$file = isset($argv[2]) ? $argv[2] : '/etc/passwd';
echo 'Container "' . $container . '" dumping "' . $file . '" (pass as arguments to this example)' . PHP_EOL;

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$stream = $client->containerCopyStream($container, array('Resource' => $file));

$tar = new Decoder();

// use caret notation for any control characters expect \t, \r and \n
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
