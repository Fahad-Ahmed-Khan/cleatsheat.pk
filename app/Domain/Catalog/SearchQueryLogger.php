<?php

namespace App\Domain\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SearchQueryLogger
{
    public function log(string $query, int $resultsCount, ?string $ip = null): void
    {
        if (! config('store.search_log_queries', false)) {
            return;
        }

        if (! Schema::hasTable('search_query_logs')) {
            return;
        }

        $hash = $ip !== null ? hash('sha256', $ip) : null;

        DB::table('search_query_logs')->insert([
            'query' => mb_substr($query, 0, 120, 'UTF-8'),
            'results_count' => $resultsCount,
            'ip_hash' => $hash,
            'created_at' => now(),
        ]);
    }
}
