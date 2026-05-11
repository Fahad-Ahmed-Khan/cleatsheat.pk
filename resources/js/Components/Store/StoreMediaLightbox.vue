<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] }, // [{ kind: 'image' | 'video', src, poster?, alt? }]
    index: { type: Number, default: 0 },
    productName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'update:index']);

const ZOOM_MIN = 1;
const ZOOM_MAX = 3;
const ZOOM_STEP = 0.5;

const zoom = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);
const isPanning = ref(false);
const panStart = ref({ x: 0, y: 0, ox: 0, oy: 0 });

const current = computed(() => props.items[props.index] ?? null);
const hasMany = computed(() => (props.items?.length ?? 0) > 1);

const isImage = computed(() => current.value?.kind === 'image');
const isVideo = computed(() => current.value?.kind === 'video');

function detectVideoEmbed(rawUrl) {
    if (!rawUrl || typeof rawUrl !== 'string') {
        return null;
    }
    const url = rawUrl.trim();

    // YouTube formats: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID, youtube.com/shorts/ID
    const yt = url.match(
        /(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/i,
    );
    if (yt && yt[1]) {
        return {
            type: 'iframe',
            src: `https://www.youtube.com/embed/${yt[1]}?autoplay=1&rel=0&modestbranding=1`,
        };
    }

    // Vimeo
    const vm = url.match(/vimeo\.com\/(?:video\/)?(\d+)/i);
    if (vm && vm[1]) {
        return {
            type: 'iframe',
            src: `https://player.vimeo.com/video/${vm[1]}?autoplay=1`,
        };
    }

    // Direct file (mp4, webm, mov)
    if (/\.(mp4|webm|ogg|mov)(\?.*)?$/i.test(url)) {
        return { type: 'file', src: url };
    }

    // Fallback: try iframe (might be a generic embed URL)
    return { type: 'iframe', src: url };
}

const videoEmbed = computed(() => (isVideo.value ? detectVideoEmbed(current.value.src) : null));

function close() {
    emit('close');
}

function next() {
    if (!hasMany.value) return;
    const n = (props.index + 1) % props.items.length;
    emit('update:index', n);
    resetTransform();
}

function prev() {
    if (!hasMany.value) return;
    const n = (props.index - 1 + props.items.length) % props.items.length;
    emit('update:index', n);
    resetTransform();
}

function resetTransform() {
    zoom.value = 1;
    offsetX.value = 0;
    offsetY.value = 0;
}

function zoomIn() {
    if (!isImage.value) return;
    zoom.value = Math.min(ZOOM_MAX, Math.round((zoom.value + ZOOM_STEP) * 10) / 10);
}

function zoomOut() {
    if (!isImage.value) return;
    zoom.value = Math.max(ZOOM_MIN, Math.round((zoom.value - ZOOM_STEP) * 10) / 10);
    if (zoom.value === 1) {
        offsetX.value = 0;
        offsetY.value = 0;
    }
}

function onWheel(e) {
    if (!isImage.value) return;
    e.preventDefault();
    if (e.deltaY < 0) zoomIn();
    else zoomOut();
}

function onPointerDown(e) {
    if (!isImage.value || zoom.value <= 1) return;
    isPanning.value = true;
    panStart.value = {
        x: e.clientX,
        y: e.clientY,
        ox: offsetX.value,
        oy: offsetY.value,
    };
}

function onPointerMove(e) {
    if (!isPanning.value) return;
    offsetX.value = panStart.value.ox + (e.clientX - panStart.value.x);
    offsetY.value = panStart.value.oy + (e.clientY - panStart.value.y);
}

function onPointerUp() {
    isPanning.value = false;
}

function onKey(e) {
    if (!props.open) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === '+' || e.key === '=') zoomIn();
    else if (e.key === '-' || e.key === '_') zoomOut();
    else if (e.key === '0') resetTransform();
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            resetTransform();
            window.addEventListener('keydown', onKey);
            const html = document.documentElement;
            html.dataset.lightboxLockedOverflow = html.style.overflow || '';
            html.style.overflow = 'hidden';
        } else {
            window.removeEventListener('keydown', onKey);
            const html = document.documentElement;
            html.style.overflow = html.dataset.lightboxLockedOverflow ?? '';
            delete html.dataset.lightboxLockedOverflow;
        }
    },
);

watch(
    () => props.index,
    () => resetTransform(),
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    const html = document.documentElement;
    if (html.dataset.lightboxLockedOverflow !== undefined) {
        html.style.overflow = html.dataset.lightboxLockedOverflow;
        delete html.dataset.lightboxLockedOverflow;
    }
});

const transformStyle = computed(() => ({
    transform: `translate(${offsetX.value}px, ${offsetY.value}px) scale(${zoom.value})`,
    cursor: zoom.value > 1 ? (isPanning.value ? 'grabbing' : 'grab') : 'zoom-in',
    transition: isPanning.value ? 'none' : 'transform 200ms ease',
}));
</script>

