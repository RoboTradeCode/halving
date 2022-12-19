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

    public function getPrice(array $orderbook): float
    {
        return ($orderbook['bids'][0][0] + $orderbook['asks'][0][0]) / 2;
    }

    public function getGridBuy(array $grid, float $price): array
    {
        $grid_buys = array_filter($grid, fn($g) => $g < $price);
        rsort($grid_buys);
        return $grid_buys;
    }

    public function getGridSell(array $grid, float $price): array
    {
        $grid_sells = array_filter($grid, fn($g) => $g > $price);
        sort($grid_sells);
        return $grid_sells;
    }

    public function getDealAmountBuy(float $balance_total, int $count_real_order_buys, float $price): float
    {
        return Math::incrementNumber($balance_total / ($count_real_order_buys * $price), $this->market_info['precision_amount']);
    }

    public function getDealAmountSell(float $balance_total, int $count_real_order_sells): float
    {
        return Math::incrementNumber($balance_total / $count_real_order_sells, $this->market_info['precision_amount']);
    }

    public function getNeedCancelOrder(array $open_orders, array $grids): array
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

    public function getGridStatusBuy(array $grid_buys, array $open_orders, int $count_real_orders_buy): array
    {
        $open_order_buys = array_filter($open_orders, fn($open_order) => $open_order['side'] == 'buy' && $open_order['status'] == 'open');
        return $this->getGridStatus($grid_buys, $open_order_buys, $count_real_orders_buy);
    }

    public function getGridStatusSell(array $grid_sells, array $open_orders, int $count_real_orders_sell): array
    {
        $open_order_sells = array_filter($open_orders, fn($open_order) => $open_order['side'] == 'sell' && $open_order['status'] == 'open');
        return $this->getGridStatus($grid_sells, $open_order_sells, $count_real_orders_sell);
    }

    public function needCancel(array $grid_statuses): array
    {
        return array_filter($grid_statuses, fn($grid_status) => ($grid_status['id'] && !$grid_status['need']));
    }

    public function needCreate(array $grid_statuses): array
    {
        return array_filter($grid_statuses, fn($grid_status) => (!$grid_status['id'] && $grid_status['need']));
    }

    private function getGridStatus(array $grids, array $open_orders, int $count_real_orders): array
    {
        [$grid_status, $i] = [[], 1];
        foreach ($grids as $key => $grid) {
            $grid_status[$key] = ['price' => $grid, 'need' => ($i++ <= $count_real_orders)];
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
}