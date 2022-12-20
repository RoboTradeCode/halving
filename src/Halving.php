<?php

namespace Src;

class Halving
{
    private array $market_info;

    public function __construct(array $full_market_info)
    {
        $this->market_info = [
            'precision_amount' => $full_market_info['precision']['amount'],
            'precision_price' => $full_market_info['precision']['price'],
            'limit_amount_min' => $full_market_info['limits']['amount']['min'],
            'limit_amount_max' => $full_market_info['limits']['amount']['max'],
            'limit_price_min' => $full_market_info['limits']['price']['min'],
            'limit_price_max' => $full_market_info['limits']['price']['max'],
            'limit_cost_min' => $full_market_info['limits']['cost']['min'],
        ];
    }

    public function getGrid(float $low, float $high, int $count_of_orders): array
    {
        [$element, $grid, $i] = [$low, [], 0];
        $step = ($high - $low) / ($count_of_orders - 1);
        while ($element < $high && !Math::compareFloats($element, $high)) {
            $element = Math::incrementNumber($low + $step * $i, $this->market_info['precision_price']);
            $grid[] = $element;
            $i++;
        }
        return $grid;
    }

    public function cancelUnnecessaryOpenOrders(array $grid, string $symbol, Ccxt $bot): void
    {
        foreach ($this->getNeedCancelOrders($bot->getOpenOrders($symbol), $grid) as $need_cancel_order_buy) {
            $bot->cancelOrder($need_cancel_order_buy['id'], $need_cancel_order_buy['symbol']);
            echo '[' . date('Y-m-d H:i:s') . '] [' . $need_cancel_order_buy['side'] . '] Cancel order: ' . $need_cancel_order_buy['id'] . ', ' . $need_cancel_order_buy['price'] . PHP_EOL;
        }
    }

    public function getPrice(array $orderbook): float
    {
        return ($orderbook['bids'][0][0] + $orderbook['asks'][0][0]) / 2;
    }

    public function getGridStatusesAndDealAmount(array $grid, array $open_orders, float $balance_total, float $price, string $side): array
    {
        $grid_by_side = $this->getGridBySide($grid, $price, $side);
        $count_real_orders = count($grid_by_side);
        if ($count_real_orders > 0) {
            $grid_status = $this->getGridStatusBySide($grid_by_side, $open_orders, $count_real_orders, $side);
            $deal_amount = $this->getDealAmountBySide($balance_total, $count_real_orders, $price, $side);
            return [$grid_status, $deal_amount];
        }
        return [[], 0];
    }

    public function cancelAndCreateOrders(array $grid_status_buys, float $deal_amount_buy, array $grid_status_sells, float $deal_amount_sell, Ccxt $bot): void
    {
        $grid_statuses = array_merge($grid_status_buys, $grid_status_sells);
        foreach ($this->needCancel($grid_statuses) as $need_cancel) {
            $bot->cancelOrder($need_cancel['id'], $need_cancel['symbol']);
            echo '[' . date('Y-m-d H:i:s') . '] [' . $need_cancel['side'] . '] Cancel order: ' . $need_cancel['id'] . PHP_EOL;
        }
        foreach ($this->needCreate($grid_statuses) as $need_create) {
            $deal_amount = ($need_create['side'] == 'buy') ? $deal_amount_buy : $deal_amount_sell;
            if ($deal_amount > 0) {
                $bot->createOrder($need_create['symbol'], 'limit', $need_create['side'], $deal_amount, $need_create['price']);
                echo '[' . date('Y-m-d H:i:s') . '] [' . $need_create['side'] . '] Create order: ' . $need_create['price'] . ', ' . $deal_amount . PHP_EOL;
            } else {
                echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Deal amount is zero' . PHP_EOL;
            }
        }
    }

    public function getGridBySide(array $grid, float $price, string $side): array
    {
        return ($side == 'buy') ? $this->getGridBuy($grid, $price) : $this->getGridSell($grid, $price);
    }

    public function getDealAmountBySide(float $balance_total, int $count_real_order_buys, float $price, string $side): float
    {
        return ($side == 'buy') ? $this->getDealAmountBuy($balance_total, $count_real_order_buys, $price) : $this->getDealAmountSell($balance_total, $count_real_order_buys);
    }

    public function getNeedCancelOrders(array $open_orders, array $grids): array
    {
        $open_orders = array_filter($open_orders, fn($open_order) => $open_order['status'] == 'open');
        foreach ($open_orders as $open_order) {
            $is_in_grid = false;
            foreach ($grids as $grid) {
                if (Math::compareFloats($open_order['price'], $grid)) {
                    $is_in_grid = true;
                    break;
                }
            }
            if (!$is_in_grid)
                $need_cancel_order[] = $open_order;
        }
        return $need_cancel_order ?? [];
    }

    public function getGridStatusBySide(array $grids, array $open_orders, int $count_real_orders, string $side): array
    {
        $open_orders = array_filter($open_orders, fn($open_order) => $open_order['side'] == $side && $open_order['status'] == 'open');
        [$grid_status, $i] = [[], 1];
        foreach ($grids as $key => $grid) {
            $grid_status[$key] = ['price' => $grid, 'need' => ($i++ <= $count_real_orders), 'side' => $side];
            foreach ($open_orders as $open_order) {
                if (Math::compareFloats($open_order['price'], $grid)) {
                    $grid_status[$key]['id'] = $open_order['id'];
                    continue 2;
                }
            }
            $grid_status[$key]['id'] = '';
        }
        return $grid_status;
    }

    public function needCancel(array $grid_statuses): array
    {
        return array_filter($grid_statuses, fn($grid_status) => ($grid_status['id'] && !$grid_status['need']));
    }

    public function needCreate(array $grid_statuses): array
    {
        return array_filter($grid_statuses, fn($grid_status) => (!$grid_status['id'] && $grid_status['need']));
    }

    protected function getGridBuy(array $grid, float $price): array
    {
        $grid_buys = array_filter($grid, fn($g) => $g < $price);
        rsort($grid_buys);
        return $grid_buys;
    }

    protected function getGridSell(array $grid, float $price): array
    {
        $grid_sells = array_filter($grid, fn($g) => $g > $price);
        sort($grid_sells);
        return $grid_sells;
    }

    protected function getDealAmountBuy(float $balance_total, int $count_real_order_buys, float $price): float
    {
        return Math::incrementNumber($balance_total / ($count_real_order_buys * $price), $this->market_info['precision_amount']);
    }

    protected function getDealAmountSell(float $balance_total, int $count_real_order_sells): float
    {
        return Math::incrementNumber($balance_total / $count_real_order_sells, $this->market_info['precision_amount']);
    }
}