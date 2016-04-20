<?php

namespace Clue\React\Docker;

use React\EventLoop\LoopInterface;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Io\Sender;

/**
 * The Factory is responsible for creating your Client instance.
 * It also registers everything with the main EventLoop.
 */
class Factory
{
    private $loop;
    private $browser;

    /**
     * Instantiate the Factory
     *
     * If you need custom DNS, SSL/TLS or proxy settings, you can explicitly
     * pass a custom Browser instance.
     *
     * @param LoopInterface $loop    the event loop
     * @param null|Browser  $browser (optional) custom Browser instance to use
     */
    public function __construct(LoopInterface $loop, Browser $browser = null)
    {
        if ($browser === null) {
            $browser = new Browser($loop);
        }

        $this->loop = $loop;
        $this->browser = $browser;
    }

    /**
     * Creates a new Client instance and helps with constructing the right Browser object for the given remote URL
     *
     * @param null|string $url (optional) URL to your (local) Docker daemon, defaults to using local unix domain socket path
     * @return Client
     */
    public function createClient($url = null)
    {
        if ($url === null) {
            $url = 'unix:///var/run/docker.sock';
        }

        $browser = $this->browser;

        if (substr($url, 0, 7) === 'unix://') {
            // send everything through a local unix domain socket
            $browser = $this->browser->withSender(
                Sender::createFromLoopUnix($this->loop, $url)
            );

            // pretend all HTTP URLs to be on localhost
            $url = 'http://localhost/';
        }

        return new Client($browser->withBase($url));
    }
}
