<script setup>
import { ref } from 'vue';

defineProps({
    enabled: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});

const email = ref('');
const submitted = ref(false);

function onSubmit(e) {
    e.preventDefault();
    if (!email.value.trim()) return;
    submitted.value = true;
}
</script>

<template>
    <section
        v-if="enabled && !compact"
        class="store-section border-t border-stadium-outline-soft/20 bg-stadium-white"
        aria-labelledby="newsletter-heading"
    >
        <div class="store-container">
            <div
                class="overflow-hidden rounded-2xl bg-stadium-inverse px-8 py-10 text-stadium-inverse-text md:flex md:items-center md:justify-between md:gap-10 md:px-12"
            >
                <div class="md:max-w-md">
                    <h2 id="newsletter-heading" class="text-display-md text-white">Join the squad</h2>
                    <p class="mt-2 text-base text-stadium-inverse-text/80">
                        Early access to new drops, sale alerts, and expert pitch advice — no spam.
                    </p>
                </div>
                <form class="mt-6 flex w-full max-w-md flex-col gap-2 sm:flex-row md:mt-0" @submit="onSubmit">
                    <label class="sr-only" for="newsletter-email">Email</label>
                    <input
                        id="newsletter-email"
                        v-model="email"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="Your email address"
                        class="min-h-12 flex-1 rounded-xl border border-transparent bg-white/10 px-4 text-white placeholder:text-stadium-inverse-text/50 focus:border-stadium-lime focus:outline-none focus:ring-1 focus:ring-stadium-lime"
                    />
                    <button
                        type="submit"
                        class="min-h-12 shrink-0 rounded-xl bg-stadium-lime px-6 text-label text-stadium-lime-ink transition hover:bg-stadium-lime/90 active:scale-[0.98]"
                    >
                        {{ submitted ? 'Thanks!' : 'Subscribe' }}
                    </button>
                </form>
            </div>
        </div>
    </section>
</template>
