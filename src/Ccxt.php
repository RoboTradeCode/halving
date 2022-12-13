<?php

namespace Src;

use ccxt\Exchange;
use Exception;
use Src\Data\Binance;
use Throwable;

class Ccxt
{
    protected Exchange $exchange;

    public function __construct($exchange_name, $api_public = '', $api_secret = '', $enableRateLimit = true)
    {
        $exchange_class = "\\ccxt\\" . $exchange_name;
        $this->exchange = new $exchange_class ([
            "apiKey" => $api_public,
            "secret" => $api_secret,
            "timeout" => 10000,
            "enableRateLimit" => $enableRateLimit
        ]);
    }

    public function getOpenOrders(string $symbol = null): array
    {
        if ($this->exchange->has["fetchOpenOrders"] !== false) {
            try {
                if ($symbol)
                    return $this->exchange->fetch_open_orders($symbol);
                return $this->exchange->fetch_open_orders();
            } catch (Throwable $e) {
                echo "[INFO] fetch_open_orders does not work without a symbol. Error: " . $e->getMessage() . PHP_EOL;
            }
        }
        return [];
    }

    public function getBalances(array $assets): array
    {
        try {
            $all_balances = $this->exchange->fetch_balance();

            foreach ($assets as $asset)
                $balances[$asset] = $all_balances[$asset] ?? ["free" => 0, "used" => 0, "total" => 0];
        } catch (Throwable $e) {
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
        }
        return $balances ?? [];
    }

    public function createOrder(string $symbol, string $type, string $side, float $amount, float $price = null): array
    {
        try {
            $order = $this->exchange->create_order($symbol, $type, $side, $amount, $price);
        } catch (Throwable $e) {
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
        }
        return $order ?? [];
    }

    public function cancelOrder(string $order_id, string $symbol): array
    {
        try {
            return $this->exchange->cancel_order($order_id, $symbol);
        } catch (Exception $e) {
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
        }
        return [];
    }

    public function cancelAllOrder(string $symbol = null): array
    {
        try {
            if ($open_orders = $this->getOpenOrders($symbol))
                foreach ($open_orders as $open_order)
                    $this->exchange->cancel_order($open_order['id'], $open_order['symbol']);
        } catch (Exception $e) {
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
        }
        return [];
    }

    public function getMarketInfo(string $symbol): ?array
    {
        return Binance::INFO_MARKETS[$symbol];
    }

    public function getOriginalMarkets(): ?array
    {
        $this->exchange->load_markets();
        return $this->exchange->markets;
    }
}