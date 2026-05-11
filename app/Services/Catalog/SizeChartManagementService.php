<?php

namespace App\Services\Catalog;

use App\Models\SizeChart;
use App\Models\SizeChartRow;
use Illuminate\Support\Facades\DB;

class SizeChartManagementService
{
    /**
     * @param  array<string, mixed>  $data  keys: brand_id, name, gender, shoe_type, rows
     */
    public function store(array $data): SizeChart
    {
        return DB::transaction(function () use ($data) {
            $chart = SizeChart::query()->create(collect($data)->only([
                'brand_id', 'name', 'gender', 'shoe_type',
            ])->all());

            $this->syncRows($chart, $data['rows'] ?? []);

            return $chart->fresh(['brand', 'rows']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SizeChart $chart, array $data): SizeChart
    {
        return DB::transaction(function () use ($chart, $data) {
            $chart->update(collect($data)->only([
                'brand_id', 'name', 'gender', 'shoe_type',
            ])->all());

            if (array_key_exists('rows', $data)) {
                $chart->rows()->delete();
                $this->syncRows($chart, $data['rows'] ?? []);
            }

            return $chart->fresh(['brand', 'rows']);
        });
    }

    /**
     * @param  list<array{sort_order?: int, label?: string, uk_size?: string|null, eu_size?: string|null, pk_size?: string|null, foot_cm?: float|null, measurements?: array|null}>  $rows
     */
    private function syncRows(SizeChart $chart, array $rows): void
    {
        foreach ($rows as $i => $row) {
            SizeChartRow::query()->create([
                'size_chart_id' => $chart->id,
                'sort_order' => $row['sort_order'] ?? $i,
                'label' => $row['label'] ?? ($row['uk_size'] ?? '—'),
                'uk_size' => $row['uk_size'] ?? null,
                'eu_size' => $row['eu_size'] ?? null,
                'pk_size' => $row['pk_size'] ?? null,
                'foot_cm' => $row['foot_cm'] ?? null,
                'measurements' => $row['measurements'] ?? null,
            ]);
        }
    }
}
