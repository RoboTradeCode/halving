<?php

namespace Tests;

use Mockery;
use Src\Algo;
use PHPUnit\Framework\TestCase;
use Src\Ccxt;
use Src\Data\Binance;
use Src\Halving;

class AlgoTest extends TestCase
{
    protected Ccxt $ccxt;

    protected function setUp(): void
    {
        $this->ccxt = $this->createMock(Ccxt::class);
        $this->ccxt->expects($this->once())->method('getMarketInfo')->with('BTC/USDT')->willReturn(Binance::INFO_MARKETS['BTC/USDT']);

//        $halving = new Halving($this->ccxt->getMarketInfo('BTC/USDT'));
//        $grid = $halving->getGrid($low, $high, $count_of_orders);
//        $price = $halving->getPrice(['bids' => [[14900, 0.1], [14800, 0.2]], 'asks' => [[15100, 0.15], [15200, 0.4]]]);
//        [$grid_status_buys, $deal_amount_buy] = $halving->getGridStatusesAndDealAmount($grid, [], 10000, $price, 'buy');
//        [$grid_status_sells, $deal_amount_sell] = $halving->getGridStatusesAndDealAmount($grid, [], 1, $price, 'sell');
//        print_r($grid);
//        echo PHP_EOL;
//        print_r($price);
//        echo PHP_EOL;
//        print_r($grid_status_buys);
//        echo PHP_EOL;
//        print_r($deal_amount_buy);
//        echo PHP_EOL;
//        print_r($grid_status_sells);
//        echo PHP_EOL;
//        print_r($deal_amount_sell);
//        echo PHP_EOL;
//        die();
    }

    /** @test */
    public function run_algorithm_with_no_open_orders()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls([], []);

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 1, 'used' => 0, 'total' => 1], 'USDT' => ['free' => 4000, 'used' => 6000, 'total' => 10000]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[14900, 0.1], [14800, 0.2]], 'asks' => [[15100, 0.15], [15200, 0.4]]]);

        $this->ccxt->expects($this->never())->method('cancelOrder');

        $this->ccxt->expects($this->exactly(4))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'buy', 0.33333, 12500], [$symbol, 'limit', 'buy', 0.33333, 10000], [$symbol, 'limit', 'sell', 0.49999, 17500], [$symbol, 'limit', 'sell', 0.49999, 20000]);

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    /** @test */
    public function run_algorithm_with_cancel_all_current_open_orders()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls([
                ['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17000],
                ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 18000],
                ['id' => 3, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 14500],
                ['id' => 4, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 14000]
            ], []);

        $this->ccxt->expects($this->exactly(4))->method('cancelOrder')
            ->withConsecutive([1, $symbol], [2, $symbol], [3, $symbol], [4, $symbol]);

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 1, 'used' => 0, 'total' => 1], 'USDT' => ['free' => 4000, 'used' => 6000, 'total' => 10000]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[14900, 0.1], [14800, 0.2]], 'asks' => [[15100, 0.15], [15200, 0.4]]]);

        $this->ccxt->expects($this->exactly(4))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'buy', 0.33333, 12500], [$symbol, 'limit', 'buy', 0.33333, 10000], [$symbol, 'limit', 'sell', 0.49999, 17500], [$symbol, 'limit', 'sell', 0.49999, 20000]);

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    protected function getAlgo($symbol, $low, $high, $count_of_orders): array|Mockery\Mock|Algo
    {
        $halving = Mockery::mock(Halving::class, [$this->ccxt->getMarketInfo('BTC/USDT')])->makePartial();
        $halving->shouldAllowMockingProtectedMethods()->shouldReceive('log');

        $algo = Mockery::mock(Algo::class, [$this->ccxt, $symbol, $low, $high, $count_of_orders])->makePartial();
        $algo->shouldAllowMockingProtectedMethods()->shouldReceive('createHalving')->andReturn($halving);

        return $algo;
    }
}
