<?php
require_once __DIR__ . '/../vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

$handler = HandlerStack::create();
$transactions = [];
$history = Middleware::history($transactions);
$handler->push($history);
$client = new Client([
    'handler' => $handler,
    'allow_redirects' => [
        'track_redirects' => true
    ]
]);

// google.co.jpの省略リンク
$request_url = 'https://t.co/N4jbvrD7Xo';
$response = $client->request('GET', $request_url);

// リダイレクト先のGoogleのトップページが取得出来ている。
$body = (string)$response->getBody();
assert(preg_match('~^<!doctype html>~', $body));
assert(preg_match('~<title>Google</title>~', $body));

/* リクエストの流れのテスト */
// リダイレクトがあるのでリクエストは2回行われている。
assert(2 === count($transactions));

/** t.coへのリクエスト **/
$redirect_transaction = $transactions[0];
$redirect_request = $redirect_transaction['request'];
$redirect_uri = $redirect_request->getUri();

assert($request_url === (string)$redirect_uri);

// デフォルトのUser-AgentはGuzzle独自のもの
//   ex: GuzzleHttp/6.2.1 curl/7.51.0 PHP/7.0.15
$user_agents = $redirect_request->getHeader('User-Agent');
assert(1, count($user_agents));

$version_re = '(\d+\.){2}\d+';
assert(preg_match(
    "~GuzzleHttp/{$version_re} curl/{$version_re} PHP/{$version_re}~",
    $user_agents[0]));

/** リダイレクト先へのリクエスト **/
$last_transaction = $transactions[1];
$last_request = $last_transaction['request'];
$last_uri = $last_request->getUri();

assert('https://www.google.co.jp' === (string)$last_uri);
