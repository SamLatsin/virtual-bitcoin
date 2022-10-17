<?php

use App\Func;
use App\Wallet;
use App\RecoverQueue;
use App\BtcAddress;
use App\Transaction;

function pageNotFound($app) {
    header("HTTP/1.0 400 Bad Request");
    header('Content-Type: application/json; charset=utf-8');
    $result = [
        "status"=>"error",
        "error"=>"Bad Request",
    ];
    echo json_encode($result);
    return exit();
}

$app->notFound(function () use ($app) {
    header("HTTP/1.0 400 Bad Request");
    header('Content-Type: application/json; charset=utf-8');
    $result = [
        "status"=>"error",
        "error"=>"Bad Request",
    ];
    return json_encode($result);
});

$app->before(function () use ($app) {
    if (strpos($_SERVER['REQUEST_URI'], CRON) === false) {
        if ($_SERVER['REQUEST_METHOD'] == "GET") {
            if (!isset(API_TICKER_TOKEN[basename("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")])) {
                pageNotFound($app);
            }
            if (!isset($_GET["token"]) or htmlspecialchars($_POST["token"]) != API_TICKER_TOKEN[basename("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")]) {
                pageNotFound($app);
            }
        }
        if ($_SERVER['REQUEST_METHOD'] == "POST") {
            if (!isset(API_TICKER_TOKEN[basename("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")])) {
                pageNotFound($app);
            }
            if (!isset($_POST["token"]) or htmlspecialchars($_POST["token"]) != API_TICKER_TOKEN[basename("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]")]) {
                pageNotFound($app);
            }
        }
        if ($_SERVER['REQUEST_METHOD'] != "GET" and $_SERVER['REQUEST_METHOD'] != "POST") {
            pageNotFound($app);
        }
    }
    
	
	$app["Function"] = new Func($app);
    $app["Wallet"] = new Wallet($app);
    $app["RecoverQueue"] = new RecoverQueue($app);
    $app["BtcAddress"] = new BtcAddress($app);
    $app["Transaction"] = new Transaction($app);
    return true;
});