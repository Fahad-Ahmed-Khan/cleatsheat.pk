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
        $parentId = request('parent_id'); // '' | null | numeric
        $hasProducts = request('has_products'); // 1 | 0 | null

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

        $categories = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'parent_id' => $parentId,
                'has_products' => $hasProducts,
            ],
            'stats' => [
                'total' => Category::query()->count(),
                'root' => Category::query()->whereNull('parent_id')->count(),
                'with_products' => Category::query()->has('products')->count(),
            ],
            'parents' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Create', [
            'parents' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category created');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
            'parents' => Category::query()->where('id', '!=', $category->id)->orderBy('name')->get(['id', 'name']),
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
}
