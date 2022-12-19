<?php

require dirname(__DIR__) . '/index.php';

$db = require_once dirname(__DIR__) . '/config/db.config.php';
$keys = require_once dirname(__DIR__) . '/config/keys.config.php';

$exchange_name = 'binance';

$assets = [
    "BTC",
    "USDT",
];

$exchange_class = "\\ccxt\\" . $exchange_name;
$exchange = new $exchange_class (["apiKey" => $keys['api_public'], "secret" => $keys['api_secret'], "enableRateLimit" => true]);

try {
    $db = new PDO("mysql:host=" . $db['host'] . ";port=" . $db['port'] . ";dbname=main", $db['user'], $db['password'], [PDO::ATTR_PERSISTENT => true]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    sleep(1);
    die("[ERROR] Can not connect to db: $e" . PHP_EOL);
}

$open_orders = [];

while (true) {
    usleep(5000000);

    $start = hrtime(true);

    /* Rates */
    try {
        $rates = [];

        $sth = $db->prepare("SELECT asset, btc, usd FROM `rates`");
        $sth->execute();

        foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $rate) {
            $rates[$rate['asset']] = ['BTC' => $rate['btc'], 'USD' => $rate['usd']];
        }

    } catch (PDOException $e) {
        sleep(1);
        die("[ERROR] Can get rates from DB: $e" . PHP_EOL);
    }

    /* Open orders from exchange */
    try {
        $all_open_orders = $exchange->fetch_open_orders('BTC/USDT');
    } catch (Exception $e) {
        echo '[Error] ' . $e->getMessage() . PHP_EOL;
        continue;
    }

    /* Truncate open orders table */
    $db->query('TRUNCATE TABLE open_orders_all');

    foreach ($all_open_orders as $order) {

        $client_order_id = $order['id'] ?? null;
        $order_id = $order['id'] ?? null;
        $type = $order['type'] ?? null;
        $side = $order['side'] ?? null;
        $amount = $order['amount'] ?? 0;
        $price = $order['price'] ?? 0;
        $filled = $amount;
        $symbol = $order['symbol'] ?? null;
        $base_or_quote = explode("/", $symbol) ?? null;
        $base_asset = $base_or_quote["0"] ?? null;
        $quote_asset = $base_or_quote["1"] ?? null;
        $status = "open";
        $usd_amount = (isset($rates[$base_asset]['USD'])) ? $rates[$base_asset]['USD'] * $amount : 0;
        $btc_amount = (isset($rates[$base_asset]['BTC'])) ? $rates[$base_asset]['BTC'] * $amount : 0;
        $execution_time = 0;
        $created = $order['datetime'];

        $params = [
            ':event_id' => null,
            ':client_order_id' => $client_order_id,
            ':order_id' => $order_id,
            ':exchange' => $exchange_name,
            ':instance' => 'halving',
            ':algo' => 'halving',
            ':symbol' => $symbol,
            ':base_asset' => $base_asset,
            ':quote_asset' => $quote_asset,
            ':type' => $type,
            ':side' => $side,
            ':amount' => $amount,
            ':btc_amount' => $btc_amount,
            ':usd_amount' => $usd_amount,
            ':price' => $price,
            ':filled' => $filled,
            ':status' => $status,
            ':sent' => 0,
            ':execution_time' => $execution_time,
            ':created' => $created
        ];

        $stmt = $db->prepare(/** @lang sql */
            "INSERT INTO `open_orders_all` 
                        (event_id, client_order_id, exchange, instance, algo, expected_id, dom_position, step, order_id, symbol, base_asset, quote_asset, type, side, amount, btc_amount, usd_amount, filled, price, status, execution_time, repeats, sent, created) VALUES
                        (:event_id, :client_order_id, :exchange, :instance, :algo, 0, 0, 0, :order_id, :symbol, :base_asset, :quote_asset, :type, :side, :amount, :btc_amount, :usd_amount, :filled, :price, :status, :execution_time, 0, :sent, :created)
                        ON DUPLICATE KEY UPDATE 
                        order_id = IF(status = 'open' OR order_id = '', VALUES(order_id), order_id),
                        symbol = IF(status = 'open' OR symbol = '', VALUES(symbol), symbol),
                        base_asset = IF(status = 'open' OR base_asset = '', VALUES(base_asset), base_asset),
                        quote_asset = IF(status = 'open' OR quote_asset = '', VALUES(quote_asset), quote_asset),
                        side = IF(status = 'open' OR side = '', VALUES(side), side),
                        type = IF(status = 'open' OR type = '', VALUES(type), type),
                        amount = IF(status = 'open' OR amount = '0', VALUES(amount), amount),
                        btc_amount = IF(status = 'open' OR btc_amount = '0', VALUES(btc_amount), btc_amount),
                        usd_amount = IF(status = 'open' OR usd_amount = '0', VALUES(usd_amount), usd_amount),
                        filled = IF(status = 'open' OR filled = '0' OR filled < VALUES(filled), VALUES(filled), filled),
                        price = IF(status = 'open' OR price = '0', VALUES(price), price),
                        status = IF(status = 'open' OR status = '', VALUES(status), status),
                        repeats = repeats + 1");

        $stmt->execute($params);

    }

    $date = date('d.m.Y H:i:s', time());
    $all_open_orders_count = count($all_open_orders);

    echo "[$date] [OPEN ORDERS] $all_open_orders_count orders open now" . PHP_EOL;
}