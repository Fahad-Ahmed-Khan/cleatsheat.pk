<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use App\Models\SizeChart;

class SizeChartResolver
{
    public function resolveForProduct(Product $product): ?SizeChart
    {
        if ($product->size_chart_id) {
            return SizeChart::query()
                ->with('rows')
                ->find($product->size_chart_id);
        }

        $brandId = $product->brand_id;

        $query = SizeChart::query()
            ->where('brand_id', $brandId)
            ->with('rows');

        $charts = $query->get();

        if ($charts->isEmpty()) {
            return null;
        }

        $gender = $product->gender->value;
        $shoeType = $product->shoe_type->value;

        $best = $charts->first(function (SizeChart $chart) use ($gender, $shoeType) {
            $g = $chart->gender?->value;
            $t = $chart->shoe_type?->value;

            $genderMatch = $g === null || $g === $gender;
            $typeMatch = $t === null || $t === $shoeType;

            return $genderMatch && $typeMatch;
        });

        return $best ?? $charts->first(function (SizeChart $chart) {
            return $chart->gender === null && $chart->shoe_type === null;
        }) ?? $charts->first();
    }
}
