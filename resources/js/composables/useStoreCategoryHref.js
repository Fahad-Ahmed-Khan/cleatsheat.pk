import { computed } from 'vue';

export function useStoreCategoryHref(categoriesRef) {
    const flatCategories = computed(() => {
        const roots = categoriesRef.value ?? [];
        return [...roots, ...roots.flatMap((x) => x.children || [])];
    });

    function categoryHref(fragments) {
        const fr = fragments.map((f) => f.toLowerCase());
        for (const cat of flatCategories.value) {
            if (!cat?.slug) continue;
            const slug = String(cat.slug).toLowerCase();
            const name = String(cat.name || '').toLowerCase();
            if (fr.some((f) => slug.includes(f) || name.includes(f))) {
                return route('store.category', cat.slug);
            }
        }
        return route('store.shop');
    }

    return { flatCategories, categoryHref };
}

export const SURFACE_TILES = [
    { title: 'Firm Ground', subtitle: 'Natural dry grass', short: 'FG', fragments: ['fg', 'firm'] },
    { title: 'Soft Ground', subtitle: 'Wet, muddy grass', short: 'SG', fragments: ['sg', 'soft'] },
    { title: 'Artificial Grass', subtitle: 'Modern 3G / 4G turf', short: 'AG', fragments: ['ag', 'artificial'] },
    { title: 'Turf / Indoor', subtitle: 'Astro and courts', short: 'TF', fragments: ['turf', 'indoor', 'astro', 'court', 'tf'] },
];
