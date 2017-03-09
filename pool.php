<?php
require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Middleware;

function multiRequest(array $container) : array
{
    $mock = new MockHandler($container['responses']);
    $handler = HandlerStack::create($mock);

    $transactions = [];
    $history = Middleware::history($transactions);
    $handler->push($history);

    $client = new Client([
        'handler' => $handler,
        'allow_redirects' => [
            'track_redirects' => true
        ]
    ]);

    $pool = new \GuzzleHttp\Pool($client, $container['requests'], [
        'concurrency' => 5,
        'fulfilled' => $container['fulfilled'],
        'rejected' => $container['rejected'],
    ]);

    $promise = $pool->promise();
    $promise->wait();
    return $transactions;
}

/* 非同期リクエストのため、リクエスト順でレスポンスが返らないことを確認 */

// リダイレクトがないreq2の方が早くレスポンスが返ります。
$expected_fulfilled_index = ['req2', 'req1'];
$transactions = multiRequest([
    'responses' => [
        new Response(301, ['Location' => 'https://example.com']),
        new Response(302), // Locationヘッダでリダイレクトするか決まります。
        new Response(200, [], '200 content'),
        new Response(404),
    ],
    'requests' => [
        'req1' => new Request('GET', 'req1.com'),
        'req2' => new Request('GET', 'req2.com'),
    ],
    'fulfilled' => function ($response, $index) use (&$expected_fulfilled_index) {
        // $indexは0からではなく、元の$requestsのindexが維持されます。
        // 完了したものから入ってきます。
        assert(array_shift($expected_fulfilled_index) === $index);
    },
    'rejected' => function (RequestException $reason, $index) {
        // 404の場合はrejectedになりません。
    }
]);
assert(3 === count($transactions));
$requests = array_column($transactions, 'request');
assert(
    ['req1.com', 'req2.com', 'https://example.com'] ===
    array_map(function ($req) { return (string)$req->getUri(); }, $requests)
);

$responses = array_column($transactions, 'response');
assert( // 302はLocationがないので成功と見なされます。
    [301, 302, 200] ===
    array_map(function ($res) { return $res->getStatusCode(); }, $responses)
);
