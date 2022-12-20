<?php

namespace Tests\Data;

use Src\Data\Binance;
use PHPUnit\Framework\TestCase;

class BinanceTest extends TestCase
{
    /** @test */
    public function it_has_precisions_for_btc_usdt()
    {
        $market_info = Binance::INFO_MARKETS['BTC/USDT'];
        $this->assertIsFloat($market_info['precision']['amount']);
        $this->assertIsFloat($market_info['precision']['price']);
    }

    /** @test */
    public function it_has_limits_for_btc_usdt()
    {
        $market_info = Binance::INFO_MARKETS['BTC/USDT'];
        $this->assertArrayHasKey('min', $market_info['limits']['amount']);
        $this->assertArrayHasKey('min', $market_info['limits']['price']);
        $this->assertArrayHasKey('min', $market_info['limits']['cost']);
        $this->assertArrayHasKey('min', $market_info['limits']['market']);
        $this->assertArrayHasKey('max', $market_info['limits']['amount']);
        $this->assertArrayHasKey('max', $market_info['limits']['price']);
        $this->assertArrayHasKey('max', $market_info['limits']['cost']);
        $this->assertArrayHasKey('max', $market_info['limits']['market']);
    }
}
