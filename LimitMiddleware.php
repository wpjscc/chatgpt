<?php


class LimitMiddleware 
{
    public $q;

    public function __construct()
    {

        $this->q =new \Clue\React\Mq\Queue(1, 1, function($request, $next){
            return \React\Promise\resolve($next($request));
        });
       
    }

    public function __invoke($request, $next)
    {
        return ($this->q)($request, $next);
    }
}