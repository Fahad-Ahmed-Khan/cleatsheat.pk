<script setup>
import { computed, ref, watch } from 'vue';
import StoreMediaLightbox from '@/Components/Store/StoreMediaLightbox.vue';

const props = defineProps({
    images: { type: Array, default: () => [] },
    productName: { type: String, default: '' },
    videoUrl: { type: String, default: '' },
    videoPoster: { type: String, default: '' },
});

const hasVideo = computed(() => typeof props.videoUrl === 'string' && props.videoUrl.trim() !== '');

const tiles = computed(() => {
    const list = props.images.map((img) => ({
        kind: 'image',
        src: img.path,
        alt: img.alt || props.productName,
    }));
    if (hasVideo.value) {
        list.push({
            kind: 'video',
            src: props.videoUrl,
            poster: props.videoPoster || props.images[0]?.path || '',
            alt: `${props.productName} video`,
        });
    }
    return list;
});

const active = ref(0);
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

watch(
    () => props.images,
    () => {
        active.value = 0;
    },
    { deep: true },
);

const imageTiles = computed(() => tiles.value.filter((t) => t.kind === 'image'));
const mainSrc = computed(() => imageTiles.value[active.value]?.src ?? null);
const mainAlt = computed(() => imageTiles.value[active.value]?.alt || props.productName);
const hasMultiple = computed(() => imageTiles.value.length > 1);

function next() {
    if (!hasMultiple.value) return;
    active.value = (active.value + 1) % imageTiles.value.length;
}

function prev() {
    if (!hasMultiple.value) return;
    active.value = (active.value - 1 + imageTiles.value.length) % imageTiles.value.length;
}

function openLightbox(tileIndex) {
    if (tiles.value.length === 0) return;
    lightboxIndex.value = Math.max(0, Math.min(tileIndex, tiles.value.length - 1));
    lightboxOpen.value = true;
}

function openMain() {
    openLightbox(active.value);
}

function openVideo() {
    const idx = tiles.value.findIndex((t) => t.kind === 'video');
    if (idx >= 0) openLightbox(idx);
}
</script>

