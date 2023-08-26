<?php

namespace App\Services;

use React\Stream\WritableStreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;
use Wpjscc\React\Limiter\TokenBucket;
use function Wpjscc\React\Limiter\getMilliseconds;
use function React\Async\async;
use function React\Async\await;

class StreamBandwidthService
{
    protected WritableStreamInterface $writeable;

    protected int $maxBandwidth;
    protected int $bandwidth;
    protected int | float  $kb;
    protected int $sendStrlen = 0;

    protected TokenBucket $bucket;


    protected $buffer;

    protected $status;

    protected $isSending = false;


    public static $start = 0;

    public static $startSend = 0;

    public static $end = 0;

    private $ms = 0;
    private $size = 0;

    private $starting = false;


    public function __construct(ReadableStreamInterface $readable, WritableStreamInterface $writeable)
    {

        static::$startSend = getMilliseconds();

        $this->writeable = $writeable;

        $_ms = getMilliseconds();
        $_mss = 0;
        $readable->on('data', function ($chunk) use (&$_ms, &$_mss) {
            $cms = getMilliseconds();
            $_mss += ($cms-$_ms);
            $_ms = $cms;

            $this->status = 'data';
            $this->buffer .= $chunk; 
            $this->send();
        });

        $readable->on('error', function ($error) use (&$_ms, &$_mss) {
            $cms = getMilliseconds();
            $_mss += ($cms-$_ms);
            $_ms = $cms;


            $this->status = 'error';
            $this->send();
        });

        $readable->on('close', function () use (&$_ms, &$_mss) {
            $cms = getMilliseconds();
            $_mss += ($cms-$_ms);
            $_ms = $cms;

            static::$end = getMilliseconds();
            echo "request-use: ". (static::$startSend-static::$start) . "\n";
            echo "response-use: ". $_mss . "\n";
            echo "request-response-end: ". (static::$end-static::$start) . "\n";


            $this->status = 'close';
            $this->send();
        });


    }

    public function setContent($buffer)
    {
        $this->buffer .= $buffer;
        return $this;
    }

    public function setBandwidth($maxBandwidth, $bandwidth, $interval)
    {

        $this->maxBandwidth = $maxBandwidth;
        $this->bandwidth = $bandwidth;

        $this->kb = $bandwidth / 1024 / 1024;

        $this->bucket = new TokenBucket($maxBandwidth, $bandwidth, $interval);
        return $this;
    }

    public function send($force = false)
    {
        if ($this->sendStrlen) {
            if ($this->sendStrlen/1024 > $this->kb) {
                throw new \Error("{$this->sendStrlen} sendStrlen/1024  > kb {$this->kb}");
            }
        }

        $start = getMilliseconds();
        
        $async = \React\Async\async(function () use (&$async, $start) {
            $this->isSendKB($async);
            $end = getMilliseconds();
            // echo "time8: ". ($end - $start) . "\n";
        });

        if ($force) {
            $async();
            return;
        }

        if ($this->isSending) {
            return;
        }
        $this->isSending = true;
        $async();

    }

    public function handleNoBuffer()
    {
        if ($this->status == 'close' || $this->status == 'error') {
            $this->writeable->end();
            echo "true-send: ". $this->ms . "\n";
            echo "true-size: ". $this->size . "\n";
            echo "时间误差: ".($this->ms/1000 * 1.8) ."ms\n";
            echo "没去掉时间误差,减去true-size 为". ($this->ms/1000 * $this->kb * 1024-$this->size) . "\n";
            echo "去掉时间误差,减去true-size 为". (($this->ms-($this->ms/1000 * 1.8))/1000* $this->kb * 1024-$this->size) . "\n";
        }
    }

    public function setSendStrlen($sendStrlen)
    {
        $this->sendStrlen = $sendStrlen;
        return $this;
    }

    public function isSendKB($async)
    {
        static $isclose = false;
        if ($this->buffer) {

            if ($this->status == 'close' && !$isclose) {
                $isclose = true;
                echo "total:size-". strlen($this->buffer) . "\n";
                echo "had-send:size-". $this->size . "\n";
            }

            if ($this->sendStrlen) {
                $size = min($this->sendStrlen, strlen($this->buffer));
            } else {
                $size = strlen($this->buffer);
            }

            if ($size * 1024 <= $this->bandwidth) {
                $start = getMilliseconds();
                await($this->bucket->removeTokens(1024 * $size));
               
                $content = substr($this->buffer, 0, $size);
                $this->buffer = substr($this->buffer, $size);
                $this->writeable->write($content);

                $end = getMilliseconds();
                $this->ms += ($end - $start);

            } else {
                $start = getMilliseconds();
                await($this->bucket->removeTokens($this->bandwidth));
               

                $size = $this->bandwidth / 1024;
                $content = substr($this->buffer, 0, $size);

                $this->buffer = substr($this->buffer, $size);
                $this->writeable->write($content);
                
                $end = getMilliseconds();

                $this->ms += ($end - $start);
            }

            $this->size += $size;


            if ($this->buffer) {
                $async();
            } else {
                $this->isSending = false;
                $this->handleNoBuffer();
            }
        } else {
            $this->isSending = false;
            $this->handleNoBuffer();
        }
    }



}