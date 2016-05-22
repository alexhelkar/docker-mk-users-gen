<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app['redis'] = function () {

    $client = new Predis\Client([
        'scheme' => 'tcp',
        'host' => 'redis',
        'port' => 6379,
    ]);

    while (!$client->getConnection()->isConnected()) {
        $client->getConnection()->connect();
    }

    return $client;
};


$app->get('/', function (Application $app, Request $request) {

    /** @var Predis\Client $redis */
    $redis = $app['redis'];
    $users = $redis->get('users:seen');

    $visits = $redis->hgetall('hit_per_sec');

    $avg_visits = 0;
    if($visits !== 0){
        $avg_visits = (int) (array_sum($visits) / count($visits));
    }

    $visits_h = $redis->hgetall('hit_host_per_sec');

    $message = '';
    $message .= sprintf('<h1>Have seen %d users so far. </h1>', $users);
    $message .= sprintf('<h1>Total Average %d hits per sec. </h1>', $avg_visits);
    $message .= sprintf('<pre>%s</pre>', json_encode($visits, JSON_PRETTY_PRINT));
    $message .= sprintf('<pre>%s</pre>', json_encode($visits_h, JSON_PRETTY_PRINT));

    return $message;
});

$app->run();
