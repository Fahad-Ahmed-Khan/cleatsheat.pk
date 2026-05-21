import { computed, unref } from 'vue';
import { SURFACE_TILES, useStoreCategoryHref } from '@/composables/useStoreCategoryHref';

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

const HOME_SURFACE_ORDER = ['FG', 'AG', 'IC'];

const HOME_PK_SUBTITLES = {
    FG: 'Firm Ground · Natural grass',
    AG: 'Artificial turf · AstroTurf',
    IC: 'Indoor · Futsal courts',
};

const HOME_SURFACE_FALLBACKS = [
    { short: 'FG', displayName: 'Firm Ground', fragments: ['fg', 'firm'] },
    { short: 'AG', displayName: 'Artificial Grass', fragments: ['ag', 'artificial'] },
    { short: 'IC', displayName: 'Indoor / Futsal', fragments: ['indoor', 'futsal', 'ic', 'tf', 'turf'] },
];

/**
 * @param {string} short
 * @param {string} slug
 */
function homeSurfaceKey(short, slug) {
    const s = String(short ?? '').toUpperCase();
    const sl = String(slug ?? '').toLowerCase();
    if (s === 'FG' || sl.includes('fg') || sl.includes('firm')) return 'FG';
    if (s === 'AG' || sl.includes('ag') || sl.includes('artificial')) return 'AG';
    if (s === 'IC' || s === 'TF' || s === 'INDOOR' || sl.includes('indoor') || sl.includes('futsal') || sl.includes('-ic')) {
        return 'IC';
    }
    return null;
}

/**
 * @param {Record<string, unknown>} category
 * @returns {{ id: number|string, name: string, displayName: string, short: string, subtitle: string, imageUrl: string|null, slug: string, href: string }}
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

/**
 * Homepage surface row: FG, AG, Indoor/Futsal only (Pakistan-focused).
 *
 * @param {import('vue').MaybeRefOrGetter<Array<Record<string, unknown>>>} surfaceCategoriesRef
 * @param {import('vue').MaybeRefOrGetter<Array<Record<string, unknown>>>} [allCategoriesRef]
 */
export function useHomeSurfaceTiles(surfaceCategoriesRef, allCategoriesRef = null) {
    const categoriesRef = allCategoriesRef ?? surfaceCategoriesRef;
    const { categoryHref } = useStoreCategoryHref(categoriesRef);

    const tiles = computed(() => {
        const list = unref(surfaceCategoriesRef) ?? [];
        const byKey = new Map();

        for (const cat of list) {
            if (!cat?.slug || cat.is_active === false) continue;
            const tile = toSurfaceTile(cat);
            const key = homeSurfaceKey(tile.short, tile.slug);
            if (!key || !HOME_SURFACE_ORDER.includes(key)) continue;
            const pkSubtitle = HOME_PK_SUBTITLES[key];
            byKey.set(key, {
                ...tile,
                short: key,
                subtitle: tile.subtitle || pkSubtitle,
            });
        }

        for (const fb of HOME_SURFACE_FALLBACKS) {
            if (byKey.has(fb.short)) continue;
            const match = SURFACE_TILES.find((t) => t.short === fb.short || (fb.short === 'IC' && t.short === 'TF'));
            const fragments = match?.fragments ?? fb.fragments;
            byKey.set(fb.short, {
                id: `home-${fb.short}`,
                name: fb.displayName,
                displayName: fb.displayName,
                short: fb.short,
                subtitle: HOME_PK_SUBTITLES[fb.short],
                imageUrl: null,
                slug: '',
                href: categoryHref(fragments),
            });
        }

        return HOME_SURFACE_ORDER.map((key) => byKey.get(key)).filter(Boolean);
    });

    return { tiles };
}

/** @param {string} name */
export function categoryShortFromName(name) {
    const words = String(name ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }
    const s = String(name ?? '').trim();
    return s.slice(0, 2).toUpperCase() || '—';
}

/**
 * @param {Record<string, unknown>} category
 * @returns {{ id: number|string, name: string, displayName: string, short: string, subtitle: string, imageUrl: string|null, slug: string, href: string }}
 */
export function toCategoryTile(category) {
    const slug = String(category.slug ?? '');
    const name = String(category.name ?? '');

    return {
        id: category.id,
        name,
        displayName: name,
        short: categoryShortFromName(name),
        subtitle: String(category.meta_description ?? '').trim(),
        imageUrl: category.og_image_url ? String(category.og_image_url) : null,
        slug,
        href: slug ? route('store.category', slug) : route('store.shop'),
    };
}

export const HOME_CATEGORY_PREVIEW_LIMIT = 4;

/**
 * Homepage category row: active root categories from admin, ordered by sort_order.
 *
 * @param {import('vue').MaybeRefOrGetter<Array<Record<string, unknown>>>} categoriesRef
 * @param {number} [limit]
 */
export function useHomeCategoryTiles(categoriesRef, limit = HOME_CATEGORY_PREVIEW_LIMIT) {
    const allTiles = computed(() => {
        const list = unref(categoriesRef) ?? [];
        return list
            .filter((c) => c?.slug && c.is_active !== false)
            .slice()
            .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))
            .map((c) => toCategoryTile(c));
    });

    const tiles = computed(() => allTiles.value.slice(0, limit));
    const hasMore = computed(() => allTiles.value.length > limit);
    const totalCount = computed(() => allTiles.value.length);

    return { tiles, hasMore, totalCount };
}
