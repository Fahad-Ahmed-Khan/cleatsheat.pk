import { computed, unref } from 'vue';

/** @param {string} name */
export function surfaceShortFromName(name) {
    const match = String(name ?? '').match(/\(([^)]+)\)/);
    return match ? match[1].trim() : null;
}

/** @param {string} name */
export function surfaceDisplayName(name) {
    const stripped = String(name ?? '')
        .replace(/\s*\([^)]+\)\s*$/, '')
        .trim();
    return stripped || String(name ?? '');
}

/** @param {string} slug */
function surfaceShortFromSlug(slug) {
    const s = String(slug ?? '').toLowerCase();
    const tail = s.split('-').pop() ?? '';
    if (tail.length >= 2 && tail.length <= 4) {
        return tail.toUpperCase();
    }
    return null;
}

/**
 * @param {Record<string, unknown>} category
 * @returns {{ id: number, name: string, displayName: string, short: string, subtitle: string, imageUrl: string|null, slug: string, href: string }}
 */
export function toSurfaceTile(category) {
    const slug = String(category.slug ?? '');
    const name = String(category.name ?? '');
    const short =
        surfaceShortFromName(name) ?? surfaceShortFromSlug(slug) ?? slug.slice(0, 2).toUpperCase();

    return {
        id: category.id,
        name,
        displayName: surfaceDisplayName(name),
        short,
        subtitle: String(category.meta_description ?? '').trim(),
        imageUrl: category.og_image_url ? String(category.og_image_url) : null,
        slug,
        href: slug ? route('store.category', slug) : route('store.shop'),
    };
}

/**
 * @param {import('vue').MaybeRefOrGetter<Array<Record<string, unknown>>>} categoriesRef
 */
export function useSurfaceCategoryTiles(categoriesRef) {
    const tiles = computed(() => {
        const list = unref(categoriesRef) ?? [];
        return list
            .filter((c) => c?.slug && c.is_active !== false)
            .map((c) => toSurfaceTile(c));
    });

    return { tiles };
}