<template>
    <Teleport to="body">
        <Transition name="lb-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-[80] flex flex-col bg-stone-950/95 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
            >
                <!-- Top bar -->
                <div class="flex items-center justify-between px-4 pt-4 sm:px-6">
                    <div class="text-xs font-medium text-stone-300">
                        <span v-if="hasMany">{{ index + 1 }} / {{ items.length }}</span>
                        <span v-else>&nbsp;</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <template v-if="isImage">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/15 transition hover:bg-white/20"
                                :disabled="zoom <= ZOOM_MIN"
                                aria-label="Zoom out"
                                @click="zoomOut"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                    <circle cx="11" cy="11" r="7" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                    <line x1="8" y1="11" x2="14" y2="11" />
                                </svg>
                            </button>
                            <span class="hidden text-[11px] font-mono text-stone-300 sm:inline">
                                {{ Math.round(zoom * 100) }}%
                            </span>
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/15 transition hover:bg-white/20"
                                :disabled="zoom >= ZOOM_MAX"
                                aria-label="Zoom in"
                                @click="zoomIn"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                    <circle cx="11" cy="11" r="7" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                    <line x1="11" y1="8" x2="11" y2="14" />
                                    <line x1="8" y1="11" x2="14" y2="11" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="hidden h-9 items-center justify-center rounded-full bg-white/10 px-3 text-[11px] font-medium text-white ring-1 ring-white/15 transition hover:bg-white/20 sm:flex"
                                aria-label="Reset zoom"
                                @click="resetTransform"
                            >
                                Reset
                            </button>
                        </template>
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/15 transition hover:bg-white/20"
                            aria-label="Close"
                            @click="close"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Stage -->
                <div
                    class="relative flex flex-1 select-none items-center justify-center overflow-hidden px-2 py-3 sm:px-12"
                    @wheel="onWheel"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                    @pointerleave="onPointerUp"
                >
                    <!-- Image -->
                    <img
                        v-if="isImage && current?.src"
                        :src="current.src"
                        :alt="current.alt || productName"
                        class="max-h-full max-w-full object-contain"
                        :style="transformStyle"
                        draggable="false"
                    >

                    <!-- Direct video file -->
                    <video
                        v-else-if="isVideo && videoEmbed?.type === 'file'"
                        :key="videoEmbed.src"
                        :src="videoEmbed.src"
                        :poster="current?.poster || undefined"
                        controls
                        autoplay
                        playsinline
                        class="max-h-full max-w-full"
                    />

                    <!-- Embedded video (YouTube / Vimeo / generic iframe) -->
                    <div
                        v-else-if="isVideo && videoEmbed?.type === 'iframe'"
                        class="aspect-video w-full max-w-5xl overflow-hidden rounded-xl bg-black ring-1 ring-white/10"
                    >
                        <iframe
                            :key="videoEmbed.src"
                            :src="videoEmbed.src"
                            class="h-full w-full"
                            title="Product video"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                        />
                    </div>

                    <!-- Prev / Next overlay -->
                    <button
                        v-if="hasMany"
                        type="button"
                        class="absolute left-2 top-1/2 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/15 transition hover:bg-white/20 sm:left-6"
                        aria-label="Previous"
                        @click="prev"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>
                    <button
                        v-if="hasMany"
                        type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/15 transition hover:bg-white/20 sm:right-6"
                        aria-label="Next"
                        @click="next"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>

                <!-- Bottom thumbnail strip -->
                <div
                    v-if="hasMany"
                    class="mx-auto flex w-full max-w-3xl gap-2 overflow-x-auto px-4 pb-4 pt-2 sm:px-6 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        v-for="(item, i) in items"
                        :key="`lb-${i}`"
                        type="button"
                        class="relative h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-white/5 ring-1 transition"
                        :class="i === index ? 'ring-2 ring-white' : 'ring-white/15 hover:ring-white/40'"
                        :aria-label="`Go to ${item.kind} ${i + 1}`"
                        @click="emit('update:index', i)"
                    >
                        <img
                            v-if="item.kind === 'image'"
                            :src="item.src"
                            :alt="item.alt || productName"
                            class="h-full w-full object-cover"
                        >
                        <div v-else class="relative h-full w-full">
                            <img
                                v-if="item.poster"
                                :src="item.poster"
                                :alt="`${productName} video`"
                                class="h-full w-full object-cover"
                            >
                            <div v-else class="flex h-full w-full items-center justify-center bg-stone-900 text-white/80">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </div>
                            <span class="absolute inset-0 flex items-center justify-center bg-black/35">
                                <svg viewBox="0 0 24 24" fill="white" class="h-4 w-4 drop-shadow">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.lb-fade-enter-active,
.lb-fade-leave-active {
    transition: opacity 180ms ease;
}
.lb-fade-enter-from,
.lb-fade-leave-to {
    opacity: 0;
}
</style>
