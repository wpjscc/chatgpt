<?php

require __DIR__.'/vendor/autoload.php';

use React\Stream\ThroughStream;


$http = new React\Http\HttpServer(
    new React\Http\Middleware\LimitConcurrentRequestsMiddleware(10), // 100 concurrent buffering handlers
    new React\Http\Middleware\RequestBodyBufferMiddleware(0.5 * 1024 * 1024), // 2 MiB per request
    new React\Http\Middleware\RequestBodyParserMiddleware(),
    new React\Http\Middleware\LimitConcurrentRequestsMiddleware(1), 
function (Psr\Http\Message\ServerRequestInterface $request) use ($argv) {
    $connector = null;

    if ($argv[3] ?? '') {
        $proxy = new \Clue\React\HttpProxy\ProxyConnector($argv[3]);
        $connector = new React\Socket\Connector(array(
            'tcp' => $proxy,
            'dns' => false
        ));
    }

    $client = (new \React\Http\Browser($connector))->withTimeout(10.0);
    $params = $request->getQueryParams();
    $query = $params['query'] ?? '';
    $token = $params['token'] ?? ($argv[2] ?? '');
    $path = $request->getUri()->getPath();

    if ($path == '/') {
        return \React\Http\Message\Response::html(file_get_contents(__DIR__.'/chat.html'));
    }

    if ($path == '/health') {
        return \React\Http\Message\Response::json([
            'is_healthy' => true,
        ]);
    }
    var_dump($path);

    if (in_array($path, ['/assets/tailwindcss.js', '/assets/alpinejs.js', '/assets/marked.min.js' ,'/assets/prism.js', '/assets/prism-treeview.js'])) {
        return new React\Http\Message\Response(
            React\Http\Message\Response::STATUS_OK,
            array(
                'Content-Type' => 'application/javascript',
                'Cache-Control' => 'max-age=3600'
            ),
            file_get_contents(__DIR__.$path)
        );
    }
    
    if (in_array($path, ['/assets/prism.css', '/assets/prism-light.css'])) {
        var_dump('exist:css:'. $path);
        return new React\Http\Message\Response(
            React\Http\Message\Response::STATUS_OK,
            array(
                'Content-Type' => 'text/css',
                'Cache-Control' => 'max-age=3600'
            ),
            file_get_contents(__DIR__.$path)
        );
    }


    if ($path != '/chatgpt') {
        return \React\Http\Message\Response::plaintext('not found');
    }


    if (!$query) {
        return \React\Http\Message\Response::plaintext('not query');
    }

    if (!$token) {
        return \React\Http\Message\Response::plaintext('not token');
    }

    var_dump($query);
    $stream = new ThroughStream();

    $client->withRejectErrorResponse(false)->requestStreaming(
        'POST',
        'https://api.openai.com/v1/chat/completions',
        array(
        'Authorization' => 'Bearer '.$token,
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-cache'
    ), json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'user',
                'content' => $query
            ]
        ],
        'stream' => true
    ]))->then(function (Psr\Http\Message\ResponseInterface $response) use ($stream) {
        echo "response";
        echo json_encode($response->getHeaders())."\n";
        $body = $response->getBody();
        // echo strval($body);
        assert($body instanceof \Psr\Http\Message\StreamInterface);
        assert($body instanceof \React\Stream\ReadableStreamInterface);
    
        $body->on('data', function ($chunk) use ($stream) {
            echo $chunk;
            $stream->write($chunk);
        });
    
        $body->on('error', function (Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
        });
    
        $body->on('close', function () use ($stream) {
            echo '[DONE]' . PHP_EOL;
            $stream->end();
        });
    
    }, function (Exception $e) use ($stream) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
        $data = json_encode(['choices' => [
            [
                'delta' => [
                    'content' => $e->getMessage()
                ]
            ]
        ]]);
        $stream->write('data: '.$data);
        $stream->write("\n\n");
        $stream->write('data: [DONE]');
        $stream->write("\n\n");
        $stream->end();
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


