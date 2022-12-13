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
$deal_amount = 0.02;
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
$count_real_orders_buy = \Src\Math::incrementNumber($balances[$quote_asset]['total'] / ($deal_amount * $price), 1);
$grid_buys = array_filter($grid, fn($g) => $g < $price);
$open_order_buys = array_filter($open_orders, fn($open_order) => $open_order['side'] == 'buy' && $open_order['status'] == 'open');
uasort($open_order_buys, fn($a, $b) => $b['price'] <=> $a['price']);

foreach ($open_order_buys as $key => $open_order_buy) {
    $is_in_grid = false;
    foreach ($grid_buys as $grid_buy) {
        if (\Src\Math::compareFloats($open_order_buy['price'], $grid_buy)) {
            $is_in_grid = true;
            break;
        }
    }
    if (!$is_in_grid) {
        // cancel orders
    }
}

// get balances
// get open orders
// sort open orders
rsort($grid_buys);

$grid_status_buys = [];
foreach ($grid_buys as $key => $grid_buy) {
    $grid_status_buys[$key]['price'] = $grid_buy;
    foreach ($open_order_buys as $open_order_buy) {
        if (\Src\Math::compareFloats($open_order_buy['price'], $grid_buy)) {
            $grid_status_buys[$key]['id'] = $open_order_buy['id'];
            continue 2;
        }
    }
    $grid_status_buys[$key]['id'] = '';
}

$i = 1;
foreach ($grid_status_buys as $key => $grid_buy) {
    $grid_status_buys[$key]['need'] = ($i <= $count_real_orders_buy);
    $i++;
}

foreach (array_filter($grid_status_buys, fn($grid_status_buy) => ($grid_status_buy['id'] && !$grid_status_buy['need'])) as $need_cancel) {
    // cancel orders
}

foreach (array_filter($grid_status_buys, fn($grid_status_buy) => (!$grid_status_buy['id'] && $grid_status_buy['need'])) as $need_create) {
    // create orders sell
}
// [END] BUY POSITIONS

// [START] SELL POSITIONS
$count_real_orders_sell = \Src\Math::incrementNumber($balances[$base_asset]['total'] / $deal_amount, 1);
$grid_sells = array_filter($grid, fn($g) => $g > $price);
$open_order_sells = array_filter($open_orders, fn($open_order) => $open_order['side'] == 'sell' && $open_order['status'] == 'open');
uasort($open_order_sells, fn($a, $b) => $a['price'] <=> $b['price']);

foreach ($open_order_sells as $key => $open_order_sell) {
    $is_in_grid = false;
    foreach ($grid_buys as $grid_buy) {
        if (\Src\Math::compareFloats($open_order_sell['price'], $grid_buy)) {
            $is_in_grid = true;
            break;
        }
    }
    if (!$is_in_grid) {
        // cancel orders
    }
}

// get balances
// get open orders
// sort open orders
sort($grid_sells);

$grid_status_sells = [];
foreach ($grid_sells as $key => $grid_buy) {
    $grid_status_sells[$key]['price'] = $grid_buy;
    foreach ($open_order_sells as $open_order_buy) {
        if (\Src\Math::compareFloats($open_order_buy['price'], $grid_buy)) {
            $grid_status_sells[$key]['id'] = $open_order_buy['id'];
            continue 2;
        }
    }
    $grid_status_sells[$key]['id'] = '';
}

$i = 1;
foreach ($grid_status_sells as $key => $grid_buy) {
    $grid_status_sells[$key]['need'] = ($i <= $count_real_orders_sell);
    $i++;
}

foreach (array_filter($grid_status_buys, fn($grid_status_buy) => ($grid_status_buy['id'] && !$grid_status_buy['need'])) as $need_cancel) {
    // cancel orders
}

foreach (array_filter($grid_status_buys, fn($grid_status_buy) => (!$grid_status_buy['id'] && $grid_status_buy['need'])) as $need_create) {
    // create orders sell
}

// [END] SELL POSITIONS