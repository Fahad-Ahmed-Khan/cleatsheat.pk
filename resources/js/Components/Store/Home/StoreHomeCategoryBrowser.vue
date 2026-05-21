<script setup>
import { useCategoryBrowser } from '@/composables/useCategoryBrowser';
import { Link } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const { parents, hasContent } = useCategoryBrowser(toRef(props, 'categories'));

const categoryCount = computed(() => props.categories?.length ?? 0);

function parentInitials(name) {
    const words = String(name ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }
    return String(name ?? '?').slice(0, 2).toUpperCase();
}
</script>

<template>
    <section
        id="shop-by-category"
        class="border-b border-stadium-outline-soft/30 bg-stadium-white py-10 dark:border-stadium-outline-soft/20 dark:bg-stadium-container md:py-14"
        aria-labelledby="shop-by-category-heading"
    >
        <div class="store-container">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-label text-stadium-olive dark:text-stadium-lime">Shop by category</p>
                    <h2 id="shop-by-category-heading" class="text-display-md text-stadium-ink">
                        Browse our catalog
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-stadium-secondary md:text-base">
                        Each department lists its subcategories — pick a surface or range and start shopping.
                    </p>
                </div>
                <Link
                    :href="route('store.shop')"
                    class="inline-flex shrink-0 items-center gap-1 font-display text-sm font-bold uppercase tracking-wide text-stadium-olive hover:underline dark:text-stadium-lime"
                >
                    View all products
                    <span aria-hidden="true">→</span>
                </Link>
            </div>

            <div
                v-if="!hasContent"
                class="mt-8 rounded-2xl border border-dashed border-stadium-outline-soft/60 bg-stadium-muted/40 px-6 py-10 text-center dark:bg-stadium-muted/30"
            >
                <p class="font-display text-lg font-bold text-stadium-ink">Categories coming soon</p>
                <p class="mt-2 text-sm text-stadium-secondary">
                    <template v-if="categoryCount > 0">
                        Found {{ categoryCount }} categor{{ categoryCount === 1 ? 'y' : 'ies' }}, but none are active
                        top-level parents. In Admin → Categories, set <strong>Parent</strong> to “None” and check
                        <strong>Active</strong>.
                    </template>
                    <template v-else>
                        Add active <strong>parent categories</strong> in Admin → Categories, then add subcategories under
                        each parent.
                    </template>
                </p>
                <Link
                    :href="route('store.shop')"
                    class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl bg-store-primary px-6 py-2.5 text-sm font-bold text-stadium-lime-ink hover:opacity-95"
                >
                    Shop all products
                    <span aria-hidden="true">→</span>
                </Link>
            </div>

            <div v-else class="mt-8 space-y-5 sm:mt-10 sm:space-y-6">
                <article
                    v-for="parent in parents"
                    :key="parent.id"
                    class="relative min-h-[12rem] overflow-hidden rounded-2xl border border-stadium-outline-soft/30 bg-stadium-inverse shadow-stadium md:min-h-[13rem] md:rounded-3xl"
                    :aria-labelledby="`dept-${parent.id}`"
                >
                    <img
                        v-if="parent.imageUrl"
                        :src="parent.imageUrl"
                        alt=""
                        class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-50"
                        loading="lazy"
                        decoding="async"
                    />
                    <div
                        class="pointer-events-none absolute inset-0 bg-gradient-to-r from-stadium-inverse via-stadium-inverse/92 to-stadium-inverse/70 lg:to-stadium-inverse/55"
                        aria-hidden="true"
                    />
                    <div
                        v-if="!parent.imageUrl"
                        class="pointer-events-none absolute inset-0 bg-gradient-to-br from-stadium-inverse via-stadium-olive/60 to-stadium-lime/40"
                        aria-hidden="true"
                    />

                    <div class="relative z-[1] flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-stretch lg:gap-5 lg:p-6">
                        <div class="flex flex-col justify-center lg:w-[min(100%,22rem)] lg:shrink-0">
                            <p class="text-label text-stadium-lime">Department</p>
                            <h3
                                :id="`dept-${parent.id}`"
                                class="mt-1 font-display text-xl font-bold tracking-tight text-white sm:text-2xl"
                            >
                                {{ parent.name }}
                            </h3>
                            <p
                                v-if="parent.description"
                                class="mt-2 line-clamp-3 text-sm leading-relaxed text-white/85"
                            >
                                {{ parent.description }}
                            </p>
                            <Link
                                :href="parent.href"
                                class="mt-4 inline-flex w-fit min-h-10 items-center gap-2 rounded-xl bg-stadium-lime px-4 py-2.5 text-label text-stadium-lime-ink shadow-md transition hover:bg-white hover:text-stadium-ink"
                            >
                                Shop all {{ parent.name }}
                                <span aria-hidden="true">→</span>
                            </Link>
                        </div>

                        <div class="min-w-0 flex-1 lg:flex lg:items-center">
                            <ul
                                v-if="parent.children && parent.children.length"
                                class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-2.5 lg:grid-cols-3 xl:grid-cols-5 xl:gap-2"
                                :aria-label="`Subcategories in ${parent.name}`"
                            >
                                <li
                                    v-for="child in parent.children"
                                    :key="child.id"
                                >
                                    <Link
                                        :href="child.href"
                                        class="group flex h-full flex-col overflow-hidden rounded-lg border border-white/20 bg-white/95 shadow-sm backdrop-blur-sm transition hover:-translate-y-0.5 hover:border-stadium-lime/60 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stadium-lime dark:bg-stadium-white/95"
                                    >
                                        <div class="relative aspect-[5/3] overflow-hidden bg-stadium-muted">
                                            <img
                                                v-if="child.imageUrl"
                                                :src="child.imageUrl"
                                                :alt="child.name"
                                                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.05]"
                                                loading="lazy"
                                                decoding="async"
                                            />
                                            <div
                                                v-else
                                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-stadium-muted to-stadium-olive/25"
                                            >
                                                <span
                                                    class="font-display text-sm font-bold text-stadium-olive"
                                                    aria-hidden="true"
                                                >
                                                    {{ parentInitials(child.name) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="px-2 py-1.5 sm:px-2.5 sm:py-2">
                                            <span
                                                class="line-clamp-2 font-display text-[11px] font-bold leading-tight text-stadium-ink group-hover:text-stadium-olive sm:text-xs"
                                            >
                                                {{ child.name }}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            </ul>
                            <p
                                v-else
                                class="rounded-lg border border-dashed border-white/25 bg-white/10 px-4 py-6 text-center text-sm text-white/80"
                            >
                                No subcategories yet —
                                <Link :href="parent.href" class="font-semibold text-stadium-lime hover:underline">
                                    browse {{ parent.name }}
                                </Link>
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
