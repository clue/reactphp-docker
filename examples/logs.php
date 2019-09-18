<?php

// this example shows how the containerLogs() call can be used to get the logs of the given container.
// demonstrates the deferred logs API, which can be used to dump the logs in one go

use Clue\CaretNotation\Encoder;
use Clue\React\Docker\Client;

require __DIR__ . '/../vendor/autoload.php';

$container = isset($argv[1]) ? $argv[1] : 'asd';
echo 'Dumping logs (last 100 lines) of container "' . $container . '" (pass as argument to this example)' . PHP_EOL;

$loop = React\EventLoop\Factory::create();
$client = new Client($loop);

$client->containerLogs($container, false, true, true, 0, false, 100)->then(
    function ($logs) {
        echo 'Received the following logs:' . PHP_EOL;

        // escape control characters (dumping logs of vi/nano etc.)
        $caret = new Encoder("\t\r\n");
        echo $caret->encode($logs);
    },
    function ($error) use ($container) {
        echo <<<EOT
An error occured while trying to access the logs.

Have you tried running the following command?

    $ docker run -i --name=$container busybox dmesg

Here's the error log:

$error
EOT;
    }
);

$loop->run();
