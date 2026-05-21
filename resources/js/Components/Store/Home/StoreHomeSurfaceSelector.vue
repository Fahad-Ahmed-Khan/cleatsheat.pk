<script setup>

import { useSurfaceCategoryTiles } from '@/composables/useSurfaceCategoryDisplay';

import { useReducedMotion } from '@/composables/useReducedMotion';

import { Link } from '@inertiajs/vue3';

import { motion } from 'motion-v';

import { computed, toRef } from 'vue';



const props = defineProps({

    surfaceCategories: { type: Array, default: () => [] },

});



const { tiles } = useSurfaceCategoryTiles(toRef(props, 'surfaceCategories'));

const { prefersReducedMotion } = useReducedMotion();



const popularLabel = computed(() => {

    const first = tiles.value[0];

    return first?.short ? `Most popular: ${first.short}` : 'Most popular: FG';

});



function tilePhotoStyle(imageUrl) {

    if (!imageUrl) return {};

    return {

        backgroundImage: `url("${imageUrl}")`,

        backgroundSize: 'cover',

        backgroundPosition: 'center',

    };

}

</script>



<template>

    <section

        v-if="tiles.length"

        class="store-section border-b border-stadium-outline-soft/20 bg-stadium-white"

    >

        <div class="store-container">

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">

                <div>

                    <p class="text-label text-stadium-olive">{{ popularLabel }}</p>

                    <h2 class="text-display-md text-stadium-ink">Choose your surface</h2>

                    <p class="mt-1 max-w-xl text-base leading-relaxed text-stadium-secondary">

                        Match your boot plate to the pitch you play on — FG, SG, AG or Turf.

                    </p>

                </div>

            </div>



            <div

                class="no-scrollbar mt-6 flex flex-nowrap gap-3 overflow-x-auto pb-2 snap-x lg:mt-8 lg:gap-4 lg:overflow-x-visible lg:snap-none"

            >

                <motion.div

                    v-for="(tile, i) in tiles"

                    :key="tile.id"

                    class="w-[9.5rem] shrink-0 snap-start lg:min-w-0 lg:flex-1 lg:shrink"

                    :initial="prefersReducedMotion ? false : { opacity: 0, y: 12 }"

                    :while-in-view="prefersReducedMotion ? undefined : { opacity: 1, y: 0 }"

                    :viewport="{ once: true }"

                    :transition="{ delay: i * 0.05, duration: 0.35 }"

                >

                    <Link

                        :href="tile.href"

                        class="group relative flex aspect-[4/5] min-h-[12rem] w-full flex-col justify-between overflow-hidden rounded-2xl border-2 border-store-primary/25 bg-stadium-muted p-4 transition active:scale-[0.98] hover:-translate-y-1 hover:border-store-primary hover:shadow-stadium-lg hover:ring-2 hover:ring-store-primary/20 lg:aspect-square lg:min-h-0 lg:rounded-[24px] lg:p-6"

                    >

                        <div

                            v-if="tile.imageUrl"

                            class="pointer-events-none absolute inset-0 bg-cover bg-center"

                            :style="tilePhotoStyle(tile.imageUrl)"

                            aria-hidden="true"

                        />

                        <div

                            v-if="tile.imageUrl"

                            class="pointer-events-none absolute inset-0 bg-gradient-to-t from-stadium-inverse/95 from-0% via-stadium-inverse/70 via-[38%] to-transparent to-[62%]"

                            aria-hidden="true"

                        />



                        <span

                            class="relative z-[1] flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-stadium-inverse font-display text-xs font-bold text-store-primary shadow-stadium-sm lg:h-12 lg:w-12 lg:text-sm"

                        >

                            {{ tile.short }}

                        </span>

                        <div class="relative z-[1]">

                            <h3

                                class="font-display text-sm font-bold leading-tight tracking-tight drop-shadow-sm lg:text-2xl"

                                :class="tile.imageUrl ? 'text-white' : 'text-stadium-ink'"

                            >

                                {{ tile.displayName }}

                            </h3>

                            <p

                                v-if="tile.subtitle"

                                class="mt-1 line-clamp-2 text-[10px] leading-snug drop-shadow-sm transition group-hover:text-store-secondary lg:text-base"

                                :class="tile.imageUrl ? 'text-white/90' : 'text-stadium-secondary'"

                            >

                                {{ tile.subtitle }}

                            </p>

                        </div>

                    </Link>

                </motion.div>

            </div>

        </div>

    </section>

</template>

