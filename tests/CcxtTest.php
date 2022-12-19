<?php

namespace Tests;

use ccxt\Exchange;
use PHPUnit\Framework\TestCase;
use Src\Ccxt;
use Src\Data\Binance;

class CcxtTest extends TestCase
{
    protected Ccxt $exchange;

    protected function setUp(): void
    {
        $this->createMock(Exchange::class);
        $this->exchange = new Ccxt('binance', '', '');
    }

    /** @test */
    public function it_get_market_info_on_binance_for_btc_usdt()
    {
        $this->assertEquals(Binance::INFO_MARKETS['BTC/USDT'], $this->exchange->getMarketInfo('BTC/USDT'));
    }
}
