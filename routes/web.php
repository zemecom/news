<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health/live', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/health/ready', function (AMQPStreamConnection $amqp) {
    $checks = [];

    try {
        DB::connection()->getPdo();
        $checks['db'] = 'ok';
    } catch (\Throwable $e) {
        $checks['db'] = 'fail';
    }

    try {
        Redis::connection()->ping();
        $checks['redis'] = 'ok';
    } catch (\Throwable $e) {
        $checks['redis'] = 'fail';
    }

    try {
        $channel = $amqp->channel();
        $channel->close();
        $checks['rabbitmq'] = 'ok';
    } catch (\Throwable $e) {
        $checks['rabbitmq'] = 'fail';
    }

    $allOk = ! in_array('fail', $checks, true);
    return response()->json($checks, $allOk ? 200 : 503);
});
