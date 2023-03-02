<?php

require __DIR__.'/vendor/autoload.php';

use React\Stream\ThroughStream;


$http = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use ($argv) {


    $client = (new \React\Http\Browser())->withTimeout(10.0);
    $params = $request->getQueryParams();
    $query = $params['query'] ?? '';
    $token = $params['token'] ?? ($argv[2] ?? '');
    $path = $request->getUri()->getPath();

    if ($path == '/') {
        return \React\Http\Message\Response::html(file_get_contents(__DIR__.'/chat.html'));
    }

    if ($path == '/tailwindcss.js') {
        return \React\Http\Message\Response::plaintext(file_get_contents(__DIR__.'/tailwindcss.js'));
    }

    if ($path == '/alpinejs.js') {
        return \React\Http\Message\Response::plaintext(file_get_contents(__DIR__.'/alpinejs.js'));
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
        'https://api.openai.com/v1/completions',
         array(
        'Authorization' => 'Bearer '.$token,
        'Content-Type' => 'application/json',
        'Accept' => 'text/event-stream',
        'Cache-Control' => 'no-cache'
    ), json_encode([
        'model' => 'text-davinci-003',
        // 'model' => 'text-davinci-002-render',
        'prompt' => $query,
        'temperature' => 0,
        "max_tokens" => 150,
        "frequency_penalty" => 0,
        "presence_penalty" => 0.6,
        "stream" => true,
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
        $stream->write('data: {"id":"cmpl-6pbgmdTJZWBk9fnZ9hPAq53XVvMl0","object":"text_completion","created":1677757624,"choices":[{"text":"373","index":0,"logprobs":null,"finish_reason":"length"}],"model":"text-davinci-003"}');
        $stream->write('data: [DONE]');
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


