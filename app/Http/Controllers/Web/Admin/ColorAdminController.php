<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreColorRequest;
use App\Http\Requests\Admin\UpdateColorRequest;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ColorAdminController extends Controller
{
    public function index(): Response
    {
        $colors = Color::query()->latest()->paginate(40);

        return Inertia::render('Admin/Colors/Index', [
            'colors' => $colors,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Colors/Create');
    }

    public function store(StoreColorRequest $request): RedirectResponse
    {
        Color::query()->create($request->validated());

        return redirect()->route('admin.colors.index')->with('status', 'Color created');
    }

    public function edit(Color $color): Response
    {
        return Inertia::render('Admin/Colors/Edit', [
            'color' => $color,
        ]);
    }

    public function update(UpdateColorRequest $request, Color $color): RedirectResponse
    {
        $color->update($request->validated());

        return redirect()->route('admin.colors.index')->with('status', 'Color updated');
    }

    public function destroy(Color $color): RedirectResponse
    {
        if ($color->variants()->exists()) {
            return redirect()->route('admin.colors.index')->withErrors(['color' => 'Cannot delete a color used by variants']);
        }

        $color->delete();

        return redirect()->route('admin.colors.index')->with('status', 'Color deleted');
    }
}
