<?php

use Monolog\Level;
use Nur\Yaozon\Yandex;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;

require_once('../vendor/autoload.php');

$loggerGuzzle = new Logger('requests');
$loggerGuzzle->pushHandler(new StreamHandler(__DIR__ .'/../logs/all.log'));

$stack = HandlerStack::create();

$stack->push(
    Middleware::log(
        $loggerGuzzle,
        new MessageFormatter('{req_body} - {res_body}')
    )
);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ .'/../');
$env = $dotenv->load();

$logRequest = new Logger('Debug');
$logRequest->pushHandler(new StreamHandler(__DIR__ .'/../logs/requests.log', Level::Debug));

$logStocks = new Logger('Debug');
$logStocks->pushHandler(new StreamHandler(__DIR__ .'/../logs/stock.log', Level::Debug));

$logCart = new Logger('Debug');
$logCart->pushHandler(new StreamHandler(__DIR__ .'/../logs/cart.log', Level::Debug));

$logOrderAccept= new Logger('Debug');
$logOrderAccept->pushHandler(new StreamHandler(__DIR__ .'/../logs/order-accept.log', Level::Debug));

$logOrderStatus= new Logger('Debug');
$logOrderStatus->pushHandler(new StreamHandler(__DIR__ .'/../logs/order-status.log', Level::Debug));

$logGetMarketSku= new Logger('Debug');
$logGetMarketSku->pushHandler(new StreamHandler(__DIR__ .'/../logs/get-market-sku.log', Level::Debug));

