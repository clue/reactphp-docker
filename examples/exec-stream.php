<?php
// this example executes some commands within the given running container and
// displays the streaming output as it happens.

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;
use React\Stream\Stream;

$container = 'asd';
//$cmd = array('echo', 'hello world');
//$cmd = array('sleep', '2');
$cmd = array('sh', '-c', 'echo -n hello && sleep 1 && echo world && sleep 1 && env');
//$cmd = array('cat', 'invalid-path');

if (isset($argv[1])) {
    $container = $argv[1];
    $cmd = array_slice($argv, 2);
}

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$out = new Stream(STDOUT, $loop);
$out->pause();

$client->execCreate($container, array('Cmd' => $cmd, 'AttachStdout' => true, 'AttachStderr' => true, 'Tty' => true))->then(function ($info) use ($client, $out) {
    $stream = $client->execStartStream($info['Id'], array('Tty' => true));
    $stream->pipe($out);

    $stream->on('error', 'printf');

    // exit with error code of executed command once it closes
    $stream->on('close', function () use ($client, $info) {
        $client->execInspect($info['Id'])->then(function ($info) {
            exit($info['ExitCode']);
        }, 'printf');
    });
}, 'printf');

$loop->run();
