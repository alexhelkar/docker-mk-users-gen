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

    $client->getConnection()->connect();

    while (!$client->getConnection()->isConnected()) {
        $client->getConnection()->connect();
    }

    return $client;
};


$app->post('/api/visit', function (Application $app, Request $request) {
    usleep(100000);

    $personJson = $request->getContent();
    $person = json_decode($personJson);

    /** @var Predis\Client $redis */
    $redis = $app['redis'];
    $redis->incrby('users:seen', 1);

    $redis->hincrby('hit_per_sec', time(), 1);
    $redis->hincrby('hit_host_per_sec', gethostname().':'.time(), 1);

    $response_msg = sprintf('%s %s visit have been tracked: '.gethostname(), $person->firstName, $person->lastName);
    return new Response($response_msg, Response::HTTP_CREATED);
});


$app->get('/stats', function (Application $app, Request $request) {

    /** @var Predis\Client $redis */
    $redis = $app['redis'];
    $users = $redis->get('users:seen');
//    $name_score = $redis->zrevrange('usernames:score', 0, 10, [
//        'withscores' => true
//    ]);
//
//    $age_score = $redis->zrevrange('age:score', 0, 10, [
//        'withscores' => true
//    ]);
//
//    $gender_score = $redis->zrevrange('gender:score', 0, 10, [
//        'withscores' => true
//    ]);

    $visits = $redis->hgetall('hit_per_sec');
    $visits_h = $redis->hgetall('hit_host_per_sec');

    $hits = array_map(function ($val) {
        return (int) $val;
    }, $visits);

    rsort($hits);
    $message = '';
    $message .= sprintf('<h1>Have seen %d users so far. </h1>', $users);
//    $message .= sprintf('<pre>%s</pre>', json_encode($name_score, JSON_PRETTY_PRINT));
//    $message .= sprintf('<pre>%s</pre>', json_encode($age_score, JSON_PRETTY_PRINT));
//    $message .= sprintf('<pre>%s</pre>', json_encode($gender_score, JSON_PRETTY_PRINT));
//    $message .= sprintf('<pre>%s</pre>', json_encode(array_slice($hits, 0, 5), JSON_PRETTY_PRINT));
    $message .= sprintf('<pre>%s</pre>', json_encode($visits, JSON_PRETTY_PRINT));
    $message .= sprintf('<pre>%s</pre>', json_encode($visits_h, JSON_PRETTY_PRINT));

    return $message;
});

$app->run();
