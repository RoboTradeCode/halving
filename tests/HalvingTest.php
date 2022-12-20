<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Ccxt;
use Src\Data\Binance;
use Src\Halving;
use TRegx\DataProvider\DataProviders;

class HalvingTest extends TestCase
{
    protected Ccxt $ccxt;
    protected Halving $halving;

    protected function setUp(): void
    {
        $this->ccxt = $this->createMock(Ccxt::class);
        $this->ccxt->expects($this->once())->method('getMarketInfo')->with('BTC/USDT')->willReturn(Binance::INFO_MARKETS['BTC/USDT']);
        $this->halving = new Halving($this->ccxt->getMarketInfo('BTC/USDT'));
    }

    /**
     * @test
     * @dataProvider provideLowHighCount
     */
    public function it_get_correct_grid($low, $high, $count_of_orders)
    {
        $grid = $this->halving->getGrid($low, $high, $count_of_orders);
        $this->assertCount($count_of_orders, $grid);
        $this->assertEquals($low, min($grid));
        $this->assertEquals($high, max($grid));
        $this->assertSameSize(array_unique($grid), $grid);
    }

    /**
     * @test
     * @dataProvider provideBestPlacesOfOrderbook
     */
    public function it_get_correct_price($bid, $ask)
    {
        $price = $this->halving->getPrice(['bids' => [[$bid, 0]], 'asks' => [[$ask, 0]]]);
        $this->assertEquals(($bid + $ask) / 2, $price);
    }

    /**
     * @test
     * @dataProvider provideLowHighCountAndBestPlacesOfOrderbook
     */
    public function it_get_correct_grid_buy($low, $high, $count_of_orders, $bid, $ask)
    {
        $grid = $this->halving->getGrid($low, $high, $count_of_orders);
        $price = $this->halving->getPrice(['bids' => [[$bid, 0]], 'asks' => [[$ask, 0]]]);
        $grid_buys = $this->halving->getGridBySide($grid, $price, 'buy');

        $grid_buys_expected = array_filter($grid, fn($g) => $g < $price);
        rsort($grid_buys_expected);

        $this->assertEquals($grid_buys_expected, $grid_buys);
    }

    /**
     * @test
     * @dataProvider provideLowHighCountAndBestPlacesOfOrderbook
     */
    public function it_get_correct_grid_sell($low, $high, $count_of_orders, $bid, $ask)
    {
        $grid = $this->halving->getGrid($low, $high, $count_of_orders);
        $price = $this->halving->getPrice(['bids' => [[$bid, 0]], 'asks' => [[$ask, 0]]]);
        $grid_sells = $this->halving->getGridBySide($grid, $price, 'sell');

        $grid_sells_expected = array_filter($grid, fn($g) => $g > $price);
        sort($grid_sells_expected);

        $this->assertEquals($grid_sells_expected, $grid_sells);
    }

    /**
     * @test
     * @dataProvider provideBalanceQuoteCountOrdersBuyPrice
     */
    public function it_get_correct_deal_amount_buy($balance_total, $count_real_order_buys, $price, $expected)
    {
        $this->assertEquals($expected, round($this->halving->getDealAmountBySide($balance_total, $count_real_order_buys, $price, 'buy'), 10));
    }

    /**
     * @test
     * @dataProvider provideBalanceBaseCountOrders
     */
    public function it_get_correct_deal_amount_sell($balance_total, $count_real_order_sells, $expected)
    {
        $this->assertEquals($expected, round($this->halving->getDealAmountBySide($balance_total, $count_real_order_sells, 0, 'sell'), 10));
    }

    /**
     * @test
     * @dataProvider provideOpenOrdersGridsExpected
     */
    public function it_get_correct_need_cancel_orders($open_orders, $grids, $expected)
    {
        $need_cancel_orders = $this->halving->getNeedCancelOrders($open_orders, $grids);
        $this->assertEquals($expected, $need_cancel_orders);
    }

    /**
     * @test
     * @dataProvider provideOpenOrdersGridsCountRealOrdersExpectedBuy
     */
    public function it_get_grid_status_buy($open_orders, $grids, $count_real_orders, $expected)
    {
        $need_cancel_orders = $this->halving->getGridStatusBySide($grids, $open_orders, $count_real_orders, 'buy');
        $this->assertEquals($expected, $need_cancel_orders);
    }

