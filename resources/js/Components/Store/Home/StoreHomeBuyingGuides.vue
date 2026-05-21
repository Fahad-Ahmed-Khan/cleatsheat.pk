<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    journalPosts: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="store-section bg-stadium-inverse text-stadium-inverse-text" aria-labelledby="guides-heading">
        <div class="store-container">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-label text-stadium-lime">Expert guides</p>
                    <h2 id="guides-heading" class="text-display-md text-white">Football boot buying guides</h2>
                    <p class="mt-2 max-w-lg text-base text-stadium-inverse-text/80">
                        FG vs SG vs AG, UK/EU sizing for Pakistan, and how we grade used pairs.
                    </p>
                </div>
                <Link
                    :href="route('store.journal.index')"
                    class="inline-flex w-fit items-center gap-1 text-label text-stadium-lime hover:underline"
                >
                    All guides
                    <span aria-hidden="true">→</span>
                </Link>
            </div>

            <div v-if="journalPosts.length" class="mt-8 grid gap-4 md:grid-cols-3">
                <Link
                    v-for="post in journalPosts"
                    :key="post.slug"
                    :href="route('store.journal.show', post.slug)"
                    class="group rounded-2xl border border-white/10 bg-white/5 p-6 transition hover:border-stadium-lime/40 hover:bg-white/10"
                >
                    <h3 class="font-display text-lg font-bold leading-snug text-white group-hover:text-stadium-lime">
                        {{ post.title }}
                    </h3>
                    <p v-if="post.excerpt" class="mt-2 line-clamp-3 text-sm text-stadium-inverse-text/75">
                        {{ post.excerpt }}
                    </p>
                    <span class="mt-4 inline-flex text-xs font-bold uppercase tracking-wide text-stadium-lime">
                        Read guide →
                    </span>
                </Link>
            </div>

            <div
                v-else
                class="mt-8 rounded-2xl border border-stadium-lime/30 bg-white/5 p-8 md:p-12"
            >
                <h3 class="font-display text-2xl font-extrabold text-white">How to choose your boots</h3>
                <p class="mt-3 max-w-md text-sm text-stadium-inverse-text/80">
                    Match your play style and pitch surface to the right plate and upper — guides coming soon in the journal.
                </p>
                <Link
                    :href="route('store.journal.index')"
                    class="mt-6 inline-flex rounded-xl border-2 border-stadium-lime px-6 py-3 text-label text-stadium-lime transition hover:bg-stadium-lime hover:text-stadium-lime-ink"
                >
                    Visit journal
                </Link>
            </div>
        </div>
    </section>
</template>
