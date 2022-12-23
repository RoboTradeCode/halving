<?php

namespace Src;

class AlgoV2
{
    public function __construct(
        protected Ccxt $bot,
        protected string $symbol,
        protected float $low,
        protected float $high,
        protected int $count_of_orders
    ) {}

    public function run(): void
    {
        // [START] PRE CALCULATIONS
        list($base_asset, $quote_asset) = explode('/', $this->symbol);
        $halving = $this->createHalving();
        // [END] PRE CALCULATIONS

        // [START] CALCULATIONS
        $grid = $halving->getGrid($this->low, $this->high, $this->count_of_orders);
        // [END] CALCULATIONS

        // [START] CANCEL UNNECESSARY OPEN ORDERS
        $halving->cancelUnnecessaryOpenOrders($grid, $this->symbol, $this->bot);
        // [END] CANCEL UNNECESSARY OPEN ORDERS

        // [START] REQUESTS TO EXCHANGE
        $balances = $this->bot->getBalances([$base_asset, $quote_asset]);
        $open_orders = $this->bot->getOpenOrders($this->symbol);
        $price = $halving->getPrice($this->bot->getOrderbook($this->symbol));
        // [END] REQUESTS TO EXCHANGE

        // [START] BUY POSITIONS
        $grid_status_buys = $halving->getGridStatusesAndDealAmount($grid, $open_orders, $balances[$quote_asset]['free'], $price, 'buy');
        // [END] BUY POSITIONS

        // [START] SELL POSITIONS
        $grid_status_sells = $halving->getGridStatusesAndDealAmount($grid, $open_orders, $balances[$base_asset]['free'], $price, 'sell');
        // [END] SELL POSITIONS

        // [START] CANCEL AND CREATE ORDERS
        $halving->cancelAndCreateOrdersV2($grid_status_buys, $grid_status_sells, $this->symbol, $this->bot);
        // [END] CANCEL AND CREATE ORDERS
    }

    protected function createHalving(): HalvingV2
    {
        return new HalvingV2($this->bot->getMarketInfo($this->symbol));
    }
}