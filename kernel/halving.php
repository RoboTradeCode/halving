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
$halving->cancelUnnecessaryOpenOrders($grid, $symbol, $bot);
// [END] CANCEL UNNECESSARY OPEN ORDERS

// [START] REQUESTS TO EXCHANGE
$balances = $bot->getBalances([$base_asset, $quote_asset]);
$open_orders = $bot->getOpenOrders($symbol);
$price = $halving->getPrice($bot->getOrderbook($symbol));
// [END] REQUESTS TO EXCHANGE

// [START] BUY POSITIONS
[$grid_status_buys, $deal_amount_buy] = $halving->getGridStatusesAndDealAmount($grid, $open_orders, $balances[$quote_asset]['total'], $price, 'buy');
// [END] BUY POSITIONS

// [START] SELL POSITIONS
[$grid_status_sells, $deal_amount_sell] = $halving->getGridStatusesAndDealAmount($grid, $open_orders, $balances[$base_asset]['total'], $price, 'sell');
// [END] SELL POSITIONS

// [START] CANCEL AND CREATE ORDERS
$halving->cancelAndCreateOrders($grid_status_buys, $deal_amount_buy, $grid_status_sells, $deal_amount_sell, $bot);
// [END] CANCEL AND CREATE ORDERS