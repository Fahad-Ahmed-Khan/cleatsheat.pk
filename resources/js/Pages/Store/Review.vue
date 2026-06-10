<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    seo: { type: Object, required: true },
});

const page = usePage();
const submitted = computed(() => !!page.props.flash?.success);

const form = useForm({
    author_name: '',
    city: '',
    quote: '',
    rating: 5,
    email: '',
});

const ratingOptions = [5, 4, 3, 2, 1];

function submit() {
    form.post(route('store.review.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.rating = 5;
        },
    });
}
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="store-container py-10 sm:py-14">
            <div class="mx-auto max-w-xl">
                <p class="text-xs font-semibold uppercase tracking-widest text-stadium-secondary">
                    Customer feedback
                </p>
                <h1 class="mt-2 text-display-md text-stadium-ink">
                    How was your experience?
                </h1>
                <p class="mt-3 text-base text-stadium-secondary">
                    Share a quick review about your boots, sizing, delivery, or support. Your words help other players across Pakistan.
                </p>

                <div
                    v-if="submitted"
                    class="mt-8 rounded-2xl border border-stadium-lime/40 bg-stadium-lime/10 p-5 text-sm text-stadium-ink"
                    role="status"
                >
                    {{ page.props.flash.success }}
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <div>
                        <label for="author_name" class="block text-sm font-semibold text-stadium-ink">
                            Your name <span class="text-stadium-secondary">*</span>
                        </label>
                        <input
                            id="author_name"
                            v-model="form.author_name"
                            type="text"
                            autocomplete="name"
                            maxlength="120"
                            required
                            class="mt-2 w-full rounded-xl border border-stadium-outline-soft/60 bg-stadium-white px-4 py-3 text-sm text-stadium-ink outline-none ring-stadium-lime/40 focus:border-stadium-lime focus:ring-2"
                            placeholder="e.g. Hassan R."
                        />
                        <p v-if="form.errors.author_name" class="mt-1 text-xs text-red-600">
                            {{ form.errors.author_name }}
                        </p>
                    </div>

                    <div>
                        <label for="city" class="block text-sm font-semibold text-stadium-ink">
                            City
                        </label>
                        <input
                            id="city"
                            v-model="form.city"
                            type="text"
                            autocomplete="address-level2"
                            maxlength="80"
                            class="mt-2 w-full rounded-xl border border-stadium-outline-soft/60 bg-stadium-white px-4 py-3 text-sm text-stadium-ink outline-none ring-stadium-lime/40 focus:border-stadium-lime focus:ring-2"
                            placeholder="e.g. Lahore"
                        />
                        <p v-if="form.errors.city" class="mt-1 text-xs text-red-600">
                            {{ form.errors.city }}
                        </p>
                    </div>

                    <fieldset>
                        <legend class="text-sm font-semibold text-stadium-ink">
                            Rating <span class="text-stadium-secondary">*</span>
                        </legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <label
                                v-for="value in ratingOptions"
                                :key="value"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm transition"
                                :class="form.rating === value
                                    ? 'border-stadium-lime bg-stadium-lime/20 text-stadium-ink'
                                    : 'border-stadium-outline-soft/60 text-stadium-secondary hover:border-stadium-lime/60'"
                            >
                                <input
                                    v-model.number="form.rating"
                                    type="radio"
                                    name="rating"
                                    :value="value"
                                    class="sr-only"
                                />
                                <span aria-hidden="true">{{ '★'.repeat(value) }}</span>
                                <span class="font-medium">{{ value }}</span>
                            </label>
                        </div>
                        <p v-if="form.errors.rating" class="mt-1 text-xs text-red-600">
                            {{ form.errors.rating }}
                        </p>
                    </fieldset>

                    <div>
                        <label for="quote" class="block text-sm font-semibold text-stadium-ink">
                            Your feedback <span class="text-stadium-secondary">*</span>
                        </label>
                        <textarea
                            id="quote"
                            v-model="form.quote"
                            rows="5"
                            maxlength="2000"
                            required
                            class="mt-2 w-full rounded-xl border border-stadium-outline-soft/60 bg-stadium-white px-4 py-3 text-sm text-stadium-ink outline-none ring-stadium-lime/40 focus:border-stadium-lime focus:ring-2"
                            placeholder="Tell us about fit, condition, delivery, or WhatsApp support…"
                        />
                        <p class="mt-1 text-xs text-stadium-secondary">
                            {{ form.quote.length }} / 2,000 characters
                        </p>
                        <p v-if="form.errors.quote" class="mt-1 text-xs text-red-600">
                            {{ form.errors.quote }}
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-stadium-ink">
                            Email <span class="text-stadium-secondary">(optional)</span>
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            maxlength="255"
                            class="mt-2 w-full rounded-xl border border-stadium-outline-soft/60 bg-stadium-white px-4 py-3 text-sm text-stadium-ink outline-none ring-stadium-lime/40 focus:border-stadium-lime focus:ring-2"
                            placeholder="Only if you want us to follow up"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-stadium-lime px-6 py-3.5 text-sm font-bold text-stadium-ink transition hover:brightness-95 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Submitting…' : 'Submit review' }}
                    </button>
                </form>
            </div>
        </div>
    </StoreLayout>
</template>
