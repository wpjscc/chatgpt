<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use React\Stream\ThroughStream;
use React\EventLoop\Loop;
use App\Services\BucketManager;
use App\Services\ChatGPTService;
use function React\Async\async;
use function React\Async\await;

class ChatGPTController
{
    public function __invoke(ServerRequestInterface $request)
    {
        return async(function () use ($request) {
            return $this->handleRequest($request);
        })();


    }

    protected function handleRequest($request)
    {

        $params = $request->getQueryParams();

        $query = $params['query'] ?? '';
        $messages = $params['messages'] ?? [];
        if ($messages && !is_array($messages)) {
            $messages = json_decode($messages, true);
        }
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

        $stream = new ThroughStream();

        if (!$messages || !$token) {
            Loop::get()->addTimer(0.01, function () use ($stream) {
                endEventStream($stream, 'not token');
            });
        }

        $remainingRequests = 0;
        $havBucket = true;
        if (!$isCustomeToken) {
            $havBucket = BucketManager::getBucket();
            $remainingRequests = await(BucketManager::getLimiter()->removeTokens(1));

            // 用完了
            if ($remainingRequests < 0) {
            // if ($havBucket === false) {
                echo 'request-header:'. json_encode($request->getHeaders(), JSON_UNESCAPED_UNICODE) . "over\n";

                Loop::get()->addTimer(0.01, function () use ($stream) {
                    endEventStream($stream, 'over '.BucketManager::getNumber() .' times in one minute please wait one minute');
                });
            }
           
        }


        if ($messages && $token && $remainingRequests >= 0) {
            // https://platform.openai.com/docs/models/gpt-3-5
            // https://platform.openai.com/account/rate-limits
            // https://openai.com/pricing#language-models
            $data = [
                'model' => 'gpt-4o-mini',
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
            echo 'request-header:'. json_encode($request->getHeaders(), JSON_UNESCAPED_UNICODE) . "\n";
            echo 'request-data:'.json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";

            (new ChatGPTService())->handle($stream, $data, $token);

        }

        return new \React\Http\Message\Response(
            \React\Http\Message\Response::STATUS_OK,
            array(
                'Content-Type' => 'text/event-stream',
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'no-cache'
            ),
            $stream
        );
    }
}