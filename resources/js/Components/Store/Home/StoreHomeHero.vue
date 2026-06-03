<script setup>
import { useReducedMotion } from '@/composables/useReducedMotion';
import { Link, usePage } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { computed } from 'vue';

const props = defineProps({
    hero: { type: Object, required: true },
    promoBanner: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
});

const page = usePage();
const storefront = computed(() => page.props.storefront ?? {});
const { prefersReducedMotion } = useReducedMotion();

const firstCategory = computed(() => {
    const list = (props.categories ?? []).filter((c) => c?.slug && c.is_active !== false);
    return list.slice().sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))[0] ?? null;
});

const primaryHref = computed(() => {
    if (props.hero.cta_url) return props.hero.cta_url;
    if (firstCategory.value?.slug) {
        return route('store.category', firstCategory.value.slug);
    }
    return route('store.shop');
});

const primaryLabel = computed(() => {
    if (props.hero.cta_label) return props.hero.cta_label;
    if (firstCategory.value?.name) {
        return `Shop ${firstCategory.value.name}`;
    }
    return 'Shop all boots';
});

const heroImageUrl = computed(() => props.hero?.image_url || null);

function stagger(i) {
    if (prefersReducedMotion.value) return {};
    return {
        initial: { opacity: 1, y: 10 },
        animate: { opacity: 1, y: 0 },
        transition: { delay: i * 0.06, duration: 0.4 },
    };
}
</script>

<template>
    <section class="relative overflow-hidden bg-stadium-inverse store-pitch-pattern">
        <div class="absolute inset-0">
            <img
                v-if="heroImageUrl"
                :src="heroImageUrl"
                alt=""
                aria-hidden="true"
                width="1920"
                height="1080"
                class="h-full w-full object-cover object-center"
                fetchpriority="high"
                decoding="async"
            >
            <div
                v-else
                class="absolute inset-0 bg-[linear-gradient(135deg,#0a0b0b_0%,#1a1c1c_45%,#414c00_100%)]"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-stadium-inverse via-stadium-inverse/70 to-stadium-inverse/30" />
            <div
                v-if="heroImageUrl"
                class="pointer-events-none absolute inset-0 bg-black/25 dark:bg-transparent"
                aria-hidden="true"
            />
        </div>

        <div class="store-container relative z-10 py-14 md:py-24 lg:py-28">
            <div class="flex max-w-3xl flex-col">
                <motion.span
                    v-if="hero.badge"
                    v-bind="stagger(0)"
                    class="mb-4 inline-flex w-fit rounded-full bg-stadium-lime px-4 py-1.5 text-label text-stadium-lime-ink shadow-md"
                >
                    {{ hero.badge }}
                </motion.span>

                <motion.h1
                    v-bind="stagger(1)"
                    class="text-display-xl text-stadium-inverse-text drop-shadow-[0_2px_16px_rgba(0,0,0,0.55)]"
                >
                    {{ hero.title }}
                </motion.h1>

                <motion.p
                    v-bind="stagger(2)"
                    class="mt-4 max-w-xl text-body-lg text-stadium-inverse-text/85 drop-shadow-[0_1px_12px_rgba(0,0,0,0.45)]"
                >
                    {{ hero.subtitle }}
                </motion.p>

                <motion.div
                    v-bind="stagger(3)"
                    class="mt-8 flex flex-wrap gap-3"
                >
                    <Link
                        :href="primaryHref"
                        class="inline-flex min-h-12 items-center gap-2 rounded-2xl bg-stadium-lime px-8 py-4 text-label text-stadium-lime-ink shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stadium-lime active:scale-[0.98]"
                    >
                        {{ primaryLabel }}
                        <span aria-hidden="true">→</span>
                    </Link>
                    <Link
                        href="#shop-by-category"
                        class="inline-flex min-h-12 items-center justify-center rounded-2xl border-2 border-stadium-inverse-text/30 px-8 py-4 text-label text-stadium-inverse-text transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stadium-lime"
                    >
                        Browse categories
                    </Link>
                </motion.div>

                <motion.div
                    v-bind="stagger(4)"
                    class="mt-6 flex flex-wrap items-center gap-4 text-sm text-stadium-inverse-text/75"
                >
                    <span class="inline-flex items-center gap-1 font-semibold text-stadium-lime">
                        ★★★★★
                        <span class="text-stadium-inverse-text/80">Trusted by players</span>
                    </span>
                    <span aria-hidden="true" class="hidden h-4 w-px bg-white/20 sm:block" />
                    <span>500+ pairs shipped nationwide</span>
                    <a
                        v-if="storefront.support_whatsapp_url && storefront.support_whatsapp_url !== '#'"
                        :href="storefront.support_whatsapp_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 font-semibold text-stadium-lime hover:underline"
                    >
                        Size help on WhatsApp
                    </a>
                </motion.div>
            </div>
        </div>

        <Link
            v-if="promoBanner?.image_url && promoBanner?.link_url"
            :href="promoBanner.link_url"
            class="store-container relative z-10 block pb-6"
        >
            <img
                :src="promoBanner.image_url"
                :alt="promoBanner.title || 'Promotion'"
                class="w-full rounded-2xl object-cover ring-1 ring-stadium-lime/30"
                loading="lazy"
            />
        </Link>
    </section>
</template>
