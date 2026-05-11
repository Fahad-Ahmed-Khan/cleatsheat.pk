<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\FitGuidance;
use App\Enums\Gender;
use App\Enums\ShoeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantSize;
use App\Models\SizeChart;
use App\Services\Catalog\ProductManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductAdminController extends Controller
{
    public function __construct(
        private readonly ProductManagementService $products,
    ) {}

    public function index(): Response
    {
        $search = trim((string) request('search', ''));
        $brandId = request('brand_id');
        $categoryId = request('category_id');
        $status = request('status'); // active | inactive | null
        $stock = request('stock'); // in | out | null
        $colorId = request('color_id');
        $sizeLabel = request('size'); // exact size_label
        $priceMin = request('price_min');
        $priceMax = request('price_max');
        $perPage = (int) request('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->withCount(['variants as variants_count'])
            ->withMin('variants as min_price', 'price')
            ->withMax('variants as max_price', 'price')
            ->addSelect([
                'primary_sku' => ProductVariant::query()
                    ->select('sku')
                    ->whereColumn('product_id', 'products.id')
                    ->orderBy('id')
                    ->limit(1),
                'sizes_count' => ProductVariant::query()
                    ->selectRaw('COUNT(variant_sizes.id)')
                    ->join('variant_sizes', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id'),
                'stock_total' => ProductVariant::query()
                    ->selectRaw('COALESCE(SUM(variant_sizes.stock_qty), 0)')
                    ->join('variant_sizes', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id'),
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('variants', fn ($vq) => $vq->where('sku', 'like', "%{$search}%"))
                        ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($brandId !== null && $brandId !== '', fn ($q) => $q->where('brand_id', $brandId))
            ->when($categoryId !== null && $categoryId !== '', fn ($q) => $q->where('category_id', $categoryId))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($colorId !== null && $colorId !== '', fn ($q) => $q->whereHas('variants', fn ($vq) => $vq->where('color_id', $colorId)))
            ->when($sizeLabel !== null && $sizeLabel !== '', fn ($q) => $q->whereHas('variants.sizes', fn ($sq) => $sq->where('size_label', $sizeLabel)))
            ->when($priceMin !== null && $priceMin !== '', fn ($q) => $q->whereHas('variants', fn ($vq) => $vq->where('price', '>=', $priceMin)))
            ->when($priceMax !== null && $priceMax !== '', fn ($q) => $q->whereHas('variants', fn ($vq) => $vq->where('price', '<=', $priceMax)))
            ->when($stock === 'in', function ($q) {
                $q->whereExists(function ($sq) {
                    $sq->selectRaw('1')
                        ->from('product_variants')
                        ->join('variant_sizes', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('variant_sizes.stock_qty', '>', 0);
                });
            })
            ->when($stock === 'out', function ($q) {
                $q->whereNotExists(function ($sq) {
                    $sq->selectRaw('1')
                        ->from('product_variants')
                        ->join('variant_sizes', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('variant_sizes.stock_qty', '>', 0);
                });
            })
            ->latest();

        $products = $query
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'brand_id' => $brandId === '' ? null : $brandId,
                'category_id' => $categoryId === '' ? null : $categoryId,
                'status' => $status,
                'stock' => $stock,
                'color_id' => $colorId === '' ? null : $colorId,
                'size' => $sizeLabel,
                'price_min' => $priceMin,
                'price_max' => $priceMax,
                'per_page' => $perPage,
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
                    'path' => $img->path,
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
                'path' => $img->path,
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
