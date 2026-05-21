import {
    isCategoryActive,
    normalizeCategoryChildren,
    normalizeCategoryList,
} from '@/composables/useCategoryBrowser';
import { computed, unref } from 'vue';

/**
 * Root categories with active children for storefront nav.
 *
 * @param {import('vue').MaybeRefOrGetter<Array<Record<string, unknown>>>} categoriesRef
 */
export function useNavCategories(categoriesRef) {
    const parents = computed(() => {
        const list = normalizeCategoryList(unref(categoriesRef));

        return list
            .filter((c) => c?.slug && isCategoryActive(c.is_active))
            .slice()
            .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))
            .map((parent) => ({
                id: parent.id,
                name: String(parent.name ?? ''),
                slug: String(parent.slug ?? ''),
                children: normalizeCategoryChildren(parent.children)
                    .filter((ch) => ch?.slug && isCategoryActive(ch.is_active))
                    .slice()
                    .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))
                    .map((ch) => ({
                        id: ch.id,
                        name: String(ch.name ?? ''),
                        slug: String(ch.slug ?? ''),
                    })),
            }));
    });

    return { parents };
}
