<?php

namespace App\Services;

use Wpjscc\React\Limiter\TokenBucket;
use function Wpjscc\React\Limiter\getMilliseconds;

class ChatGPTService
{
    public function handle($stream, $data, $token)
    {
        $connector = null;

        if (getParam('--proxy')) {
            $proxy = new \Clue\React\HttpProxy\ProxyConnector(getParam('--proxy'));
            $connector = new \React\Socket\Connector(array(
                'tcp' => $proxy,
                'dns' => false
            ));
        }

        $client = (new \React\Http\Browser($connector))->withTimeout(getParam('--timeout', 10));
        \App\Services\BandwidthService::$start = getMilliseconds();
        $client->withRejectErrorResponse(false)->requestStreaming(
            'POST',
            'https://api.openai.com/v1/chat/completions',
            array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache'
            ),
            json_encode($data)
        )->then(function (\Psr\Http\Message\ResponseInterface $response) use ($stream) {
            echo "response";
            echo json_encode($response->getHeaders()) . "\n";
            $body = $response->getBody();
            // echo strval($body);
            assert($body instanceof \Psr\Http\Message\StreamInterface);
            assert($body instanceof \React\Stream\ReadableStreamInterface);

            if (strpos($response->getHeaderLine('Content-Type'), 'application/json') !== false) {

                $body->on('data', function ($chunk) use ($stream) {
                    echo $chunk;
                    $stream->write(getEventStreamData(addslashes($chunk)));
                });

                $body->on('error', function (\Exception $e) {
                    echo 'Error: ' . $e->getMessage() . PHP_EOL;
                });

                $body->on('close', function () use ($stream) {
                    echo '[DONE]' . PHP_EOL;
                    endEventStream($stream, '[/?token=xxxxx](?token=xxxxx)');
                });
            } else {
                
               (new \App\Services\StreamBandwidthService($body, $stream))
               ->setBandwidth(1024 * 1024 * getParam('--kb', 1), 1024 * 1024 * getParam('--kb', 1), 1000)
               ->setSendStrlen(1)
               ->send(true);
                // $body->on('data', function ($chunk) use ($stream) {
                //     echo $chunk;
                //     $stream->write($chunk);
                // });

                // $body->on('error', function (\Exception $e) {
                //     echo 'Error: ' . $e->getMessage() . PHP_EOL;
                // });

                // $body->on('close', function () use ($stream) {
                //     echo '[DONE]' . PHP_EOL;
                //     $stream->end();
                // });
            }
        }, function (\Exception $e) use ($stream) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
            endEventStream($stream, $e->getMessage());
        });
    }
}