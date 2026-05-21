<script setup>
import { computed } from 'vue';

const props = defineProps({
    social: { type: Object, default: () => ({}) },
});

const posts = computed(() => props.social?.posts ?? []);
const instagramUrl = computed(() => props.social?.instagram_url || '#');
const tiktokUrl = computed(() => props.social?.tiktok_url || '#');
</script>

<template>
    <section class="store-section bg-stadium" aria-labelledby="social-heading">
        <div class="store-container">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="social-heading" class="text-display-md text-stadium-ink">On the pitch with us</h2>
                    <p class="mt-1 text-base text-stadium-secondary">
                        Match-day fits, unboxings, and surface tips on Instagram & TikTok.
                    </p>
                </div>
                <div class="flex gap-3">
                    <a
                        :href="instagramUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center rounded-xl border border-stadium-outline-soft px-4 text-sm font-bold text-stadium-ink transition hover:border-store-primary"
                    >
                        Instagram
                    </a>
                    <a
                        :href="tiktokUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center rounded-xl border border-stadium-outline-soft px-4 text-sm font-bold text-stadium-ink transition hover:border-store-primary"
                    >
                        TikTok
                    </a>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
                <a
                    v-for="(post, i) in posts"
                    :key="i"
                    :href="post.url || (post.platform === 'tiktok' ? tiktokUrl : instagramUrl)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group relative aspect-square overflow-hidden rounded-2xl bg-stadium-inverse ring-1 ring-stadium-outline-soft/30 transition hover:ring-store-primary/40"
                >
                    <img
                        v-if="post.image_url"
                        :src="post.image_url"
                        :alt="post.caption"
                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="flex h-full flex-col items-center justify-center bg-gradient-to-br from-stadium-inverse to-stadium-olive/40 p-4 text-center"
                    >
                        <span
                            class="font-display text-xs font-bold uppercase tracking-widest text-stadium-lime"
                        >
                            {{ post.platform }}
                        </span>
                        <p class="mt-2 text-sm font-medium text-stadium-inverse-text/90">
                            {{ post.caption }}
                        </p>
                    </div>
                    <div
                        class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3 opacity-0 transition group-hover:opacity-100"
                    >
                        <p class="text-xs font-semibold text-white">{{ post.caption }}</p>
                    </div>
                </a>
            </div>
        </div>
    </section>
</template>
