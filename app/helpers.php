<?php

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

function endEventStream($stream, $msg)
{
    $stream->write(getEventStreamData($msg));
    $stream->write('data: [DONE]');
    $stream->end();
}

function getEventStreamData($msg)
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