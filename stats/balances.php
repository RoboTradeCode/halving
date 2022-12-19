<?php

require dirname(__DIR__) . '/index.php';

$db = require_once dirname(__DIR__) . '/config/db.config.php';
$keys = require_once dirname(__DIR__) . '/config/keys.config.php';

$exchange_name = 'binance';

$assets = [
    "BTC",
    "USDT",
];

$exchange_class = "\\ccxt\\" .  $exchange_name;
$exchange = new $exchange_class (["apiKey" => $keys['api_public'], "secret" => $keys['api_secret'], "enableRateLimit" => true]);

try {
    $db = new PDO("mysql:host=" . $db['host'] . ";port=" . $db['port'] . ";dbname=main", $db['user'], $db['password'], [PDO::ATTR_PERSISTENT => true]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    sleep(1);
    die("[ERROR] Can not connect to db: $e" . PHP_EOL);
}

$balances = [];

while (true) {
    usleep(1000000);

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

    /* Balances from exchange */
    try {
        $all_balances = $exchange->fetch_balance();
    } catch (Exception $e) {
        echo '[Error] ' . $e->getMessage() . PHP_EOL;
        continue;
    }

    foreach ($assets as $asset) {
        if (isset($all_balances[$asset])) $balances[$asset] = $all_balances[$asset];
        else $balances[$asset] = ["free" => 0, "used" => 0, "total" => 0];
    }

    $balances_string = '';

    foreach ($balances as $asset => $balance) {
        // Общий баланс в USD
        $total_usd = (isset($rates[$asset]['USD']) && isset($balance['total'])) ?
            $rates[$asset]['USD'] * $balance['total'] :
            0;

        // Общий баланс в BTC
        $total_btc = (isset($rates[$asset]['BTC']) && isset($balance['total'])) ?
            $rates[$asset]['BTC'] * $balance['total'] :
            0;

        $params = [
            ':asset' => $asset,
            ':free' => $balance['free'],
            ':used' => $balance['used'],
            ':total' => $balance['total'],
            ':total_btc' => $total_btc,
            ':total_usd' => $total_usd,
            ':exchange' => $exchange_name,
            ':instance' => 'spread_bot_php',
            ':algo' => 'halving',
            ':created' => date("Y-m-d H:i:s", time()),
        ];

        $stmt = $db->prepare(/** @lang sql */
            "INSERT INTO `balances_" . $exchange_name . "` (exchange, instance, algo, asset, free, used, total, total_usd, total_btc, last_update) VALUES 
                        (:exchange,  :instance, :algo, :asset, :free, :used, :total, :total_usd, :total_btc, :created) 
				         ON DUPLICATE KEY UPDATE asset = :asset, free = :free, used = :used, total = :total, total_usd = :total_usd, total_btc = :total_btc, last_update = :created");

        $stmt->execute($params);

        $balances_string .= "$asset: {$balance['total']}, ";
    }

    $balances_string = rtrim($balances_string, ", ");

    echo "[BALANCE] New balances: $balances_string" . PHP_EOL;
}