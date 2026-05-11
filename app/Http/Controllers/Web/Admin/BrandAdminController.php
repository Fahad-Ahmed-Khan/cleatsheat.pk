<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BrandAdminController extends Controller
{
    public function index(): Response
    {
        $search = trim((string) request('search', ''));

        $brands = Brand::query()
            ->withCount('products')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $brands->through(function (Brand $b) {
            $b->logo_url = $b->logo_path ? Storage::url($b->logo_path) : null;
            return $b;
        });

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $brands,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Brands/Create');
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('brands', 'public');
        }

        Brand::query()->create($data);

        return redirect()->route('admin.brands.index')->with('status', 'Brand created');
    }

    public function edit(Brand $brand): Response
    {
        $logoUrl = $brand->logo_path ? Storage::url($brand->logo_path) : null;

        return Inertia::render('Admin/Brands/Edit', [
            'brand' => array_merge($brand->toArray(), [
                'logo_url' => $logoUrl,
            ]),
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('brands', 'public');
        }

        $brand->update($data);

        return redirect()->route('admin.brands.index')->with('status', 'Brand updated');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return redirect()->route('admin.brands.index')->withErrors(['brand' => 'Cannot delete a brand that still has products']);
        }

        $brand->delete();

        return redirect()->route('admin.brands.index')->with('status', 'Brand deleted');
    }
}
