<script setup>
import StoreAuthCard from '@/Components/Store/StoreAuthCard.vue';
import StoreFormField from '@/Components/Store/StoreFormField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String, default: '' },
});

const form = useForm({ email: '' });

const inputClass =
    'mt-2 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-4 text-base text-stadium-ink shadow-sm focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/20';

function submit() {
    form.post(route('password.email'));
}
</script>

<template>
    <Head title="Forgot password" />
    <StoreAuthCard title="Forgot password" subtitle="We will email you a reset link.">
        <p v-if="status" class="mb-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">{{ status }}</p>
        <form class="space-y-5" @submit.prevent="submit">
            <StoreFormField label="Email" for-id="email" :error="form.errors.email">
                <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" :class="inputClass">
            </StoreFormField>
            <button
                type="submit"
                class="w-full min-h-12 rounded-xl bg-stadium-lime text-sm font-bold text-stadium-lime-ink transition hover:bg-stadium-lime/90 disabled:opacity-50"
                :disabled="form.processing"
            >
                Email reset link
            </button>
        </form>
        <template #footer>
            <Link :href="route('login')" class="font-semibold text-store-primary underline">Back to log in</Link>
        </template>
    </StoreAuthCard>
</template>
