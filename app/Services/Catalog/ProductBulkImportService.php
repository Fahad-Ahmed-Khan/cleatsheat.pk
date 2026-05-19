<?php

namespace App\Services\Catalog;

use App\Enums\FitGuidance;
use App\Enums\Gender;
use App\Enums\ShoeType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

final class ProductBulkImportService
{
    public function __construct(
        private readonly ProductManagementService $products,
    ) {}

    /**
     * @return array{created: int, updated: int, errors: list<array{line: int, message: string}>}
     */
    public function importFromSpreadsheet(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return ['created' => 0, 'updated' => 0, 'errors' => [['line' => 0, 'message' => 'Could not read uploaded file.']]];
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['csv', 'txt'], true)) {
            $csvReader = new Csv;
            $csvReader->setInputEncoding('UTF-8');
            $csvReader->setDelimiter(',');
            $csvReader->setEnclosure('"');
            $spreadsheet = $csvReader->load($path);
        } else {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        }
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);
        if ($matrix === [] || $matrix === [[]]) {
            return ['created' => 0, 'updated' => 0, 'errors' => [['line' => 1, 'message' => 'Sheet is empty.']]];
        }

        $rawHeader = array_shift($matrix);
        if (! is_array($rawHeader)) {
            return ['created' => 0, 'updated' => 0, 'errors' => [['line' => 1, 'message' => 'Missing header row.']]];
        }

        if (isset($rawHeader[0])) {
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]);
        }

        $headerMap = $this->normalizeHeaderRow($rawHeader);
        $maxColIndex = $headerMap === [] ? 0 : max(array_keys($headerMap));

        $rows = [];
        $line = 2;
        foreach ($matrix as $row) {
            if (! is_array($row)) {
                $line++;

                continue;
            }
            $row = array_values($row);
            if (count($row) < $maxColIndex + 1) {
                $row = array_pad($row, $maxColIndex + 1, null);
            }
            $assoc = $this->rowToAssoc($headerMap, $row);
            if ($this->isBlankRow($assoc)) {
                $line++;

                continue;
            }
            $rows[] = ['line' => $line, 'data' => $assoc];
            $line++;
        }

        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'errors' => [['line' => 0, 'message' => 'No data rows found.']]];
        }

        $groups = $this->groupRowsBySlug($rows);

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($groups as $slug => $groupRows) {
            $firstLine = $groupRows[0]['line'];
            try {
                [$payload, $existing] = $this->buildPayloadFromGroup($slug, $groupRows);
            } catch (\InvalidArgumentException $e) {
                $errors[] = ['line' => $firstLine, 'message' => $e->getMessage()];

                continue;
            }

            $validator = Validator::make($payload, $this->rulesForPayload($existing));
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $firstLine,
                    'message' => 'Validation: '.$validator->errors()->first(),
                ];

                continue;
            }

            $clean = $validator->validated();

            try {
                if ($existing !== null) {
                    $this->products->update($existing, $clean);
                    $updated++;
                } else {
                    $this->products->store($clean);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['line' => $firstLine, 'message' => $e->getMessage()];
            }
        }

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * @param  list<array{line: int, data: array<string, mixed>}>  $groupRows
     * @return array{0: array<string, mixed>, 1: Product|null}
     */
    private function buildPayloadFromGroup(string $slug, array $groupRows): array
    {
        $first = $groupRows[0]['data'];
        $productId = $this->intish($first['product_id'] ?? null);

        /** @var Product|null $existing */
        $existing = null;
        if ($productId !== null) {
            $existing = Product::query()->find($productId);
            if ($existing === null) {
                throw new \InvalidArgumentException("product_id {$productId} not found.");
            }
            if (isset($first['slug']) && trim((string) $first['slug']) !== '' && $existing->slug !== trim((string) $first['slug'])) {
                throw new \InvalidArgumentException('slug does not match product_id.');
            }
        } else {
            $existing = Product::query()->where('slug', $slug)->first();
        }

        $brandId = $this->resolveBrandId($first);
        $categoryId = $this->resolveCategoryId($first);
        $sizeChartId = $this->nullableInt($first['size_chart_id'] ?? null);

        $variantsMap = [];
        foreach ($groupRows as ['data' => $row]) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                throw new \InvalidArgumentException('Each row must include sku.');
            }
            if (! isset($variantsMap[$sku])) {
                $colorId = $this->resolveColorId($row);
                $variantsMap[$sku] = [
                    'color_id' => $colorId,
                    'sku' => $sku,
                    'price' => $row['price'] ?? null,
                    'compare_at_price' => $this->nullableDecimal($row['compare_at_price'] ?? null),
                    'is_active' => $this->boolish($row['variant_is_active'] ?? true),
                    'bargain_enabled' => $this->boolish($row['bargain_enabled'] ?? false),
                    'bargain_min_price' => $this->nullableDecimal($row['bargain_min_price'] ?? null),
                    'bargain_max_discount_percent' => $this->nullableDecimal($row['bargain_max_discount_percent'] ?? null),
                    'sizes' => [],
                ];
            }

            $sizeLabel = trim((string) ($row['size_label'] ?? ''));
            if ($sizeLabel === '') {
                throw new \InvalidArgumentException("Row for SKU {$sku} is missing size_label.");
            }

            $variantsMap[$sku]['sizes'][] = [
                'size_label' => $sizeLabel,
                'uk_size' => $this->emptyStringToNull($row['uk_size'] ?? null) ?? $sizeLabel,
                'eu_size' => $this->emptyStringToNull($row['eu_size'] ?? null),
                'pk_size' => $this->emptyStringToNull($row['pk_size'] ?? null),
                'stock_qty' => $this->intish($row['stock_qty'] ?? 0) ?? 0,
                'low_stock_threshold' => $this->intish($row['low_stock_threshold'] ?? 0) ?? 0,
            ];
        }

        $variants = array_values($variantsMap);
        if ($variants === []) {
            throw new \InvalidArgumentException('No variants built for product.');
        }

        $productRow = $first;
        $features = $this->parseFeatures($productRow['features'] ?? null);

        $payload = [
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'size_chart_id' => $sizeChartId,
            'name' => trim((string) ($productRow['name'] ?? '')),
            'slug' => $slug,
            'description' => $this->emptyStringToNull($productRow['description'] ?? null),
            'meta_title' => $this->emptyStringToNull($productRow['meta_title'] ?? null),
            'meta_description' => $this->emptyStringToNull($productRow['meta_description'] ?? null),
            'canonical_url' => $this->emptyStringToNull($productRow['canonical_url'] ?? null),
            'video_url' => $this->emptyStringToNull($productRow['video_url'] ?? null),
            'video_poster' => $this->emptyStringToNull($productRow['video_poster'] ?? null),
            'fit_guidance' => $productRow['fit_guidance'] ?? null,
            'gender' => $productRow['gender'] ?? null,
            'shoe_type' => $productRow['shoe_type'] ?? null,
            'fit_notes' => $this->emptyStringToNull($productRow['fit_notes'] ?? null),
            'size_info' => $this->emptyStringToNull($productRow['size_info'] ?? null),
            'features' => $features,
            'is_active' => $this->boolish($productRow['product_is_active'] ?? true),
            'variants' => $variants,
        ];

        $paths = $this->parseImagePaths($productRow['image_paths'] ?? null);
        if ($paths !== null) {
            $payload['images'] = collect($paths)->values()->map(fn (string $p, int $i) => [
                'path' => $p,
                'alt' => null,
                'sort_order' => $i,
            ])->all();
        }

        return [$payload, $existing];
    }

    /**
     * @param  list<array{line: int, data: array<string, mixed>}>  $rows
     * @return array<string, list<array{line: int, data: array<string, mixed>}>>
     */
    private function groupRowsBySlug(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $slug = trim((string) ($row['data']['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $groups[$slug] ??= [];
            $groups[$slug][] = $row;
        }

        return $groups;
    }

    /**
     * @param  array<int|string, mixed>  $rawHeader
     * @return array<int, string> column index => normalized key
     */
    private function normalizeHeaderRow(array $rawHeader): array
    {
        $map = [];
        foreach ($rawHeader as $i => $h) {
            $key = Str::snake(strtolower(trim((string) $h)));
            if ($key === '') {
                $key = '__column_'.$i;
            }
            $map[(int) $i] = $key;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $headerMap
     * @param  array<int|string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowToAssoc(array $headerMap, array $row): array
    {
        $out = [];
        foreach ($headerMap as $col => $key) {
            $out[$key] = $row[$col] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $assoc
     */
    private function isBlankRow(array $assoc): bool
    {
        foreach ($assoc as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $first
     */
    private function resolveBrandId(array $first): int
    {
        $id = $this->intish($first['brand_id'] ?? null);
        if ($id !== null) {
            return $id;
        }
        $slug = trim((string) ($first['brand_slug'] ?? ''));
        if ($slug === '') {
            throw new \InvalidArgumentException('Provide brand_id or brand_slug.');
        }
        $brand = Brand::query()->where('slug', $slug)->first();
        if ($brand === null) {
            throw new \InvalidArgumentException("Unknown brand_slug \"{$slug}\".");
        }

        return (int) $brand->id;
    }

    /**
     * @param  array<string, mixed>  $first
     */
    private function resolveCategoryId(array $first): int
    {
        $id = $this->intish($first['category_id'] ?? null);
        if ($id !== null) {
            return $id;
        }
        $slug = trim((string) ($first['category_slug'] ?? ''));
        if ($slug === '') {
            throw new \InvalidArgumentException('Provide category_id or category_slug.');
        }
        $cat = Category::query()->where('slug', $slug)->first();
        if ($cat === null) {
            throw new \InvalidArgumentException("Unknown category_slug \"{$slug}\".");
        }

        return (int) $cat->id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveColorId(array $row): int
    {
        $id = $this->intish($row['color_id'] ?? null);
        if ($id !== null) {
            return $id;
        }
        $slug = trim((string) ($row['color_slug'] ?? ''));
        if ($slug === '') {
            throw new \InvalidArgumentException('Provide color_id or color_slug on each row.');
        }
        $color = Color::query()->where('slug', $slug)->first();
        if ($color === null) {
            throw new \InvalidArgumentException("Unknown color_slug \"{$slug}\".");
        }

        return (int) $color->id;
    }

    private function parseFeatures(mixed $cell): ?array
    {
        if ($cell === null || trim((string) $cell) === '') {
            return null;
        }
        $s = trim((string) $cell);
        if (str_starts_with($s, '[')) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, static fn ($f) => is_string($f) && $f !== '')) ?: null;
            }
        }
        $parts = array_values(array_filter(array_map('trim', explode('|', $s)), static fn ($f) => $f !== ''));

        return $parts === [] ? null : $parts;
    }

    /**
     * @return list<string>|null null means omit images (preserve on update)
     */
    private function parseImagePaths(mixed $cell): ?array
    {
        if ($cell === null || trim((string) $cell) === '') {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode('|', (string) $cell)), static fn ($p) => $p !== ''));

        return $parts === [] ? null : $parts;
    }

    private function boolish(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function intish(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v)) {
            return (int) $v;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }

        return null;
    }

    private function nullableInt(mixed $v): ?int
    {
        $i = $this->intish($v);

        return $i;
    }

    private function nullableDecimal(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }

    private function emptyStringToNull(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForPayload(?Product $existing): array
    {
        $slugRule = $existing
            ? ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', Rule::unique('products', 'slug')->ignore($existing->id)]
            : ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', 'unique:products,slug'];

        $skuUnique = $existing
            ? ['required', 'string', 'max:64', 'distinct', Rule::unique('product_variants', 'sku')->where(
                fn ($q) => $q->where('product_id', '<>', $existing->id)
            )]
            : ['required', 'string', 'max:64', 'distinct', 'unique:product_variants,sku'];

        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'size_chart_id' => ['nullable', 'integer', 'exists:size_charts,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => $slugRule,
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'canonical_url' => ['nullable', 'string', 'max:512'],
            'video_url' => ['nullable', 'string', 'max:1024'],
            'video_poster' => ['nullable', 'string', 'max:1024'],
            'fit_guidance' => ['required', Rule::enum(FitGuidance::class)],
            'gender' => ['required', Rule::enum(Gender::class)],
            'shoe_type' => ['required', Rule::enum(ShoeType::class)],
            'fit_notes' => ['nullable', 'string'],
            'size_info' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:500'],
            'is_active' => ['boolean'],
            'images' => ['nullable', 'array'],
            'images.*.path' => ['required', 'string', 'max:1024'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.color_id' => ['required', 'integer', 'exists:colors,id'],
            'variants.*.sku' => $skuUnique,
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.bargain_enabled' => ['boolean'],
            'variants.*.bargain_min_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.bargain_max_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'variants.*.sizes' => ['required', 'array', 'min:1'],
            'variants.*.sizes.*.size_label' => ['required', 'string', 'max:32'],
            'variants.*.sizes.*.uk_size' => ['nullable', 'string', 'max:16'],
            'variants.*.sizes.*.eu_size' => ['nullable', 'string', 'max:16'],
            'variants.*.sizes.*.pk_size' => ['nullable', 'string', 'max:16'],
            'variants.*.sizes.*.stock_qty' => ['required', 'integer', 'min:0'],
            'variants.*.sizes.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
