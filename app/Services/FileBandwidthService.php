<?php

namespace App\Services;

use Wpjscc\React\Limiter\TokenBucket;
use React\EventLoop\Loop;
use function React\Async\async;
use function React\Async\await;
use function Wpjscc\React\Limiter\getMilliseconds;
use React\Filesystem\Factory;

class FileBandwidthService
{

    protected $maxBandwidth;
    protected $bandwidth;
    protected $kb;

    protected TokenBucket $bucket;

    protected $streams = [];

    protected $is_run = false;


    protected $filesystem;

    
    public function __construct(int $maxBandwidth, int $bandwidth, int $interval)
    {
        $this->maxBandwidth = $maxBandwidth;
        $this->bandwidth = $bandwidth;

        $this->kb = $bandwidth / 1024 / 1024;

        $this->bucket = new TokenBucket($maxBandwidth, $bandwidth, $interval);

        $this->filesystem = Factory::create();
    }

    public function addStream($stream, $fiepath)
    {
        // array_push($this->streams, [
        //     'stream' => $stream,
        //     'filepath' => $fiepath,
        //     'filesize' => filesize($fiepath),
        //     'position' => 0,
        // ]);
        $this->runStream([
            'stream' => $stream,
            'filepath' => $fiepath,
            'filesize' => filesize($fiepath),
            'position' => 0,
        ]);
        return $this;
    }

    public function run()
    {   
        if ($this->is_run) {
            return;
        }
        $this->is_run = true;
        function formatBytes($bytes, $precision = 2) {

            return $bytes/1024/1024 ."MB";
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
        
            $bytes /= pow(1024, $pow);
        
            return round($bytes, $precision) . ' ' . $units[$pow];
        }
        
        // Loop::addPeriodicTimer(0.001, function () {

        //     $this->send();
        // });
        // Loop::addPeriodicTimer(1, function () {
        //     echo "当前脚本使用的内存量：". formatBytes(memory_get_usage())."\n";
        //     echo "脚本执行过程中内存使用的峰值：". formatBytes(memory_get_peak_usage())."\n";
        // });

    }

    protected function runStream($stream)
    {

            $sendStream = function ($stream) use (&$sendStream) {
                return async(function () use ($stream, $sendStream) {
                    $path = $stream['filepath'];
                    $p = $stream['position'];
                    $size = $stream['filesize'];
                    $writeable = $stream['stream'];

                    if ($writeable->isWritable() === false) {
                        return;
                    }
                    $bucket = $this->bucket;
    
    
                    if ($size/1024 < $this->kb) {

                        await($bucket->removeTokens(1024 * 1024 * ceil($size/1024)));
                        $content = await($this->filesystem->file($path)->getContents(0, $size));
                        $writeable->end($content);
                    } else {
                        if (($size-$p)/1024 < $this->kb) {

                            $start = getMilliseconds();
    
                            await($bucket->removeTokens(1024 * 1024 * ceil(($size-$p)/1024)));
                            $content = await($this->filesystem->file($path)->getContents($p, 1024 * $this->kb));

                            $end = getMilliseconds();
                            var_dump($end-$start, ($size-$p));
                            $p += strlen($content);
                            $writeable->end($content);
                        } else {

                            $start = getMilliseconds();
                            await($bucket->removeTokens(1024 * 1024 * $this->kb));
                            $content = await($this->filesystem->file($path)->getContents($p, 1024 * $this->kb));
                            $end = getMilliseconds();
                            var_dump($end-$start, $this->kb);
                            $p += strlen($content);
                            if ($p >= $size) {

                                $writeable->end($content);
                            } else {
                                $writeable->write($content);
                                $stream['position'] = $p;
                                await($sendStream($stream));
                            }
                        }
                        
                    }
                })();
            };

            Loop::futureTick(function () use ($stream, $sendStream) {
                $sendStream($stream);
            });
    }
}