<?php

require __DIR__ . '/vendor/autoload.php';

use React\Stream\ThroughStream;
use React\EventLoop\Loop;

class BucketManager
{

    static $buckets = [];

    static $number = 3;

    public static function addBucket($default = 1)
    {
        if (count(static::$buckets) >= self::$number) {
            return;
        }

        for ($i=0; $i < $default; $i++) { 
            array_push(static::$buckets, 1);
        }

    }

    public static function getBucket()
    {
        if (count(static::$buckets) == 0) {
            return false;
        }

        Loop::get()->addTimer(1 * 60, function () {
            BucketManager::addBucket();
        });

        return array_pop(static::$buckets);
    }

}

if (getParam('--every-minute-times')) {
    BucketManager::$number = (int) getParam('--every-minute-times');
}

BucketManager::addBucket(BucketManager::$number);

var_dump(BucketManager::$buckets);

$http = new React\Http\HttpServer(
    new React\Http\Middleware\LimitConcurrentRequestsMiddleware(1), // 100 concurrent buffering handlers
    new React\Http\Middleware\RequestBodyBufferMiddleware(0.5 * 1024 * 1024), // 2 MiB per request
    function (Psr\Http\Message\ServerRequestInterface $request) {
        $connector = null;

        if (getParam('--proxy')) {
            $proxy = new \Clue\React\HttpProxy\ProxyConnector(getParam('--proxy'));
            $connector = new React\Socket\Connector(array(
                'tcp' => $proxy,
                'dns' => false
            ));
        }

        $client = (new \React\Http\Browser($connector))->withTimeout(getParam('--timeout', 10));
        $params = $request->getQueryParams();

        $query = $params['query'] ?? '';
        $messages = $params['messages'] ?? [];
        if ($messages && !is_array($messages)) {
            $messages = json_decode($messages, true);
        }
        var_dump($params);
        if (!$messages) {
            if ($query) {
                $messages = [
                    [
                        'role' => 'user',
                        'content' => $query
                    ]
                ];
            }
        } 


       
        $token = $params['token'] ?? (getParam('--token') ?: '');
        $isCustomeToken = ($params['token'] ?? '') ? true : false;
        
        $path = $request->getUri()->getPath();

        if ($path == '/') {
            return \React\Http\Message\Response::html(file_get_contents(__DIR__ . '/chat.html'));
        }

        if ($path == '/health') {
            return \React\Http\Message\Response::json([
                'is_healthy' => true,
            ]);
        }
        var_dump($path);

        if (strpos($path, "/assets") === 0) {
            if (file_exists(__DIR__ . $path)) {
                $fileType = getFileType(__DIR__ . $path);
                $contentType = 'application/javascript';
                if ($fileType == 'css') {
                    $contentType = 'text/css';
                }
                return new React\Http\Message\Response(
                    React\Http\Message\Response::STATUS_OK,
                    array(
                        'Content-Type' => $contentType,
                        'Cache-Control' => 'max-age=3600'
                    ),
                    file_get_contents(__DIR__ . $path)
                );
            }
        }

        $stream = new ThroughStream();

        if ($path != '/chatgpt') {
            Loop::get()->addTimer(1, function () use ($stream) {
                endStream($stream, '404');
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
        }


        if (!$messages || !$token) {
            Loop::get()->addTimer(1, function () use ($stream) {
                endStream($stream, 'not token');
            });
        }

        var_dump($query);
        echo "request-header:" . json_encode($request->getHeaders()) . "\n";


        $havBucket = true;
        if (!$isCustomeToken) {
            $havBucket = BucketManager::getBucket();

            // 用完了
            if ($havBucket === false) {
                Loop::get()->addTimer(1, function () use ($stream) {
                    endStream($stream, 'over '.BucketManager::$number .' times in one minute please wait one minute');
                });
            }
           
        }

        if ($messages && $token && $havBucket) {
            // https://platform.openai.com/docs/models/gpt-3-5
            // https://platform.openai.com/account/rate-limits
            // https://openai.com/pricing#language-models
            $data = [
                'model' => 'gpt-3.5-turbo',
                // 'model' => 'gpt-3.5-turbo-0613',// 可以函数调用--发布新的不会维护了
                'messages' => $messages,
                'stream' => true
            ];

            // 非自定义token 才限制，要不token用完了～
            if (!$isCustomeToken) {
                if (getParam('max-tokens')) {
                    $data['max_tokens'] = (int) getParam('max-tokens');
                }
            }

            $client->withRejectErrorResponse(false)->requestStreaming(
                'POST',
                'https://api.openai.com/v1/chat/completions',
                array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Cache-Control' => 'no-cache'
                ),
                json_encode($data)
            )->then(function (Psr\Http\Message\ResponseInterface $response) use ($stream) {
                echo "response";
                echo json_encode($response->getHeaders()) . "\n";
                $body = $response->getBody();
                // echo strval($body);
                assert($body instanceof \Psr\Http\Message\StreamInterface);
                assert($body instanceof \React\Stream\ReadableStreamInterface);

                if (strpos($response->getHeaderLine('Content-Type'), 'application/json') !== false) {

                    $body->on('data', function ($chunk) use ($stream) {
                        echo $chunk;
                        $stream->write(getData(addslashes($chunk)));
                    });

                    $body->on('error', function (Exception $e) {
                        echo 'Error: ' . $e->getMessage() . PHP_EOL;
                    });

                    $body->on('close', function () use ($stream) {
                        echo '[DONE]' . PHP_EOL;
                        endStream($stream, '[/?token=xxxxx](?token=xxxxx)');
                    });
                } else {
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
                }
            }, function (Exception $e) use ($stream) {
                echo 'Error: ' . $e->getMessage() . PHP_EOL;
                endStream($stream, $e->getMessage());
            });
        }


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
    }
);

function endStream($stream, $msg)
{
    $stream->write(getData($msg));
    $stream->write('data: [DONE]');
    $stream->end();
}

function getData($msg)
{
    return 'data: ' . json_encode(['choices' => [
        [
            'delta' => [
                'content' => $msg,
                'is_error' => true
            ]
        ]
    ]]) . "\n\n";
}

function getParam($key, $default = null)
{
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $key) !== false) {
            return explode('=', $arg)[1];
        }
    }
    return $default;
}

function getFileType($filename)
{
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if ($ext == 'css') {
        return 'css';
    } elseif ($ext == 'js') {
        return 'js';
    } else {
        return '';
    }
}

$socket = new React\Socket\SocketServer('0.0.0.0:' . (getParam('--port') ?: '8000'));
$http->listen($socket);