    /**
     * @test
     * @dataProvider provideOpenOrdersGridsCountRealOrdersExpectedSell
     */
    public function it_get_grid_status_sell($open_orders, $grids, $count_real_orders, $expected)
    {
        $need_cancel_orders = $this->halving->getGridStatusBySide($grids, $open_orders, $count_real_orders, 'sell');
        $this->assertEquals($expected, $need_cancel_orders);
    }

    /**
     * @test
     * @dataProvider provideGridStatusesCancel
     */
    public function it_get_need_cancel($grid_statuses, $expected)
    {
        $need_cancel_orders = $this->halving->needCancel($grid_statuses);
        $this->assertEquals($expected, $need_cancel_orders);
    }

    /**
     * @test
     * @dataProvider provideGridStatusesCreate
     */
    public function it_get_need_create($grid_statuses, $expected)
    {
        $need_cancel_orders = $this->halving->needCreate($grid_statuses);
        $this->assertEquals($expected, $need_cancel_orders);
    }

    /** @test */
    public function it_cancel_unnecessary_open_orders()
    {
        $grid = [10000, 10500, 11000, 11500, 12000];
        $symbol = 'BTC/USDT';
        $this->ccxt->expects($this->once())->method('getOpenOrders')->with($symbol)->willReturn([
            ['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17000],
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 10000],
            ['id' => 6, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 16000],
            ['id' => 5, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 10500],
            ['id' => 3, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 19000],
            ['id' => 7, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 11000],
            ['id' => 8, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 14000],
            ['id' => 4, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 11500],
            ['id' => 9, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 12000],
            ['id' => 10, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'closed', 'price' => 9000]
        ]);
        $this->ccxt->expects($this->exactly(4))->method('cancelOrder')->withConsecutive([1, $symbol], [6, $symbol], [3, $symbol], [8, $symbol]);
        $this->halving->cancelUnnecessaryOpenOrders($grid, $symbol, $this->ccxt);
    }

    /** @test */
    public function it_get_grid_statuses_and_deal_amount_buy()
    {
        $grid = [10000, 10500, 11000, 11500, 12000];
        $open_orders = [
            ['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 10500],
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 11000],
            ['id' => 6, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 11500]
        ];
        $quote_asset = 'USDT';
        $balances = ['BTC' => ['free' => 1, 'used' => 0, 'total' => 1], 'USDT' => ['free' => 4000, 'used' => 6000, 'total' => 10000]];
        $price = 12500;

        $grid_status_buys_expected = [
            ['price' => 12000, 'need' => true, 'id' => '', 'side' => 'buy'],
            ['price' => 11500, 'need' => true, 'id' => 6, 'side' => 'buy'],
            ['price' => 11000, 'need' => true, 'id' => 2, 'side' => 'buy'],
            ['price' => 10500, 'need' => true, 'id' => 1, 'side' => 'buy'],
            ['price' => 10000, 'need' => true, 'id' => '', 'side' => 'buy']
        ];
        $deal_amount_buy_expected = 0.15999;

        [$grid_status_buys, $deal_amount_buy] = $this->halving->getGridStatusesAndDealAmount($grid, $open_orders, $balances[$quote_asset]['total'], $price, 'buy');

        $this->assertEquals($grid_status_buys_expected, $grid_status_buys);
        $this->assertEquals(round($deal_amount_buy_expected, 8), round($deal_amount_buy, 8));
    }

    /** @test */
    public function it_get_grid_statuses_and_deal_amount_sell()
    {
        $grid = [10000, 10500, 11000, 11500, 12000];
        $open_orders = [
            ['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 10500],
            ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 11000],
            ['id' => 6, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 11500]
        ];
        $base_asset = 'BTC';
        $balances = ['BTC' => ['free' => 1, 'used' => 0, 'total' => 1], 'USDT' => ['free' => 4000, 'used' => 6000, 'total' => 10000]];
        $price = 9500;

        $grid_status_sells_expected = [
            ['price' => 10000, 'need' => true, 'id' => '', 'side' => 'sell'],
            ['price' => 10500, 'need' => true, 'id' => 1, 'side' => 'sell'],
            ['price' => 11000, 'need' => true, 'id' => 2, 'side' => 'sell'],
            ['price' => 11500, 'need' => true, 'id' => 6, 'side' => 'sell'],
            ['price' => 12000, 'need' => true, 'id' => '', 'side' => 'sell']
        ];
        $deal_amount_sell_expected = 0.2;

        [$grid_status_sells, $deal_amount_sell] = $this->halving->getGridStatusesAndDealAmount($grid, $open_orders, $balances[$base_asset]['total'], $price, 'sell');

        $this->assertEquals($grid_status_sells_expected, $grid_status_sells);
        $this->assertEquals(round($deal_amount_sell_expected, 8), round($deal_amount_sell, 8));
    }

