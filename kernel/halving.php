<?php

use Src\Ccxt;
use Src\Halving;

require dirname(__DIR__) . '/index.php';

$keys = require_once dirname(__DIR__) . '/config/keys.config.php';
$api_public = $keys['api_public'];
$api_secret = $keys['api_secret'];

$exchange = 'binance';
$symbol = 'BTC/USDT';
$low = 15000;
$high = 20000;
$count_of_orders = 50;
list($base_asset, $quote_asset) = explode('/', $symbol);

$bot = new Ccxt($exchange, $api_public, $api_secret);

$halving = new Halving($bot->getMarketInfo($symbol));

$grid = $halving->getGrid($low, $high, $count_of_orders);

$open_orders = $bot->getOpenOrders($symbol);
$need_cancel_order_buys = $halving->getNeedCancelOrder($open_orders, $grid);
foreach ($need_cancel_order_buys as $need_cancel_order_buy) {
    $bot->cancelOrder($need_cancel_order_buy['id'], $need_cancel_order_buy['symbol']);
    echo '[' . date('Y-m-d H:i:s') . '] [BUY] Cancel order: ' . $need_cancel_order_buy['id'] . ', ' . $need_cancel_order_buy['price'] . PHP_EOL;
}

$balances = $bot->getBalances([$base_asset, $quote_asset]);
$open_orders = $bot->getOpenOrders($symbol);
$price = $halving->getPrice($bot->getOrderbook($symbol));

// [START] BUY POSITIONS
$grid_buys = $halving->getGridBuy($grid, $price);
$count_real_orders_buy = count($grid_buys);
if ($count_real_orders_buy > 0) {
    $deal_amount_buy = $halving->getDealAmountBuy($balances[$quote_asset]['total'], $count_real_orders_buy, $price);
    $grid_status_buys = $halving->getGridStatusBuy($grid_buys, $open_orders, $count_real_orders_buy);

    foreach ($halving->needCancel($grid_status_buys) as $need_cancel) {
        $bot->cancelOrder($need_cancel['id'], $symbol);
        echo '[' . date('Y-m-d H:i:s') . '] [BUY] Cancel order: ' . $need_cancel['id'] . PHP_EOL;
    }

    foreach ($halving->needCreate($grid_status_buys) as $need_create) {
        $bot->createOrder($symbol, 'limit', 'buy', $deal_amount_buy, $need_create['price']);
        echo '[' . date('Y-m-d H:i:s') . '] [BUY] Create order: ' . $need_create['price'] . ', ' . $deal_amount_buy . PHP_EOL;
    }
}
// [END] BUY POSITIONS

// [START] SELL POSITIONS
$grid_sells = $halving->getGridSell($grid, $price);
$count_real_orders_sell = count($grid_sells);
if ($count_real_orders_sell > 0) {
    $deal_amount_sell = $halving->getDealAmountSell($balances[$base_asset]['total'], $count_real_orders_sell);
    $grid_status_sells = $halving->getGridStatusSell($grid_sells, $open_orders, $count_real_orders_sell);

    foreach ($halving->needCancel($grid_status_sells) as $need_cancel) {
        $bot->cancelOrder($need_cancel['id'], $symbol);
        echo '[' . date('Y-m-d H:i:s') . '] [SELL] Cancel order: ' . $need_cancel['id'] . PHP_EOL;
    }

    foreach ($halving->needCreate($grid_status_sells) as $need_create) {
        $bot->createOrder($symbol, 'limit', 'sell', $deal_amount_sell, $need_create['price']);
        echo '[' . date('Y-m-d H:i:s') . '] [SELL] Create order: ' . $need_create['price'] . ', ' . $deal_amount_sell . PHP_EOL;
    }
}
// [END] SELL POSITIONS