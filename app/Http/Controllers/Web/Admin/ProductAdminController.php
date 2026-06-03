<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FitGuidance;
use App\Enums\Gender;
use App\Enums\ShoeType;
use App\Exports\ProductsFlatExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\SizeChart;
use App\Models\VariantSize;
use App\Services\Catalog\ProductBulkImportService;
use App\Services\Catalog\ProductManagementService;
use App\Support\Admin\AdminProductListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ProductAdminController extends Controller
{
    public function __construct(
        private readonly ProductManagementService $products,
        private readonly ProductBulkImportService $bulkImport,
    ) {}

    public function index(): Response
    {
        $list = AdminProductListQuery::fromRequest(request());
        $filters = $list->filtersForInertia();

        $products = $list->forIndex()
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $filters['search'],
                'brand_id' => $filters['brand_id'],
                'category_id' => $filters['category_id'],
                'status' => $filters['status'],
                'stock' => $filters['stock'],
                'color_id' => $filters['color_id'],
                'size' => $filters['size'],
                'price_min' => $filters['price_min'],
                'price_max' => $filters['price_max'],
                'per_page' => $filters['per_page'],
            ],
            'stats' => [
                'total' => Product::query()->count(),
                'active' => Product::query()->where('is_active', true)->count(),
                'inactive' => Product::query()->where('is_active', false)->count(),
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'colors' => Color::query()->orderBy('name')->get(['id', 'name', 'hex']),
            'sizes' => VariantSize::query()
                ->select('size_label')
                ->distinct()
                ->orderBy('size_label')
                ->pluck('size_label')
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Create', $this->sharedFormPayload());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->products->store($request->validated());

        return redirect()->route('admin.products.index')->with('status', 'Product created');
    }

    public function edit(Product $product): Response
    {
        $product->load(['images', 'variants.sizes', 'brand', 'category', 'sizeChart']);

        $payload = $this->sharedFormPayload();
        $payload['product'] = $this->transformProduct($product);

        return Inertia::render('Admin/Products/Edit', $payload);
    }

    public function show(Product $product): Response
    {
        $product->load([
            'images',
            'brand:id,name',
            'category:id,name',
            'sizeChart:id,name',
            'variants.color:id,name,hex',
            'variants.sizes:id,product_variant_id,size_label,uk_size,eu_size,pk_size,stock_qty,low_stock_threshold',
        ]);

        return Inertia::render('Admin/Products/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'is_active' => (bool) $product->is_active,
                'description' => $product->description,
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'canonical_url' => $product->canonical_url,
                'video_url' => $product->video_url,
                'video_poster' => $product->video_poster,
                'fit_guidance' => $product->fit_guidance?->value,
                'gender' => $product->gender?->value,
                'shoe_type' => $product->shoe_type?->value,
                'fit_notes' => $product->fit_notes,
                'size_info' => $product->size_info,
                'features' => is_array($product->features) ? $product->features : [],
                'brand' => $product->brand ? ['id' => $product->brand->id, 'name' => $product->brand->name] : null,
                'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->name] : null,
                'size_chart' => $product->sizeChart ? ['id' => $product->sizeChart->id, 'name' => $product->sizeChart->name] : null,
                'images' => $product->images->map(fn ($img) => [
                    'path' => \App\Support\Storage\PublicAssetUrl::resolve($img->path),
                    'alt' => $img->alt,
                    'sort_order' => $img->sort_order,
                ])->values()->all(),
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'price' => (float) $v->price,
                    'compare_at_price' => $v->compare_at_price !== null ? (float) $v->compare_at_price : null,
                    'bargain_enabled' => (bool) $v->bargain_enabled,
                    'bargain_min_price' => $v->bargain_min_price !== null ? (float) $v->bargain_min_price : null,
                    'bargain_max_discount_percent' => $v->bargain_max_discount_percent !== null ? (float) $v->bargain_max_discount_percent : null,
                    'is_active' => (bool) $v->is_active,
                    'color' => $v->color ? [
                        'id' => $v->color->id,
                        'name' => $v->color->name,
                        'hex' => $v->color->hex,
                    ] : null,
                    'sizes' => $v->sizes
                        ->sortBy('size_label')
                        ->values()
                        ->map(fn ($s) => [
                            'id' => $s->id,
                            'size_label' => $s->size_label,
                            'uk_size' => $s->uk_size,
                            'eu_size' => $s->eu_size,
                            'pk_size' => $s->pk_size,
                            'stock_qty' => (int) $s->stock_qty,
                            'low_stock_threshold' => (int) $s->low_stock_threshold,
                        ])
                        ->all(),
                    'stock_total' => (int) $v->sizes->sum('stock_qty'),
                ])->values()->all(),
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->update($product, $request->validated());

        return redirect()->route('admin.products.index')->with('status', 'Product updated');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product removed');
    }

    public function toggleActive(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $product->update([
            'is_active' => (bool) $data['is_active'],
        ]);

        return back()->with('status', 'Product updated');
    }

    public function variants(Product $product): JsonResponse
    {
        $product->load([
            'variants.color:id,name,hex',
            'variants.sizes:id,product_variant_id,size_label,stock_qty,low_stock_threshold',
        ]);

        return response()->json([
            'product_id' => $product->id,
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => (float) $v->price,
                'compare_at_price' => $v->compare_at_price !== null ? (float) $v->compare_at_price : null,
                'is_active' => (bool) $v->is_active,
                'color' => $v->color ? [
                    'id' => $v->color->id,
                    'name' => $v->color->name,
                    'hex' => $v->color->hex,
                ] : null,
                'sizes' => $v->sizes
                    ->sortBy('size_label')
                    ->values()
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'size_label' => $s->size_label,
                        'stock_qty' => (int) $s->stock_qty,
                        'low_stock_threshold' => (int) $s->low_stock_threshold,
                    ])
                    ->all(),
                'stock_total' => (int) $v->sizes->sum('stock_qty'),
            ])->values()->all(),
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $list = AdminProductListQuery::fromRequest($request);
        $export = new ProductsFlatExport($list->forExport());
        $filename = 'products-export-'.now()->format('Y-m-d-His');

        return match ($format) {
            'csv' => Excel::download($export, $filename.'.csv', ExcelFormat::CSV),
            default => Excel::download($export, $filename.'.xlsx', ExcelFormat::XLSX),
        };
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:12288'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $result = $this->bulkImport->importFromSpreadsheet($file);

        $summary = "Import finished: {$result['created']} created, {$result['updated']} updated.";
        if ($result['errors'] !== []) {
            $detail = collect($result['errors'])->take(8)->map(fn ($e) => "Line {$e['line']}: {$e['message']}")->implode(' ');

            return back()->with('error', $summary.' '.$detail);
        }

        return back()->with('status', $summary);
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedFormPayload(): array
    {
        return [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'colors' => Color::query()->orderBy('name')->get(['id', 'name', 'slug', 'hex']),
            'size_charts' => SizeChart::query()->with('brand')->orderBy('name')->get()->map(fn (SizeChart $c) => [
                'id' => $c->id,
                'label' => $c->name.' — '.$c->brand->name,
            ]),
            'enums' => $this->enumSelects(),
        ];
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function enumSelects(): array
    {
        $map = static fn (string $name, string $value): array => [
            'value' => $value,
            'label' => Str::headline($name),
        ];

        return [
            'fit_guidance' => array_map(
                fn (FitGuidance $c) => $map($c->name, $c->value),
                FitGuidance::cases()
            ),
            'gender' => array_map(
                fn (Gender $c) => $map($c->name, $c->value),
                Gender::cases()
            ),
            'shoe_type' => array_map(
                fn (ShoeType $c) => $map($c->name, $c->value),
                ShoeType::cases()
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'size_chart_id' => $product->size_chart_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'canonical_url' => $product->canonical_url,
            'video_url' => $product->video_url,
            'video_poster' => $product->video_poster,
            'fit_guidance' => $product->fit_guidance->value,
            'gender' => $product->gender->value,
            'shoe_type' => $product->shoe_type->value,
            'fit_notes' => $product->fit_notes,
            'size_info' => $product->size_info,
            'features' => $product->features ?? [],
            'is_active' => $product->is_active,
            'images' => $product->images->map(fn ($img) => [
                'path' => \App\Support\Storage\PublicAssetUrl::resolve($img->path),
                'alt' => $img->alt,
                'sort_order' => $img->sort_order,
            ])->values()->all(),
            'variants' => $product->variants->map(fn ($v) => [
                'color_id' => $v->color_id,
                'sku' => $v->sku,
                'price' => (float) $v->price,
                'compare_at_price' => $v->compare_at_price !== null ? (float) $v->compare_at_price : null,
                'is_active' => $v->is_active,
                'sizes' => $v->sizes->map(fn ($s) => [
                    'size_label' => $s->size_label,
                    'uk_size' => $s->uk_size,
                    'eu_size' => $s->eu_size,
                    'pk_size' => $s->pk_size,
                    'stock_qty' => $s->stock_qty,
                    'low_stock_threshold' => $s->low_stock_threshold,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
