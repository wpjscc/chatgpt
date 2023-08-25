<?php

namespace App\Services;

use React\Stream\WritableStreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;
use Wpjscc\React\Limiter\TokenBucket;
use function React\Async\async;
use function React\Async\await;

class BandwidthService
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


    public function __construct(ReadableStreamInterface $readable, WritableStreamInterface $writeable)
    {
       
        $this->writeable = $writeable;

        $readable->on('data', function ($chunk) {
            $this->status = 'data';
            echo $chunk;

            $this->buffer .= $chunk; 
            $this->send();
        });

        $readable->on('error', function ($error) {
            $this->status = 'error';
            $this->send();
        });

        $readable->on('close', function () {
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

       
        $async = \React\Async\async(function () use (&$async) {
            $this->isSendKB($async);
        });

        if ($force) {
            $async();
            return;
        }

        if ($this->status == 'data') {
            if ($this->isSending) {
                return;
            }
            $this->isSending = true;
            $async();
        } 
        elseif ($this->status == 'error') {
            if ($this->isSending) {
                return;
            }
            $this->isSending = true;

            $async();

        }
        elseif ($this->status == 'close') {
            if ($this->isSending) {
                return;
            }
            $this->isSending = true;

            $async();
        }

    }

    public function handleNoBuffer()
    {
        if ($this->status == 'close' || $this->status == 'error') {
            $this->writeable->end();
        }
    }

    public function setSendStrlen($sendStrlen)
    {
        $this->sendStrlen = $sendStrlen;
        return $this;
    }

    public function isSendKB($async)
    {
        if ($this->buffer) {


            // if ((strlen($this->buffer) * 1024) < ($this->bandwidth/1000) && $this->status == 'data') {
            //     $this->isSending = false;
            //     return;
            // }

            if ($this->sendStrlen) {
                $size = min($this->sendStrlen, strlen($this->buffer));
            } else {
                $size = strlen($this->buffer);
            }

            if ($size * 1024 <= $this->bandwidth) {

                await($this->bucket->removeTokens(1024 * $size));
                $content = substr($this->buffer, 0, $size);
                echo "hello 1-". $content."\n";

                $this->buffer = substr($this->buffer, $size);
                $this->writeable->write($content);
            } else {


                await($this->bucket->removeTokens($this->bandwidth));
                $size = $this->bandwidth / 1024;
               
                $content = substr($this->buffer, 0, $size);
                echo "hello 2-". $content."\n";
                $this->buffer = substr($this->buffer, $size);
                $this->writeable->write($content);
            }

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