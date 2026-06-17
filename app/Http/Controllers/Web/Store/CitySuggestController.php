<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Shipping\Trax\TraxCityResolver;
use App\Domain\Shipping\Trax\TraxTokenResolver;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CitySuggestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $qNorm = mb_strtolower($q, 'UTF-8');

        $courier = Courier::query()->where('code', 'trax')->first();
        if ($courier === null) {
            return response()->json(['cities' => []]);
        }

        $account = CourierAccount::query()
            ->where('courier_id', $courier->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
        if ($account === null) {
            return response()->json(['cities' => []]);
        }

        $token = TraxTokenResolver::forCourierAccount($account);
        if ($token === '') {
            return response()->json(['cities' => []]);
        }

        $all = TraxCityResolver::cities($account, $token);
        if (count($all) === 0) {
            return response()->json(['cities' => []]);
        }

        $names = array_values(array_unique(array_map(fn ($r) => (string) ($r['name'] ?? ''), $all)));
        $names = array_values(array_filter($names, fn ($s) => trim($s) !== ''));

        if ($qNorm !== '') {
            $names = array_values(array_filter($names, function (string $name) use ($qNorm): bool {
                return str_contains(mb_strtolower($name, 'UTF-8'), $qNorm);
            }));
        }

        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        $names = array_slice($names, 0, 25);

        return response()->json(['cities' => $names]);
    }
}

