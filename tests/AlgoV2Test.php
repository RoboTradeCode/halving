<?php

namespace Tests;

use Mockery;
use Src\AlgoV2;
use PHPUnit\Framework\TestCase;
use Src\Ccxt;
use Src\Data\Binance;
use Src\HalvingV2;

class AlgoV2Test extends TestCase
{
    protected Ccxt $ccxt;

    protected function setUp(): void
    {
        $this->ccxt = $this->createMock(Ccxt::class);
        $this->ccxt->expects($this->once())->method('getMarketInfo')->with('BTC/USDT')->willReturn(Binance::INFO_MARKETS['BTC/USDT']);
    }

    /** @test */
    public function run_algorithm_with_no_open_orders_create_orders_on_full_balance()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls([], []);

        $this->ccxt->expects($this->never())->method('cancelOrder');

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 1, 'used' => 0, 'total' => 1], 'USDT' => ['free' => 10000, 'used' => 0, 'total' => 10000]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[14900, 0.1], [14800, 0.2]], 'asks' => [[15100, 0.15], [15200, 0.4]]]);

        $this->ccxt->expects($this->once())->method('getMyTrades')->willReturnOnConsecutiveCalls([]);

        $this->ccxt->expects($this->exactly(4))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'buy', 0.4, 12500], [$symbol, 'limit', 'buy', 0.49999, 10000], [$symbol, 'limit', 'sell', 0.49999, 17500], [$symbol, 'limit', 'sell', 0.49999, 20000]);

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
            ->willReturn(['BTC' => ['free' => 1, 'used' => 0, 'total' => 1], 'USDT' => ['free' => 10000, 'used' => 0, 'total' => 10000]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[14900, 0.1], [14800, 0.2]], 'asks' => [[15100, 0.15], [15200, 0.4]]]);

        $this->ccxt->expects($this->once())->method('getMyTrades')->willReturnOnConsecutiveCalls([]);

        $this->ccxt->expects($this->exactly(4))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'buy', 0.4, 12500], [$symbol, 'limit', 'buy', 0.49999, 10000], [$symbol, 'limit', 'sell', 0.49999, 17500], [$symbol, 'limit', 'sell', 0.49999, 20000]);

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    /** @test */
    public function run_algorithm_with_no_free_balances()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;
        $open_orders = [
            ['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 10000],
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 12500],
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17500],
            ['id' => 3, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 20000]
        ];

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls($open_orders, $open_orders);

        $this->ccxt->expects($this->never())->method('cancelOrder');

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 0.00002, 'used' => 0.99998, 'total' => 0.00002], 'USDT' => ['free' => 0, 'used' => 10000, 'total' => 10000]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[14900, 0.1], [14800, 0.2]], 'asks' => [[15100, 0.15], [15200, 0.4]]]);

        $this->ccxt->expects($this->once())->method('getMyTrades')->willReturnOnConsecutiveCalls([]);

        $this->ccxt->expects($this->never())->method('createOrder');

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    /** @test */
    public function run_algorithm_with_free_balances_and_filled_orders_buy()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;
        $open_orders = [
            ['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 10000],
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 12500]
        ];

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls($open_orders, $open_orders);

        $this->ccxt->expects($this->never())->method('cancelOrder');

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 0.00002, 'used' => 0.99998, 'total' => 0.00002], 'USDT' => ['free' => 5000, 'used' => 10000, 'total' => 15000]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[20900, 0.1], [14800, 0.2]], 'asks' => [[21100, 0.15], [15200, 0.4]]]);

        $this->ccxt->expects($this->once())->method('getMyTrades')->willReturnOnConsecutiveCalls([]);

        $this->ccxt->expects($this->exactly(3))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'buy', 0.08333, 20000], [$symbol, 'limit', 'buy', 0.09523, 17500], [$symbol, 'limit', 'buy', 0.11111, 15000]);

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    /** @test */
    public function run_algorithm_with_free_balances_and_filled_orders_sell()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;
        $open_orders = [
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17500],
            ['id' => 3, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 20000]
        ];

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls($open_orders, $open_orders);

        $this->ccxt->expects($this->never())->method('cancelOrder');

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 1, 'used' => 0.99998, 'total' => 1.99998], 'USDT' => ['free' => 0, 'used' => 0, 'total' => 0]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[8900, 0.1], [8800, 0.2]], 'asks' => [[9100, 0.15], [9200, 0.4]]]);

        $this->ccxt->expects($this->once())->method('getMyTrades')->willReturnOnConsecutiveCalls([]);

        $this->ccxt->expects($this->exactly(3))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'sell', 0.33333, 10000], [$symbol, 'limit', 'sell', 0.33333, 12500], [$symbol, 'limit', 'sell', 0.33333, 15000]);

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    /** @test */
    public function run_algorithm_with_not_enough_balance()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;
        $open_orders = [
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17500],
            ['id' => 3, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 20000]
        ];

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls($open_orders, $open_orders);

        $this->ccxt->expects($this->never())->method('cancelOrder');

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 0.0001, 'used' => 0.99998, 'total' => 1.00008], 'USDT' => ['free' => 0, 'used' => 0, 'total' => 0]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[8900, 0.1], [8800, 0.2]], 'asks' => [[9100, 0.15], [9200, 0.4]]]);

        $this->ccxt->expects($this->once())->method('getMyTrades')->willReturnOnConsecutiveCalls([]);

        $this->ccxt->expects($this->never())->method('createOrder')
            ->withConsecutive();

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    /** @test */
    public function run_algorithm_with_not_create_order_by_last_trade()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;
        $open_orders = [
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17500],
            ['id' => 3, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 20000]
        ];

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls($open_orders, $open_orders);

        $this->ccxt->expects($this->never())->method('cancelOrder');

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 1, 'used' => 0.99998, 'total' => 1.99998], 'USDT' => ['free' => 0, 'used' => 0, 'total' => 0]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[8900, 0.1], [8800, 0.2]], 'asks' => [[9100, 0.15], [9200, 0.4]]]);

        $this->ccxt->expects($this->once())->method('getMyTrades')
            ->willReturnOnConsecutiveCalls([
                [
                    'id' => 2394059806,
                    'symbol' => 'BTC/USDT',
                    'side' => 'buy',
                    'price' => 10000,
                    'amount' => 0.00131,
                ]
            ]);

        $this->ccxt->expects($this->exactly(2))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'sell', 0.33333, 12500], [$symbol, 'limit', 'sell', 0.33333, 15000]);

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    /** @test */
    public function run_algorithm_with_free_balances_only_on_min_deal_amount()
    {
        $symbol = 'BTC/USDT';
        $low = 10000;
        $high = 20000;
        $count_of_orders = 5;
        $open_orders = [
            ['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 10000],
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 12500]
        ];

        $this->ccxt->expects($this->exactly(2))->method('getOpenOrders')
            ->withConsecutive([$symbol], [$symbol])
            ->willReturnOnConsecutiveCalls($open_orders, $open_orders);

        $this->ccxt->expects($this->never())->method('cancelOrder');

        $this->ccxt->expects($this->once())->method('getBalances')
            ->with(explode('/', $symbol))
            ->willReturn(['BTC' => ['free' => 0.00002, 'used' => 0.99998, 'total' => 0.00002], 'USDT' => ['free' => 50, 'used' => 10000, 'total' => 10050]]);

        $this->ccxt->expects($this->once())->method('getOrderbook')
            ->with($symbol)
            ->willReturn(['bids' => [[20900, 0.1], [14800, 0.2]], 'asks' => [[21100, 0.15], [15200, 0.4]]]);

        $this->ccxt->expects($this->once())->method('getMyTrades')->willReturnOnConsecutiveCalls([]);

        $this->ccxt->expects($this->exactly(2))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'buy', 0.001, 20000], [$symbol, 'limit', 'buy', 0.00114, 17500]);

        $algo = $this->getAlgo($symbol, $low, $high, $count_of_orders);
        $algo->run();
    }

    protected function getAlgo($symbol, $low, $high, $count_of_orders): array|Mockery\Mock|AlgoV2
    {
        $halving = Mockery::mock(HalvingV2::class, [$this->ccxt->getMarketInfo('BTC/USDT')])->makePartial();
        $halving->shouldAllowMockingProtectedMethods()->shouldReceive('log');

        $algo = Mockery::mock(AlgoV2::class, [$this->ccxt, $symbol, $low, $high, $count_of_orders])->makePartial();
        $algo->shouldAllowMockingProtectedMethods()->shouldReceive('createHalving')->andReturn($halving);

        return $algo;
    }
}
