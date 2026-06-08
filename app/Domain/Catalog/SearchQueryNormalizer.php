<?php

namespace App\Domain\Catalog;

final class SearchQueryNormalizer
{
    /**
     * Normalize and sanitize a storefront search query.
     */
    public static function normalize(string $query, ?int $maxLength = null): string
    {
        $max = $maxLength ?? (int) config('store.search_query_max_length', 120);

        $query = trim($query);
        $query = preg_replace('/[+\-><()~*"@]+/u', ' ', $query) ?? $query;
        $query = preg_replace('/\s+/u', ' ', $query) ?? $query;
        $query = trim($query);

        if (mb_strlen($query, 'UTF-8') > $max) {
            $query = mb_substr($query, 0, $max, 'UTF-8');
            $query = trim($query);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $normalizedQuery): array
    {
        if ($normalizedQuery === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(is_array($parts) ? $parts : []));
    }

    /**
     * Build a BOOLEAN MODE FULLTEXT query from tokens (each term prefixed with + and suffixed with *).
     */
    public static function booleanQuery(string $normalizedQuery): string
    {
        $tokens = self::tokens($normalizedQuery);
        if ($tokens === []) {
            return '';
        }

        $parts = [];
        foreach ($tokens as $token) {
            $safe = preg_replace('/[^\pL\pN\-]+/u', '', $token) ?? '';
            if ($safe !== '') {
                $parts[] = '+'.$safe.'*';
            }
        }

        return implode(' ', $parts);
    }

    public static function slugCandidate(string $normalizedQuery): string
    {
        return strtolower(str_replace(' ', '-', $normalizedQuery));
    }
}
