<?php

use Src\AlgoV2;
use Src\Ccxt;
use Src\DB;

require dirname(__DIR__) . '/index.php';

// [START] CONFIGURATIONS
DB::connect();
$config = DB::getConfig();

$api_public = $config['api_key'];
$api_secret = $config['api_secret'];
$exchange = $config['exchange'];
$symbol = $config['symbol'];
$low = $config['low'];
$high = $config['high'];
$count_of_orders = $config['order_count'];
// [END] CONFIGURATIONS

$algo = new AlgoV2(new Ccxt($exchange, $api_public, $api_secret), $symbol, $low, $high, $count_of_orders);
$algo->run();