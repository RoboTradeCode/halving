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
        print_r($this->market_info);
        echo PHP_EOL;
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
}