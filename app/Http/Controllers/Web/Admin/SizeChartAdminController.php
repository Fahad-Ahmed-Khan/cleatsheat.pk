<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Gender;
use App\Enums\ShoeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSizeChartRequest;
use App\Http\Requests\Admin\UpdateSizeChartRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Models\SizeChart;
use App\Services\Catalog\SizeChartManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SizeChartAdminController extends Controller
{
    public function __construct(
        private readonly SizeChartManagementService $sizeCharts,
    ) {}

    public function index(): Response
    {
        $charts = SizeChart::query()
            ->with('brand')
            ->withCount('rows')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/SizeCharts/Index', [
            'charts' => $charts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/SizeCharts/Create', [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'enums' => $this->chartEnums(),
        ]);
    }

    public function store(StoreSizeChartRequest $request): RedirectResponse
    {
        $this->sizeCharts->store($request->validated());

        return redirect()->route('admin.size-charts.index')->with('status', 'Size chart created');
    }

    public function edit(SizeChart $sizeChart): Response
    {
        $sizeChart->load('rows');

        return Inertia::render('Admin/SizeCharts/Edit', [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'enums' => $this->chartEnums(),
            'chart' => [
                'id' => $sizeChart->id,
                'brand_id' => $sizeChart->brand_id,
                'name' => $sizeChart->name,
                'gender' => $sizeChart->gender?->value ?? '',
                'shoe_type' => $sizeChart->shoe_type?->value ?? '',
                'rows' => $sizeChart->rows->map(fn ($r) => [
                    'sort_order' => $r->sort_order,
                    'label' => $r->label,
                    'uk_size' => $r->uk_size,
                    'eu_size' => $r->eu_size,
                    'pk_size' => $r->pk_size,
                    'foot_cm' => $r->foot_cm !== null ? (float) $r->foot_cm : null,
                ])->values()->all(),
            ],
        ]);
    }

    public function update(UpdateSizeChartRequest $request, SizeChart $sizeChart): RedirectResponse
    {
        $this->sizeCharts->update($sizeChart, $request->validated());

        return redirect()->route('admin.size-charts.index')->with('status', 'Size chart updated');
    }

    public function destroy(SizeChart $sizeChart): RedirectResponse
    {
        if (Product::query()->where('size_chart_id', $sizeChart->id)->exists()) {
            return redirect()->route('admin.size-charts.index')->withErrors(['chart' => 'Chart is linked to one or more products']);
        }

        $sizeChart->delete();

        return redirect()->route('admin.size-charts.index')->with('status', 'Size chart deleted');
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function chartEnums(): array
    {
        $map = static fn (\BackedEnum $e): array => [
            'value' => $e->value,
            'label' => Str::headline($e->name),
        ];

        return [
            'gender' => array_merge(
                [['value' => '', 'label' => 'Any']],
                array_map($map, Gender::cases())
            ),
            'shoe_type' => array_merge(
                [['value' => '', 'label' => 'Any']],
                array_map($map, ShoeType::cases())
            ),
        ];
    }
}
