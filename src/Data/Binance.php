<?php

namespace Src\Data;

class Binance
{
    const INFO_MARKETS = [
        'BTC/USDT' => [
            'id' => 'BTCUSDT',
            'symbol' => 'BTC/USDT',
            'base' => 'BTC',
            'quote' => 'USDT',
            'precision' => ['amount' => 0.00001, 'price' => 0.01],
            'limits' => [
                'amount' => ['min' => 1.0E-5, 'max' => 9000.0,],
                'price' => ['min' => 0.01, 'max' => 1000000.0],
                'cost' => ['min' => 10.0, 'max' => NULL],
                'market' => ['min' => 0.0, 'max' => 328.8041295]
            ],
            'percentage' => true,
            'taker' => 0.001,
            'maker' => 0.001,
            'lowercaseId' => 'btcusdt',
        ]
    ];
}