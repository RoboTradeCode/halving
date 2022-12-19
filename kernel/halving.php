<?php

use Src\Ccxt;
use Src\Halving;

require dirname(__DIR__) . '/index.php';


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

// [START] PRE CALCULATIONS
list($base_asset, $quote_asset) = explode('/', $symbol);
$bot = new Ccxt($exchange, $api_public, $api_secret);
$halving = new Halving($bot->getMarketInfo($symbol));
// [END] PRE CALCULATIONS

// [START] CALCULATIONS
$grid = $halving->getGrid($low, $high, $count_of_orders);
// [END] CALCULATIONS

// [START] CANCEL UNNECESSARY OPEN ORDERS
$open_orders = $bot->getOpenOrders($symbol);
foreach ($halving->getNeedCancelOrders($open_orders, $grid) as $need_cancel_order_buy) {
    $bot->cancelOrder($need_cancel_order_buy['id'], $need_cancel_order_buy['symbol']);
    echo '[' . date('Y-m-d H:i:s') . '] [' . $need_cancel_order_buy['side'] . '] Cancel order: ' . $need_cancel_order_buy['id'] . ', ' . $need_cancel_order_buy['price'] . PHP_EOL;
}
// [END] CANCEL UNNECESSARY OPEN ORDERS

// [START] REQUESTS TO EXCHANGE
$balances = $bot->getBalances([$base_asset, $quote_asset]);
$open_orders = $bot->getOpenOrders($symbol);
$price = $halving->getPrice($bot->getOrderbook($symbol));
// [END] REQUESTS TO EXCHANGE

// [START] BUY POSITIONS
$grid_buys = $halving->getGridBySide($grid, $price, 'buy');
$count_real_orders_buy = count($grid_buys);
[$grid_status_buys, $deal_amount_buy] = [[], 0];
if ($count_real_orders_buy > 0) {
    $deal_amount_buy = $halving->getDealAmountBySide($balances[$quote_asset]['total'], $count_real_orders_buy, $price, 'buy');
    $grid_status_buys = $halving->getGridStatusBySide($grid_buys, $open_orders, $count_real_orders_buy, 'buy');
}
// [END] BUY POSITIONS

// [START] SELL POSITIONS
$grid_sells = $halving->getGridBySide($grid, $price, 'sell');
$count_real_orders_sell = count($grid_sells);
[$grid_status_sells, $deal_amount_sell] = [[], 0];
if ($count_real_orders_sell > 0) {
    $deal_amount_sell = $halving->getDealAmountBySide($balances[$base_asset]['total'], $count_real_orders_sell, $price, 'sell');
    $grid_status_sells = $halving->getGridStatusBySide($grid_sells, $open_orders, $count_real_orders_sell, 'sell');
}
// [END] SELL POSITIONS

// [START] CANCEL AND CREATE ORDERS
$grid_statuses = array_merge($grid_status_buys, $grid_status_sells);
foreach ($halving->needCancel($grid_statuses) as $need_cancel) {
    $bot->cancelOrder($need_cancel['id'], $symbol);
    echo '[' . date('Y-m-d H:i:s') . '] [' . $need_cancel['side'] . '] Cancel order: ' . $need_cancel['id'] . PHP_EOL;
}
foreach ($halving->needCreate($grid_statuses) as $need_create) {
    $deal_amount = ($need_create['side'] == 'buy') ? $deal_amount_buy : $deal_amount_sell;
    if ($deal_amount > 0) {
        $bot->createOrder($symbol, 'limit', $need_create['side'], $deal_amount, $need_create['price']);
        echo '[' . date('Y-m-d H:i:s') . '] [' . $need_create['side'] . '] Create order: ' . $need_create['price'] . ', ' . $deal_amount . PHP_EOL;
    } else {
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Deal amount is zero' . PHP_EOL;
    }
}
// [END] CANCEL AND CREATE ORDERS