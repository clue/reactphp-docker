<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Factory as LoopFactory;
use Clue\React\Docker\Factory;

$loop = LoopFactory::create();

$factory = new Factory($loop);
$client = $factory->createClient();

$client->info()->then('var_dump', 'var_dump');

$loop->run();
