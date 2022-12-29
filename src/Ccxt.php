<?php

namespace Src;

use ccxt\Exchange;
use Exception;
use Src\Data\Binance;

class Ccxt
{
    protected Exchange|\ccxt\binance $exchange;

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

    public function getOrderbook(string $symbol, int $depth = 5): array
    {
        try {
            $orderbook = $this->exchange->fetch_order_book($symbol, $depth);
            unset($orderbook['nonce']);
            unset($orderbook['info']);
            unset($orderbook['datetime']);
        } catch (Exception $e) {
            Log::error($e, ['$symbol' => $symbol, '$depth' => $depth]);
            echo '[ERROR] fetch_order_book does not work. Error:' . $e->getMessage() . PHP_EOL;
        }

        return $orderbook ?? [];
    }

    public function getOpenOrders(string $symbol = null): array
    {
        if ($this->exchange->has["fetchOpenOrders"] !== false) {
            try {
                $open_orders = ($symbol) ? $this->exchange->fetch_open_orders($symbol) : $this->exchange->fetch_open_orders();
                foreach ($open_orders as $key => $open_order) {
                    unset($open_orders[$key]['info']);
                    unset($open_orders[$key]['clientOrderId']);
                    unset($open_orders[$key]['lastTradeTimestamp']);
                    unset($open_orders[$key]['timeInForce']);
                    unset($open_orders[$key]['postOnly']);
                    unset($open_orders[$key]['reduceOnly']);
                    unset($open_orders[$key]['stopPrice']);
                    unset($open_orders[$key]['cost']);
                    unset($open_orders[$key]['average']);
                    unset($open_orders[$key]['fee']);
                    unset($open_orders[$key]['trades']);
                }
            } catch (Exception $e) {
                Log::error($e, ['$symbol' => $symbol]);
                echo "[INFO] fetch_open_orders does not work without a symbol. Error: " . $e->getMessage() . PHP_EOL;
            }
        }
        return $open_orders ?? [];
    }

    public function getBalances(array $assets): array
    {
        try {
            $all_balances = $this->exchange->fetch_balance();

            foreach ($assets as $asset)
                $balances[$asset] = $all_balances[$asset] ?? ["free" => 0, "used" => 0, "total" => 0];
        } catch (Exception $e) {
            Log::error($e, ['$assets' => $assets]);
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
        }
        return $balances ?? [];
    }

    public function getMyTrades(string $symbol = 'BTC/USDT', int $limit = 1): array
    {
        try {
            return $this->exchange->fetch_my_trades($symbol, limit: $limit);
        } catch (Exception $e) {
            Log::error($e, ['$symbol' => $symbol]);
            echo "[INFO] getMyTrade does not work. Error: " . $e->getMessage() . PHP_EOL;
        }

        return [];
    }

    public function createOrder(string $symbol, string $type, string $side, float $amount, float $price = null): array
    {
        try {
            $order = $this->exchange->create_order($symbol, $type, $side, $amount, $price);
        } catch (Exception $e) {
            Log::error($e, ['$symbol' => $symbol, '$type' => $type, '$side' => $side, '$amount' => $amount, '$price' => $price]);
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
        }
        return $order ?? [];
    }

    public function cancelOrder(string $order_id, string $symbol): array
    {
        try {
            return $this->exchange->cancel_order($order_id, $symbol);
        } catch (Exception $e) {
            Log::error($e, ['$order_id' => $order_id, '$symbol' => $symbol]);
            echo '[ERROR] ' . $e->getMessage() . PHP_EOL;
        }
        return [];
    }

    public function cancelAllOrders(string $symbol = null): array
    {
        try {
            if ($open_orders = $this->getOpenOrders($symbol))
                foreach ($open_orders as $open_order)
                    $this->exchange->cancel_order($open_order['id'], $open_order['symbol']);
        } catch (Exception $e) {
            Log::error($e, ['$symbol' => $symbol]);
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