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
    ],
    'headers' => [
        'User-Agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36"
    ]
]);

// google.co.jpの省略リンク
$request_url = 'https://t.co/N4jbvrD7Xo';
$response = $client->request('GET', $request_url);

// ブラウザのUser-Agentだと、Locationヘッダーでリダイレクトされない。
$body = (string)$response->getBody();
$expected = preg_replace('/ # assert用に空白と改行の除去
    (?<= >) \s+    # 終了タグに後ろ
    | \s+ (?= <)   # 開始タグの手前
    | (?<= ;) \n[ ] # JSのセミコンロの後ろ
/x', '', <<< EOF
<head>
  <noscript><META http-equiv="refresh" content="0;URL=https://www.google.co.jp"></noscript>
  <title>https://www.google.co.jp</title>
</head>
<script>
  window.opener = null;
  location.replace("https:\/\/www.google.co.jp")
</script>
EOF
);
assert($expected === $body);

// リダイレクトが行われないのでトランザクションは1個しかない。
assert(1 === count($transactions));
