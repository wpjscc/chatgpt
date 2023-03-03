<?php

require __DIR__.'/vendor/autoload.php';

use React\Stream\ThroughStream;
use React\EventLoop\Loop;

$http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use ($argv) {

    $stream = new ThroughStream();
    $loop = Loop::get();
    $path = $request->getUri()->getPath();

    if ($path == '/') {
        return \React\Http\Message\Response::html(file_get_contents(__DIR__.'/eventsource.html'));
    }

    $n = 3;
    $loop->addPeriodicTimer(1.0, function ($timer) use ( $loop, &$n, $stream) {
        if ($n > 0) {
            --$n;
            // $stream->write("event: ping\n");
            $stream->write('data: {"time": "' . date('Y-m-d H:i:s') . '"}');
            $stream->write("\n\n");
        } else {
            $loop->cancelTimer($timer);
            $stream->end();
        }
    });
    // stop timer if stream is closed (such as when connection is closed)
    $stream->on('close', function () {
       
    });

    return new React\Http\Message\Response(
        React\Http\Message\Response::STATUS_OK,
        array(
            'Content-Type' => 'text/event-stream',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache'
        ),
        $stream
    );
});

$socket = new React\Socket\SocketServer('0.0.0.0:'.($argv[1] ?? '8000'));
$http->listen($socket);


