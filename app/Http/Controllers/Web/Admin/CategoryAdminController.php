<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryAdminController extends Controller
{
    public function index(): Response
    {
        $search = trim((string) request('search', ''));
        $parentId = request('parent_id');
        $hasProducts = request('has_products');

        $rootParents = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $stats = [
            'total' => Category::query()->count(),
            'root' => Category::query()->whereNull('parent_id')->count(),
            'sub' => Category::query()->whereNotNull('parent_id')->count(),
            'with_products' => Category::query()->has('products')->count(),
        ];

        if ($search !== '') {
            $query = Category::query()
                ->with('parent:id,name')
                ->withCount('products')
                ->when($search !== '', function ($q) use ($search) {
                    $q->where(function ($qq) use ($search) {
                        $qq->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    });
                })
                ->when($parentId === 'root', fn ($q) => $q->whereNull('parent_id'))
                ->when($parentId !== null && $parentId !== '' && $parentId !== 'root', fn ($q) => $q->where('parent_id', $parentId))
                ->when($hasProducts === '1', fn ($q) => $q->has('products'))
                ->when($hasProducts === '0', fn ($q) => $q->doesntHave('products'))
                ->orderBy('sort_order');

            return Inertia::render('Admin/Categories/Index', [
                'viewMode' => 'flat',
                'categories' => $query->paginate(25)->withQueryString(),
                'tree' => [],
                'filters' => [
                    'search' => $search,
                    'parent_id' => $parentId,
                    'has_products' => $hasProducts,
                ],
                'stats' => $stats,
                'rootParents' => $rootParents,
            ]);
        }

        $treeQuery = Category::query()
            ->with([
                'children' => fn ($q) => $q->withCount('products')->orderBy('sort_order'),
            ])
            ->withCount('products')
            ->whereNull('parent_id')
            ->when($parentId !== null && $parentId !== '' && $parentId !== 'root', fn ($q) => $q->where('id', $parentId))
            ->when($hasProducts === '1', fn ($q) => $q->where(function ($qq) {
                $qq->has('products')->orWhereHas('children', fn ($c) => $c->has('products'));
            }))
            ->when($hasProducts === '0', fn ($q) => $q->whereDoesntHave('products')->whereDoesntHave('children', fn ($c) => $c->has('products')))
            ->orderBy('sort_order');

        $tree = $treeQuery->get()->map(fn (Category $parent) => $this->serializeTreeParent($parent))->values()->all();

        return Inertia::render('Admin/Categories/Index', [
            'viewMode' => 'tree',
            'categories' => null,
            'tree' => $tree,
            'filters' => [
                'search' => $search,
                'parent_id' => $parentId,
                'has_products' => $hasProducts,
            ],
            'stats' => $stats,
            'rootParents' => $rootParents,
        ]);
    }

    public function create(): Response
    {
        $presetParentId = request()->filled('parent_id') ? (int) request('parent_id') : null;

        return Inertia::render('Admin/Categories/Create', [
            'rootParents' => $this->rootParentOptions(),
            'presetParentId' => $presetParentId,
            'defaultKind' => $presetParentId ? 'sub' : (request('kind') === 'sub' ? 'sub' : 'parent'),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category created');
    }

    public function edit(Category $category): Response
    {
        $category->loadCount(['products', 'children']);

        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
            'rootParents' => $this->rootParentOptions($category->id),
            'hasChildren' => $category->children_count > 0,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category updated');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return redirect()->route('admin.categories.index')->withErrors(['category' => 'Cannot delete a category that still has products']);
        }

        if ($category->children()->exists()) {
            return redirect()->route('admin.categories.index')->withErrors(['category' => 'Cannot delete a category that has child categories']);
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function rootParentOptions(?int $exceptId = null): array
    {
        return Category::query()
            ->whereNull('parent_id')
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTreeParent(Category $parent): array
    {
        return [
            'id' => $parent->id,
            'name' => $parent->name,
            'slug' => $parent->slug,
            'sort_order' => $parent->sort_order,
            'is_active' => (bool) $parent->is_active,
            'og_image_url' => $parent->og_image_url,
            'products_count' => $parent->products_count,
            'children' => $parent->children->map(fn (Category $child) => [
                'id' => $child->id,
                'parent_id' => $child->parent_id,
                'name' => $child->name,
                'slug' => $child->slug,
                'sort_order' => $child->sort_order,
                'is_active' => (bool) $child->is_active,
                'og_image_url' => $child->og_image_url,
                'products_count' => $child->products_count,
            ])->values()->all(),
        ];
    }
}
