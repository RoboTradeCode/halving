<?php

namespace Src;

class Halving
{
    private array $market_info;

    public function __construct(protected float $low, protected float $high, protected int $count_of_orders, array $full_market_info)
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

    public function getGrid(): array
    {
        [$element, $grid, $i] = [$this->low, [], 0];
        $step = round(($this->high - $this->low) / ($this->count_of_orders - 1), 8);
        while ($element < $this->high && !Math::compareFloats($element, $this->high)) {
            $element = Math::incrementNumber($this->low + $step * $i, $this->market_info['precision_price']);
            $grid[] = $element;
            $i++;
        }
        return $grid;
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

    public function getOpenOrderBuys(array $open_orders): array
    {
        $open_order_buys = array_filter($open_orders, fn($open_order) => $open_order['side'] == 'buy' && $open_order['status'] == 'open');
        uasort($open_order_buys, fn($a, $b) => $b['price'] <=> $a['price']);
        return $open_order_buys;
    }

    public function getOpenOrderSells(array $open_orders): array
    {
        $open_order_sells = array_filter($open_orders, fn($open_order) => $open_order['side'] == 'sell' && $open_order['status'] == 'open');
        uasort($open_order_sells, fn($a, $b) => $a['price'] <=> $b['price']);
        return $open_order_sells;
    }

    public function getNeedCancelOrderBuys(array $open_order_buys, array $grid_buys): array
    {
        foreach ($open_order_buys as $open_order_buy) {
            $is_in_grid = false;
            foreach ($grid_buys as $grid_buy) {
                if (Math::compareFloats($open_order_buy['price'], $grid_buy)) {
                    $is_in_grid = true;
                    break;
                }
            }
            if (!$is_in_grid)
                $need_cancel_order_buys[] = $open_order_buy['id'];
        }
        return $need_cancel_order_buys ?? [];
    }

    public function getNeedCancelOrderSells(array $open_order_sells, array $grid_sells): array
    {
        foreach ($open_order_sells as $open_order_sell) {
            $is_in_grid = false;
            foreach ($grid_sells as $grid_sell) {
                if (Math::compareFloats($open_order_sell['price'], $grid_sell)) {
                    $is_in_grid = true;
                    break;
                }
            }
            if (!$is_in_grid)
                $need_cancel_order_sells[] = $open_order_sell['id'];
        }
        return $need_cancel_order_sells ?? [];
    }

    public function getGridStatusBuy(array $grid_buys, array $open_order_buys, int $count_real_orders_buy): array
    {
        return $this->getGridStatus($grid_buys, $open_order_buys, $count_real_orders_buy);
    }

    public function getGridStatusSell(array $grid_sells, array $open_order_sells, int $count_real_orders_sell): array
    {
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