if(!empty($_REQUEST['auth-token']) &&  $_REQUEST['auth-token'] == $env['YANDEX_AUTH_TOKEN']) {

    $json = file_get_contents('php://input');

    if($env['DEBUG']){ $logRequest->debug('Request ==>: '. json_encode($_REQUEST)); }

    if($_REQUEST['action'] === 'stocks') {

        //if($env['DEBUG']){ $logStocks->debug('Stock  ==>: '. $json); }

        $client = new GuzzleHttp\Client(['handler' => $stack]);
        $response = $client->request('POST', $env['URL_STOCKS_AND_CART'], [
            'body' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $res    = json_decode($response->getBody(), 1);
        $warehouseId   = json_decode($json, 1);

        //if($env['DEBUG']){ $logStocks->debug('Ответ для Stock  <==:  '. json_encode($res)); }

        $nData = [];

        if($res){
            foreach($res['skus'] as $k => $val){
                $nData['skus'][$k]['sku'] = $val['sku'];
                $nData['skus'][$k]['warehouseId'] = $warehouseId['warehouseId'];
                $nData['skus'][$k]['items'][0]['type'] = "FIT";
                $nData['skus'][$k]['items'][0]['count'] = $val['count'];
                $nData['skus'][$k]['items'][0]['updatedAt'] = date("Y-m-d")."T".date("H:i:s")."+04:00";
            }
        }

        if($nData){
            header('Content-type: application/json');
            echo json_encode($nData);
        } else {
            header("HTTP/1.1 200 OK");
        }
    }

    if($_REQUEST['action'] === 'cart'){

        if($env['DEBUG']){ $logCart->debug('Cart ==>: '. $json); }

        $client = new GuzzleHttp\Client(['handler' => $stack]);
        $response = $client->request('POST', $env['URL_STOCKS_AND_CART'], [
            'body' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $res = json_decode($response->getBody(), 1);

        /*$allCount = true;

        foreach($res['items'] as $k => $v){
            if($v['count'] > 0){
                $allCount = false;
                break;
            }
        }

        if($allCount === true){
            $otvet['cart']['items'] = "";
        } else {
            $otvet['cart'] = $res;
        }*/

        $otvet['cart'] = $res;

        if($env['DEBUG']){ $logCart->debug('Ответ для Cart  <==: '. json_encode($otvet)); }

        echo json_encode($otvet);

    }

    if($_REQUEST['action'] === 'order/accept'){

        if($env['DEBUG']){ $logOrderAccept->debug('Order/accept ==>: '. $json); }

        $client = new GuzzleHttp\Client(['handler' => $stack]);
        $response = $client->request('POST', $env['URL_ORDER_ACCEPT_STATUS'], [
            'body' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $res = json_decode($response->getBody(), 1);

        $otvet = [];
        $otvet["order"]["accepted"] = true;
        $otvet["order"]["id"] = $res['id_zayav'][0];

        if($env['DEBUG']){ $logOrderAccept->debug('Ответ для Order/accept  <==: '. json_encode($otvet)); }

        echo json_encode($otvet);
    }

    if($_REQUEST['action'] === 'order/status'){

        $order = json_decode($json, 1);

        if($env['DEBUG']){ $logOrderStatus->debug('Order/status №'.$order['order']['id'].' ==>: '. $json); }

        $client = new GuzzleHttp\Client(['handler' => $stack]);
        $response = $client->request('POST', $env['URL_ORDER_ACCEPT_STATUS'], [
            'body' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        if($response->getBody()){

            if($env['DEBUG']){ $logOrderStatus->debug('Ответ для Order/status №'.$order['order']['id'].'  <==: '. $response->getBody()); }

            $url = $env['YANDEX_API_URL'].'/campaigns/'.$env['CAMPAIGN'].'/orders/'.$order['order']['id'].'/status.json';
            $response = $client->request('PUT', $url, [
                'body' => $response->getBody(),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'OAuth oauth_token="'.$env['YANDEX_TOKEN'].'", oauth_client_id="'.$env['YANDEX_CLIENT_ID'].'"',
                ]
            ]);

            if($env['DEBUG']){ $logOrderStatus->debug('Измененный Order/status №'.$order['order']['id'].' ==>: '. $response->getBody()); }

            $response = $client->request('POST', $env['URL_ORDER_ACCEPT_STATUS'], [
                'body' => $response->getBody(),
                'headers' => ['Content-Type' => 'application/json']
            ]);

        } else {
            header("HTTP/1.1 200 OK");
        }
    }

    if($_REQUEST['action'] === 'order/cancelled'){

        $order = json_decode($json, 1);

        if($env['DEBUG']){ $logOrderStatus->debug('Отменяем заказ Order/status №'.$order['orderid'].'  <==: '. $json); }

        $client = new GuzzleHttp\Client(['handler' => $stack]);
        $url = $env['YANDEX_API_URL'].'/campaigns/'.$env['CAMPAIGN'].'/orders/'.$order['orderid'].'/status.json';
        $body = [];
        $body['order'] = $order['order'];
        $response = $client->request('PUT', $url, [
            'body' => json_encode($body),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'OAuth oauth_token="'.$env['YANDEX_TOKEN'].'", oauth_client_id="'.$env['YANDEX_CLIENT_ID'].'"',
            ]
        ]);
    }

    if($_REQUEST['action'] === 'order/check'){

        $order = json_decode($json, 1);

        $client = new GuzzleHttp\Client(['handler' => $stack]);
        $url = $env['YANDEX_API_URL'].'/campaigns/'.$env['CAMPAIGN'].'/orders/'.$order['orderid'].'.json';
        $response = $client->request('GET', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'OAuth oauth_token="'.$env['YANDEX_TOKEN'].'", oauth_client_id="'.$env['YANDEX_CLIENT_ID'].'"',
            ]
        ]);

        if($response->getBody()) {

            $response = $client->request('POST', $env['URL_ORDER_ACCEPT_STATUS'], [
                'body' => $response->getBody(),
                'headers' => ['Content-Type' => 'application/json']
            ]);

        } else {
            echo "Заказ с номером ".$order['orderid']." не найден";
        }


    }

    if($_REQUEST['action'] === 'get/sku'){

        if($env['DEBUG']){ $logGetMarketSku->debug('Shop SKU ==>: '. $json); }

        $skus = json_decode($json, 1);

        $client = new GuzzleHttp\Client();

        $url = $env['YANDEX_API_URL'].'/campaigns/'.$env['CAMPAIGN'].'/offer-mapping-entries.json?shop_sku='.$skus['sku'][0];

        $response = $client->request('GET', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'OAuth oauth_token="'.$env['YANDEX_TOKEN'].'", oauth_client_id="'.$env['YANDEX_CLIENT_ID'].'"',
            ]
        ]);

        $res = json_decode($response->getBody(), 1);

        if($res['status'] == 'OK'){
            echo $res['result']['offerMappingEntries'][0]['mapping']['marketSku'];
        } else {
            echo null;
        }


    }
}