    /** @test */
    public function it_cancel_and_create_orders()
    {
        $grid_status_buys = [
            ['price' => 11500, 'need' => true, 'id' => 5, 'side' => 'buy'],
            ['price' => 11000, 'need' => true, 'id' => 4, 'side' => 'buy'],
            ['price' => 10500, 'need' => true, 'id' => '', 'side' => 'buy'],
            ['price' => 10000, 'need' => true, 'id' => '', 'side' => 'buy']
        ];
        $deal_amount_buy = 0.15;
        $grid_status_sells = [
            ['price' => 12000, 'need' => true, 'id' => '', 'side' => 'sell'],
            ['price' => 12500, 'need' => true, 'id' => 1, 'side' => 'sell'],
            ['price' => 13000, 'need' => true, 'id' => '', 'side' => 'sell'],
            ['price' => 14500, 'need' => true, 'id' => 6, 'side' => 'sell'],
            ['price' => 15000, 'need' => false, 'id' => 7, 'side' => 'sell']
        ];
        $deal_amount_sell = 0.2;
        $symbol = 'BTC/USDT';

        $this->ccxt->expects($this->once())->method('cancelOrder')->withConsecutive([7, $symbol]);
        $this->ccxt->expects($this->exactly(4))->method('createOrder')
            ->withConsecutive([$symbol, 'limit', 'buy', 0.15, 10500], [$symbol, 'limit', 'buy', 0.15, 10000], [$symbol, 'limit', 'sell', 0.2, 12000], [$symbol, 'limit', 'sell', 0.2, 13000], [$symbol, 'limit', 'sell', 0.2, 14500]);

        $this->halving->cancelAndCreateOrders($grid_status_buys, $deal_amount_buy, $grid_status_sells, $deal_amount_sell, $symbol, $this->ccxt);
    }

    public function provideLowHighCount(): array
    {
        return [
            [100, 200, 50],
            [10000, 20000, 100],
            [0, 100000, 150],
            [5000, 150000, 200],
            [6000, 45000, 20],
            [10000, 100000, 180],
            [1000000, 5000000, 150],
            [70000, 700000, 150]
        ];
    }

    public function provideBestPlacesOfOrderbook(): array
    {
        return [
            [150, 160],
            [15000, 16000],
            [30000, 30100],
            [51000, 51010],
            [43000, 44000],
            [21000, 21400],
            [1999900, 2000000],
            [98000, 100000]
        ];
    }

    public function provideLowHighCountAndBestPlacesOfOrderbook(): array
    {
        return DataProviders::cross(
            $this->provideLowHighCount(),
            $this->provideBestPlacesOfOrderbook()
        );
    }

    public function provideBalanceQuoteCountOrdersBuyPrice(): array
    {
        return [
            [100, 10, 10, 0.99999],
            [1000, 100, 10, 0.99999],
            [500, 20, 60, 0.41666],
            [15000, 100, 15000, 0.00999],
            [15000, 100, 20000, 0.00749],
            [1500, 100, 20000, 0.00075],
            [1500, 50, 20000, 0.0015],
            [1500, 100, 10000, 0.0015],
            [2000, 80, 10060, 0.00248]
        ];
    }

    public function provideBalanceBaseCountOrders(): array
    {
        return [
            [100, 10, 9.99999],
            [1000, 100, 9.99999],
            [500, 20, 25],
            [15000, 100, 149.99999],
            [15000, 100, 149.99999],
            [1500, 100, 14.99999],
            [1500, 50, 29.99999],
            [1500, 100, 14.99999],
            [2000, 80, 25],
            [0.1, 60, 0.00166],
            [1, 70, 0.01428],
            [0.001, 10, 0.0001],
            [0.5, 30, 0.01666],
            [0.546, 100, 0.00546]
        ];
    }

