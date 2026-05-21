<?php

namespace App\Http\Controllers\Web\Store\Account;

use App\Domain\Wishlist\WishlistService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Store\Account\Concerns\BuildsAccountSeo;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    use BuildsAccountSeo;

    public function __construct(
        private readonly WishlistService $wishlist,
    ) {}

    public function index(Request $request): Response
    {
        $perPage = min((int) $request->query('per_page', 12), 48);
        $paginator = $this->wishlist->paginateProductsForUser($request->user(), $perPage);

        return Inertia::render('Store/Account/Wishlist', [
            'seo' => $this->accountSeo('Wishlist', '/account/wishlist'),
            'products' => ProductResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->wishlist->add($request->user(), $product->id);

        return back();
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->wishlist->remove($request->user(), $product->id);

        return back();
    }

    public function merge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'min:1'],
        ]);

        $this->wishlist->mergeFromProductIds($request->user(), $validated['product_ids']);

        return back();
    }
}
