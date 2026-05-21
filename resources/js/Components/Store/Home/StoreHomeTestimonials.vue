<script setup>
defineProps({
    testimonials: { type: Array, default: () => [] },
});
</script>

<template>
    <section
        v-if="testimonials.length"
        class="store-section bg-stadium-white"
        aria-labelledby="reviews-heading"
    >
        <div class="store-container">
            <h2 id="reviews-heading" class="text-display-md text-stadium-ink">Players across Pakistan</h2>
            <p class="mt-2 text-base text-stadium-secondary">
                Real feedback from Lahore, Karachi, Islamabad, and beyond.
            </p>
            <div class="no-scrollbar mt-8 flex gap-4 overflow-x-auto pb-2 snap-x md:grid md:grid-cols-3 md:overflow-visible md:snap-none">
                <figure
                    v-for="(t, i) in testimonials"
                    :key="i"
                    class="w-[min(85vw,320px)] shrink-0 snap-start rounded-2xl border border-stadium-outline-soft/40 bg-stadium-muted p-6 md:w-auto"
                >
                    <div class="flex gap-0.5 text-stadium-lime" aria-hidden="true">
                        <span v-for="s in (t.rating || 5)" :key="s">★</span>
                    </div>
                    <blockquote class="mt-4 text-sm leading-relaxed text-stadium-ink">
                        “{{ t.quote }}”
                    </blockquote>
                    <figcaption class="mt-4 text-xs font-semibold text-stadium-secondary">
                        {{ t.name }} · {{ t.city }}
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>
</template>