    public function provideOpenOrdersGridsExpected(): array
    {
        return [
            [
                [], [], []
            ],
            [
                [['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17500]], [10000, 17500, 26000], [],
            ],
            [
                [
                    ['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17000],
                    ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 18000],
                    ['id' => 6, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 16000],
                    ['id' => 5, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 21000],
                    ['id' => 3, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 19000],
                    ['id' => 7, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 15000],
                    ['id' => 8, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 14000],
                    ['id' => 4, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 20000],
                    ['id' => 9, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 13000],
                    ['id' => 10, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'closed', 'price' => 12000]
                ],
                [17000, 18000, 16000, 19000, 15000, 14000, 13000],
                [['id' => 5, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 21000], ['id' => 4, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 20000]]
            ]
        ];
    }

    public function provideOpenOrdersGridsCountRealOrdersExpectedBuy(): array
    {
        return [
            [
                [], [], 0, []
            ],
            [
                [['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 17500]],
                [10000, 17500, 26000],
                3,
                [['price' => 10000, 'need' => true, 'id' => '', 'side' => 'buy'], ['price' => 17500, 'need' => true, 'id' => 1, 'side' => 'buy'], ['price' => 26000, 'need' => true, 'id' => '', 'side' => 'buy']]
            ],
            [
                [
                    ['id' => 7, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 15000],
                    ['id' => 8, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 14000],
                    ['id' => 9, 'symbol' => 'BTC/USDT', 'side' => 'buy', 'status' => 'open', 'price' => 13000]
                ],
                [16000, 15000, 14000, 13000],
                4,
                [['price' => 16000, 'need' => true, 'id' => '', 'side' => 'buy'], ['price' => 15000, 'need' => true, 'id' => 7, 'side' => 'buy'], ['price' => 14000, 'need' => true, 'id' => 8, 'side' => 'buy'], ['price' => 13000, 'need' => true, 'id' => 9, 'side' => 'buy']]
            ]
        ];
    }

    public function provideOpenOrdersGridsCountRealOrdersExpectedSell(): array
    {
        return [
            [
                [], [], 0, []
            ],
            [
                [['id' => 1, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 17500]],
                [10000, 17500, 26000],
                3,
                [['price' => 10000, 'need' => true, 'id' => '', 'side' => 'sell'], ['price' => 17500, 'need' => true, 'id' => 1, 'side' => 'sell'], ['price' => 26000, 'need' => true, 'id' => '', 'side' => 'sell']],
            ],
            [
                [
                    ['id' => 2, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 18000],
                    ['id' => 3, 'symbol' => 'BTC/USDT', 'side' => 'sell', 'status' => 'open', 'price' => 19000]
                ],
                [17000, 18000, 19000],
                2,
                [['price' => 17000, 'need' => true, 'id' => '', 'side' => 'sell'], ['price' => 18000, 'need' => true, 'id' => 2, 'side' => 'sell'], ['price' => 19000, 'need' => false, 'id' => 3, 'side' => 'sell']]
            ]
        ];
    }

    public function provideGridStatusesCancel(): array
    {
        return [
            [
                [['price' => 16000, 'need' => false, 'id' => 1, 'side' => 'buy'], ['price' => 16000, 'need' => false, 'id' => 2, 'side' => 'buy'], ['price' => 16000, 'need' => true, 'id' => 3, 'side' => 'buy'], ['price' => 16000, 'need' => true, 'id' => 4, 'side' => 'buy'], ['price' => 16000, 'need' => true, 'id' => '', 'side' => 'buy'], ['price' => 16000, 'need' => false, 'id' => 5, 'side' => 'buy']],
                [['price' => 16000, 'need' => false, 'id' => 1, 'side' => 'buy'], ['price' => 16000, 'need' => false, 'id' => 2, 'side' => 'buy'], 5 => ['price' => 16000, 'need' => false, 'id' => 5, 'side' => 'buy']]
            ]
        ];
    }

    public function provideGridStatusesCreate(): array
    {
        return [
            [
                [['price' => 16000, 'need' => true, 'id' => '', 'side' => 'buy'], ['price' => 16000, 'need' => false, 'id' => '', 'side' => 'buy'], ['price' => 16000, 'need' => true, 'id' => 3, 'side' => 'buy'], ['price' => 17000, 'need' => true, 'id' => '', 'side' => 'buy']],
                [['price' => 16000, 'need' => true, 'id' => '', 'side' => 'buy'], 3 => ['price' => 17000, 'need' => true, 'id' => '', 'side' => 'buy']]
            ]
        ];
    }
}