<?php

use Monolog\Level;
use Nur\Yaozon\Yandex;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;

require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$env = $dotenv->load();

/*

$client = new GuzzleHttp\Client();

$url = 'https://api.partner.market.yandex.ru/v2/campaigns/10003/orders/7206821/delivery/parcel/4177/boxes.json';
$url = $env['YANDEX_API_URL'].'/campaigns/'.$env['CAMPAIGN'].'/orders/121352086/delivery/parcel/214546847/boxes.json';

$body = '{"boxes":[{"fulfilmentId": "121352086-1"}]}';

$response = $client->request('PUT', $url, [
    'body' => $body,
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'OAuth oauth_token="AQAAAAAsVzKzAAflIiUAn89MeERNiDYYpmClAPs", oauth_client_id="6088a34ebee24bc1b9001f29ff8bf8e6"',
    ]
]);*/

$client = new GuzzleHttp\Client();

$url = $env['YANDEX_API_URL'].'/campaigns/'.$env['CAMPAIGN'].'/offer-mapping-entries.json?shop_sku=530023';

$response = $client->request('GET', $url, [
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'OAuth oauth_token="AQAAAAAsVzKzAAflIiUAn89MeERNiDYYpmClAPs", oauth_client_id="6088a34ebee24bc1b9001f29ff8bf8e6"',
    ]
]);

$res = json_decode($response->getBody(), 1);


echo "<pre>";
print_r($res);
echo "</pre>";