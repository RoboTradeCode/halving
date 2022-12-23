<?php

namespace Src;

class Algo
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
        [$grid_status_buys, $deal_amount_buy] = $halving->getGridStatusesAndDealAmount($grid, $open_orders, $balances[$quote_asset]['total'], $price, 'buy');
        // [END] BUY POSITIONS

        // [START] SELL POSITIONS
        [$grid_status_sells, $deal_amount_sell] = $halving->getGridStatusesAndDealAmount($grid, $open_orders, $balances[$base_asset]['total'], $price, 'sell');
        // [END] SELL POSITIONS

        // [START] CANCEL AND CREATE ORDERS
        $halving->cancelAndCreateOrders($grid_status_buys, $deal_amount_buy, $grid_status_sells, $deal_amount_sell, $this->symbol, $this->bot);
        // [END] CANCEL AND CREATE ORDERS
    }

    protected function createHalving(): Halving
    {
        return new Halving($this->bot->getMarketInfo($this->symbol));
    }
}