<template>
    <div class="flex gap-3 sm:gap-4">
        <!-- Vertical thumbnails (desktop) -->
        <div
            v-if="hasMultiple || hasVideo"
            class="hidden w-20 shrink-0 flex-col gap-3 sm:flex"
        >
            <button
                v-for="(img, i) in imageTiles"
                :key="i"
                type="button"
                class="aspect-square w-full overflow-hidden rounded-xl bg-stone-50 ring-1 transition"
                :class="active === i ? 'ring-2 ring-stone-900' : 'ring-stone-200/80 hover:ring-stone-400'"
                :aria-label="`View image ${i + 1}`"
                @click="active = i"
                @dblclick="openLightbox(i)"
            >
                <img
                    :src="img.src"
                    :alt="img.alt"
                    class="h-full w-full object-contain"
                >
            </button>
            <button
                v-if="hasVideo"
                type="button"
                class="relative aspect-square w-full overflow-hidden rounded-xl bg-stone-900 ring-1 ring-stone-200/80 transition hover:ring-stone-400"
                aria-label="Play product video"
                @click="openVideo"
            >
                <img
                    v-if="videoPoster || imageTiles[0]?.src"
                    :src="videoPoster || imageTiles[0]?.src"
                    :alt="`${productName} video`"
                    class="h-full w-full object-cover opacity-80"
                >
                <span class="absolute inset-0 flex items-center justify-center bg-black/40">
                    <svg viewBox="0 0 24 24" fill="white" class="h-6 w-6 drop-shadow">
                        <path d="M8 5v14l11-7z" />
                    </svg>
                </span>
            </button>
        </div>

        <!-- Main image -->
        <div class="relative flex-1">
            <div
                class="group relative flex aspect-square w-full cursor-zoom-in items-center justify-center overflow-hidden rounded-2xl bg-stone-50 ring-1 ring-stone-200/80 sm:aspect-[4/5]"
                role="button"
                tabindex="0"
                :aria-label="`Open ${mainAlt} preview`"
                @click="openMain"
                @keydown.enter.prevent="openMain"
                @keydown.space.prevent="openMain"
            >
                <img
                    v-if="mainSrc"
                    :src="mainSrc"
                    :alt="mainAlt"
                    class="h-full w-full object-contain transition duration-200 group-hover:scale-[1.02]"
                >
                <div v-else class="text-sm text-stone-400">
                    Image coming soon
                </div>

                <!-- Zoom hint -->
                <span class="pointer-events-none absolute left-3 top-3 hidden items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-medium text-stone-700 ring-1 ring-stone-200/80 sm:flex">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        <line x1="11" y1="8" x2="11" y2="14" />
                        <line x1="8" y1="11" x2="14" y2="11" />
                    </svg>
                    Click to zoom
                </span>

                <!-- Floating play button when video is available -->
                <button
                    v-if="hasVideo"
                    type="button"
                    class="absolute left-3 bottom-3 flex items-center gap-1.5 rounded-full bg-stone-900/90 px-3 py-1.5 text-xs font-semibold text-white shadow ring-1 ring-stone-900/40 backdrop-blur transition hover:bg-stone-900 active:scale-95"
                    aria-label="Play product video"
                    @click.stop="openVideo"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5">
                        <path d="M8 5v14l11-7z" />
                    </svg>
                    Play video
                </button>

                <!-- Prev/Next controls -->
                <div
                    v-if="hasMultiple"
                    class="absolute bottom-3 right-3 flex items-center gap-2"
                >
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-stone-700 shadow ring-1 ring-stone-200 backdrop-blur transition hover:bg-white hover:text-stone-900 active:scale-95"
                        aria-label="Previous image"
                        @click.stop="prev"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-stone-700 shadow ring-1 ring-stone-200 backdrop-blur transition hover:bg-white hover:text-stone-900 active:scale-95"
                        aria-label="Next image"
                        @click.stop="next"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Horizontal thumbnails (mobile) -->
            <div
                v-if="hasMultiple || hasVideo"
                class="mt-3 flex gap-2 overflow-x-auto pb-1 sm:hidden [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                <button
                    v-for="(img, i) in imageTiles"
                    :key="`m-${i}`"
                    type="button"
                    class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-stone-50 ring-1 transition"
                    :class="active === i ? 'ring-2 ring-stone-900' : 'ring-stone-200/80'"
                    :aria-label="`View image ${i + 1}`"
                    @click="active = i"
                >
                    <img :src="img.src" :alt="img.alt" class="h-full w-full object-contain">
                </button>
                <button
                    v-if="hasVideo"
                    type="button"
                    class="relative h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-stone-900 ring-1 ring-stone-200/80"
                    aria-label="Play product video"
                    @click="openVideo"
                >
                    <img
                        v-if="videoPoster || imageTiles[0]?.src"
                        :src="videoPoster || imageTiles[0]?.src"
                        :alt="`${productName} video`"
                        class="h-full w-full object-cover opacity-80"
                    >
                    <span class="absolute inset-0 flex items-center justify-center bg-black/40">
                        <svg viewBox="0 0 24 24" fill="white" class="h-4 w-4 drop-shadow">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                    </span>
                </button>
            </div>

            <!-- Stacked full-size gallery (desktop only) -->
            <div
                v-if="tiles.length > 1"
                class="mt-4 hidden grid-cols-2 gap-3 sm:grid lg:gap-4"
            >
                <button
                    v-for="(t, i) in tiles"
                    :key="`stack-${i}`"
                    type="button"
                    class="group relative flex aspect-[4/5] w-full items-center justify-center overflow-hidden rounded-2xl bg-stone-50 ring-1 ring-stone-200/80 transition hover:ring-stone-300"
                    :aria-label="t.kind === 'video' ? 'Play product video' : `Open image ${i + 1}`"
                    @click="openLightbox(i)"
                >
                    <img
                        v-if="t.kind === 'image'"
                        :src="t.src"
                        :alt="t.alt"
                        class="h-full w-full object-contain transition duration-200 group-hover:scale-[1.02]"
                        loading="lazy"
                    >
                    <template v-else>
                        <img
                            v-if="t.poster"
                            :src="t.poster"
                            :alt="t.alt"
                            class="h-full w-full object-cover opacity-90"
                            loading="lazy"
                        >
                        <div v-else class="h-full w-full bg-stone-900" />
                        <span class="absolute inset-0 flex items-center justify-center bg-black/30">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white/95 text-stone-900 shadow ring-1 ring-stone-200 backdrop-blur transition group-hover:scale-105">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="ml-0.5 h-6 w-6">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </span>
                        </span>
                    </template>
                </button>
            </div>
        </div>

        <StoreMediaLightbox
            :open="lightboxOpen"
            :items="tiles"
            :index="lightboxIndex"
            :product-name="productName"
            @close="lightboxOpen = false"
            @update:index="(i) => (lightboxIndex = i)"
        />
    </div>
</template>
