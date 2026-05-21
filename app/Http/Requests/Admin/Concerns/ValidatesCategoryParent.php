<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Category;
use Closure;

trait ValidatesCategoryParent
{
    /**
     * Subcategories may only attach to a top-level (root) parent.
     */
    protected function rootParentIdRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $parent = Category::query()->find((int) $value);
            if ($parent === null) {
                return;
            }

            if ($parent->parent_id !== null) {
                $fail('Choose a top-level parent category. Subcategories cannot be nested further.');
            }
        };
    }
}
