<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\BucketManager;
use React\Stream\ThroughStream;
use Wpjscc\React\Limiter\TokenBucket;
use function Wpjscc\React\Limiter\getMilliseconds;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use App\Services\FileBandwidthService;

DEFINE('KB', 50);
define('BURST_RATE', 1024 * 1024 * 3 * KB);
define('FILL_RATE', 1024 * 1024 * KB);
$fileBandwidthService = new FileBandwidthService(1024*1024*getParam('--filekb', 300), 1024*1024* getParam('--filekb', 100), 1000);
$fileBandwidthService->run();

if (getParam('--every-minute-times')) {
    BucketManager::setNumber((int) getParam('--every-minute-times'));
}

BucketManager::addBucket(BucketManager::getNumber());
BucketManager::initLimiter(BucketManager::getNumber(), 'minute');

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


$app->get('/assets/{path}', function (Psr\Http\Message\ServerRequestInterface $request) use ($fileBandwidthService) {
    $path = '/assets/' . $request->getAttribute('path');
    if (file_exists(__DIR__ . $path)) {
        $fileType = getFileType(__DIR__ . $path);
        $contentType = 'application/javascript';
        if ($fileType == 'css') {
            $contentType = 'text/css';
        }
        $p = 0;
        $size = filesize(__DIR__ . $path);

        $bucket = new TokenBucket(BURST_RATE, FILL_RATE, 'sec');
        $bucket->setContent(BURST_RATE);

        $stream = new ThroughStream;
        $async = async(function () use (&$async, $stream, $path, &$p, $size, $bucket) {

            if ($size/1024 < KB) {
                await($bucket->removeTokens(1024 * 1024 * ceil($size/1024)));
                $stream->end(file_get_contents(__DIR__ . $path));
            } else {
                if (($size-$p)/1024 < KB) {
                    $start = getMilliseconds();
                    await($bucket->removeTokens(1024 * 1024 * ceil(($size-$p)/1024)));
                    $end = getMilliseconds();
                    var_dump($end-$start, ($size-$p));
                    $fp = fopen(__DIR__ . $path, 'r');
                    fseek($fp, $p);
                    $content = fread($fp, 1024 * KB);
                    $p += strlen($content);
                    fclose($fp);
                    $stream->end($content);
                } else {
                    $start = getMilliseconds();
                    await($bucket->removeTokens(1024 * 1024 * KB));
                    $end = getMilliseconds();
                    var_dump($end-$start, KB);
                    $fp = fopen(__DIR__ . $path, 'r');
                    fseek($fp, $p);
                    $content = fread($fp, 1024 * KB);
                    $p += strlen($content);
                    fclose($fp);
                    if ($p >= $size) {
                        $stream->end($content);
                    } else {
                        $stream->write($content);
                        $async();
                    }
                }
                
            }
        });



        // Loop::addTimer(0.001, $async);



        $fileBandwidthService->addStream($stream, __DIR__ . $path);


        return new React\Http\Message\Response(
            React\Http\Message\Response::STATUS_OK,
            array(
                'Content-Type' => $contentType,
                'Cache-Control' => 'max-age=3600'
            ),
            $stream
        );
    }
});

$app->run();
