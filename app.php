<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\BucketManager;


if (getParam('--every-minute-times')) {
    BucketManager::setNumber((int) getParam('--every-minute-times'));
}

BucketManager::addBucket(BucketManager::getNumber());


$app = new FrameworkX\App(
    new React\Http\Middleware\LimitConcurrentRequestsMiddleware(1), // 100 concurrent buffering handlers
    new React\Http\Middleware\RequestBodyBufferMiddleware(0.5 * 1024 * 1024), // 2 MiB per request
);


$app->get('/', function () {
    return \React\Http\Message\Response::html(file_get_contents(__DIR__ . '/chat.html'));
});
$app->get('/health', function () {
    return \React\Http\Message\Response::json([
        'is_healthy' => true,
    ]);
});

$app->get('/chatgpt', new App\Controllers\ChatGPTController());


$app->get('/assets/{path}', function (Psr\Http\Message\ServerRequestInterface $request) {
    $path = '/assets/'.$request->getAttribute('path');
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
});

$app->run();


