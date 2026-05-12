<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SizeChart;
use App\Support\Api\ApiResponder;
use Illuminate\Http\JsonResponse;

class SizeChartController extends Controller
{
    public function show(SizeChart $sizeChart): JsonResponse
    {
        $sizeChart->load('rows');

        return ApiResponder::ok([
            'id' => $sizeChart->id,
            'name' => $sizeChart->name,
            'rows' => $sizeChart->rows->map(fn ($r) => [
                'label' => $r->label,
                'uk_size' => $r->uk_size,
                'eu_size' => $r->eu_size,
                'pk_size' => $r->pk_size,
                'foot_cm' => $r->foot_cm !== null ? (float) $r->foot_cm : null,
                // Optional conversion columns (if stored in measurements JSON).
                'us_size' => is_array($r->measurements) ? ($r->measurements['us'] ?? $r->measurements['us_size'] ?? null) : null,
                'aus_size' => is_array($r->measurements) ? ($r->measurements['aus'] ?? $r->measurements['aus_size'] ?? null) : null,
                'jpn_size' => is_array($r->measurements) ? ($r->measurements['jpn'] ?? $r->measurements['jpn_size'] ?? null) : null,
                'cm' => $r->foot_cm !== null
                    ? (float) $r->foot_cm
                    : (is_array($r->measurements) ? ($r->measurements['cm'] ?? null) : null),
                'measurements' => $r->measurements,
            ])->values()->all(),
        ]);
    }
}
