<?php

use Src\AlgoV2;
use Src\Ccxt;
use Src\DB;

require dirname(__DIR__) . '/index.php';

// [START] CONFIGURATIONS
//DB::connect();
//$config = DB::getConfig();
//
//$api_public = $config['api_public'];
//$api_secret = $config['api_secret'];
//$exchange = $config['exchange'];
//$symbol = $config['symbol'];
//$low = $config['low'];
//$high = $config['high'];
//$count_of_orders = $config['count_orders'];
// [END] CONFIGURATIONS

// [START] CONFIGURATIONS
$keys = require_once dirname(__DIR__) . '/config/keys.config.php';
$api_public = $keys['api_public'];
$api_secret = $keys['api_secret'];
$exchange = 'binance';
$symbol = 'BTC/USDT';
$low = 15000;
$high = 20000;
$count_of_orders = 50;
// [END] CONFIGURATIONS

$algo = new AlgoV2(new Ccxt($exchange, $api_public, $api_secret), $symbol, $low, $high, $count_of_orders);
$algo->run();