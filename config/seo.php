<?php

return [
    'sitemap_chunk_size' => (int) env('SEO_SITEMAP_CHUNK_SIZE', 10_000),
    'sitemap_index_threshold' => (int) env('SEO_SITEMAP_INDEX_THRESHOLD', 45_000),
];
