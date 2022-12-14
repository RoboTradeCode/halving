<?php

use Src\Ccxt;
use Src\Halving;

require dirname(__DIR__) . '/index.php';

$keys = require_once dirname(__DIR__) . '/config/keys.config.php';
$api_public = $keys['api_public'];
$api_secret = $keys['api_secret'];

$exchange = 'binance';
$symbol = 'BTC/USDT';
$low = 10000;
$high = 100000;
$count_of_orders = 180;
list($base_asset, $quote_asset) = explode('/', $symbol);

$bot = new Ccxt($exchange, $api_public, $api_secret);

$halving = new Halving($low, $high, $count_of_orders, $bot->getMarketInfo($symbol));

$grid = $halving->getGrid();

// get balances
// get open orders
// get price
$open_orders = [
    [
        'id' => 3351123429,
        'symbol' => 'BTC/USDT',
        'type' => 'limit',
        'side' => 'buy',
        'price' => 14000,
        'amount' => 0.007,
        'status' => 'open'
    ],
    [
        'id' => 3351123429,
        'symbol' => 'BTC/USDT',
        'type' => 'limit',
        'side' => 'buy',
        'price' => 12000,
        'amount' => 0.007,
        'status' => 'open'
    ],
    [
        'id' => 3351123429,
        'symbol' => 'BTC/USDT',
        'type' => 'limit',
        'side' => 'buy',
        'price' => 13000,
        'amount' => 0.007,
        'status' => 'open'
    ]
];
$balances = [
    'BTC' => ['free' => 1, 'used' => 0, 'total' => 1],
    'USDT' => ['free' => 1500, 'used' => 0, 'total' => 1500]
];
$price = 15000;

// [START] BUY POSITIONS
$grid_buys = $halving->getGridBuy($grid, $price);
$balance_total = $balances[$quote_asset]['total'];
$count_real_orders_buy = count($grid_buys);
$deal_amount_buy = $halving->getDealAmountBuy($balance_total, $count_real_orders_buy, $price);
$open_order_buys = $halving->getOpenOrderBuys($open_orders);

$need_cancel_order_buys = $halving->getNeedCancelOrderBuys($open_order_buys, $grid_buys);
foreach ($need_cancel_order_buys as $need_cancel_order_buy) {
    // cancel order buy
}

// get balances
// get open orders
$balance_total = $balances[$quote_asset]['total'];
$count_real_orders_buy = count($grid_buys);
$open_order_buys = $halving->getOpenOrderBuys($open_orders);
$grid_status_buys = $halving->getGridStatusBuy($grid_buys, $open_order_buys, $count_real_orders_buy);

foreach ($halving->needCancel($grid_status_buys) as $need_cancel) {
    // cancel orders buy
}
foreach ($halving->needCreate($grid_status_buys) as $need_create) {
    // create orders buy
}
// [END] BUY POSITIONS

// [START] SELL POSITIONS
$grid_sells = $halving->getGridSell($grid, $price);
$balance_total = $balances[$base_asset]['total'];
$count_real_orders_sell = count($grid_sells);
$deal_amount_sell = $halving->getDealAmountSell($balance_total, $count_real_orders_sell);
$open_order_sells = $halving->getOpenOrderSells($open_orders);

$need_cancel_order_sells = $halving->getNeedCancelOrderSells($open_order_sells, $grid_sells);
foreach ($need_cancel_order_sells as $need_cancel_order_sell) {
    // cancel order
}

// get balances
// get open orders
// sort open orders

$balance_total = $balances[$base_asset]['total'];
$count_real_orders_sell = count($grid_sells);
$open_order_sells = $halving->getOpenOrderSells($open_orders);
$grid_status_sells = $halving->getGridStatusSell($grid_sells, $open_order_sells, $count_real_orders_sell);

foreach ($halving->needCancel($grid_status_sells) as $need_cancel) {
    // cancel orders sell
}
foreach ($halving->needCreate($grid_status_sells) as $need_create) {
    // create orders sell
}
// [END] SELL POSITIONS