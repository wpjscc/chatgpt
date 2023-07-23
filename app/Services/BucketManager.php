<?php

namespace App\Services;

use React\EventLoop\Loop;

class BucketManager
{
    protected static $buckets = [];

    protected static $number = 3;

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

    public static function setNumber($number)
    {
        static::$number = $number;
    }

    public static function getNumber()
    {
        return static::$number;
    }

}