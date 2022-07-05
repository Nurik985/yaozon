<?php

use Monolog\Level;
use Nur\Yaozon\Yandex;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once('../vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$log = new Logger('Debug');
$log->pushHandler(new StreamHandler('../logs/debug.log', Level::Debug));

if(!empty($_REQUEST['auth-token']) &&  $_REQUEST['auth-token'] == getenv('YANDEX_AUTH_TOKEN')) {

    $json = file_get_contents('php://input');

    if($_REQUEST['action'] == 'stocks') {
        $log->debug('Stock  ==>: '. $json);

        $client = new GuzzleHttp\Client();
        $response = $client->request('POST', getenv('URL_STOCKS'), [
            'body' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $res = json_decode($response->getBody()->getContents(), 1);

        $log->debug('Ответ для Stock  <==:  '. json_encode($res));

        $nData = [];

        if($res){
            foreach($res['skus'] as $k => $val){
                $nData['skus'][$k]['sku'] = $val['sku'];
                $nData['skus'][$k]['warehouseId'] = $json['warehouseId'];
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

    if($_REQUEST['action'] == 'cart'){

        $log->debug('Cart ==>: '. $json);

        $client = new GuzzleHttp\Client();
        $response = $client->request('POST', getenv('URL_CART'), [
            'body' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $res = json_decode($response->getBody()->getContents(), 1);

        $otvet['cart'] = $res;

        $log->debug('Ответ для Cart  <==: '. json_encode($otvet));

        echo json_encode($otvet);

    }

    if($_REQUEST['action'] == 'order/accept'){

        $log->debug('Order/accept ==>: '. $json);

        $client = new GuzzleHttp\Client();
        $response = $client->request('POST', getenv('URL_ORDER_ACCEPT'), [
            'body' => $json,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $res = json_decode($response->getBody()->getContents(), 1);

        $otvet = [];
        $otvet["order"]["accepted"] = true;
        $otvet["order"]["id"] = $res['id_zayav'][0];

        $log->debug('Ответ для Order/accept  <==: '. json_encode($otvet));

        echo json_encode($otvet);
    }
}

