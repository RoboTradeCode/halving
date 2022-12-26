<?php

namespace Src;

class HalvingV2 extends Halving
{
    public function getGridStatusesAndDealAmount(array $grid, array $open_orders, float $balance_total, float $price, string $side): array
    {
        $grid_by_side = $this->getGridBySide($grid, $price, $side);
        $count_real_orders = count($grid_by_side);
        if ($count_real_orders > 0) {
            return $this->getGridStatusBySideV2($grid_by_side, $open_orders, $balance_total, $count_real_orders, $side);
        }
        return [];
    }

    public function getGridStatusBySideV2(array $grids, array $open_orders, float $balance_total, int $count_real_orders, string $side): array
    {
        $open_orders = array_filter($open_orders, fn($open_order) => $open_order['side'] == $side && $open_order['status'] == 'open');
        $count_add_real_orders = $count_real_orders - count($open_orders);
        if ($count_add_real_orders > 0) {
            $deal_amount = $balance_total / $count_add_real_orders;
            foreach ($grids as $key => $grid) {
                $grid_status[$key] = ['price' => $grid, 'side' => $side, 'amount' => $this->getDealAmountBySideV2($deal_amount, $grid, $side)];
                foreach ($open_orders as $open_order) {
                    if (Math::compareFloats($open_order['price'], $grid)) {
                        $grid_status[$key]['need'] = false;
                        continue 2;
                    }
                }
                $grid_status[$key]['need'] = true;
            }
        }
        return $grid_status ?? [];
    }

    public function getDealAmountBySideV2(float $balance, float $price, string $side): float
    {
        if ($side == 'buy') {
            return Math::incrementNumber($balance / $price, $this->market_info['precision_amount']);
        }
        return Math::incrementNumber($balance, $this->market_info['precision_amount']);
    }

    public function cancelAndCreateOrdersV2(array $grid_status_buys, array $grid_status_sells, string $symbol, Ccxt $bot, float $min_deal_amount): void
    {
        foreach (array_merge($grid_status_buys, $grid_status_sells) as $need_create) {
            if ($need_create['need']) {
                if ($need_create['amount'] > $min_deal_amount) {
                    $bot->createOrder($symbol, 'limit', $need_create['side'], round($need_create['amount'], 8), $need_create['price']);

                    $this->log('[' . $need_create['side'] . '] Create order: ' . $need_create['price'] . ', ' . $need_create['amount']);
                }
            }
        }
    }
}