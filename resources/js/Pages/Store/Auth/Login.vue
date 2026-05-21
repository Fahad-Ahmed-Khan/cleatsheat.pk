<script setup>
import StoreAuthCard from '@/Components/Store/StoreAuthCard.vue';
import StoreFormField from '@/Components/Store/StoreFormField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String, default: '' },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const inputClass =
    'mt-2 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-4 text-base text-stadium-ink shadow-sm focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/20';

function submit() {
    form.post(route('login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Log in" />
    <StoreAuthCard title="Log in" subtitle="Access your orders, wishlist, and saved addresses.">
        <p v-if="status" class="mb-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">{{ status }}</p>
        <form class="space-y-5" @submit.prevent="submit">
            <StoreFormField label="Email" for-id="email" :error="form.errors.email">
                <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" :class="inputClass">
            </StoreFormField>
            <StoreFormField label="Password" for-id="password" :error="form.errors.password">
                <input id="password" v-model="form.password" type="password" required autocomplete="current-password" :class="inputClass">
            </StoreFormField>
            <label class="flex items-center gap-2 text-sm text-stadium-secondary">
                <input v-model="form.remember" type="checkbox" class="rounded border-stadium-outline-soft">
                Remember me
            </label>
            <button
                type="submit"
                class="w-full min-h-12 rounded-xl bg-stadium-lime text-sm font-bold text-stadium-lime-ink transition hover:bg-stadium-lime/90 disabled:opacity-50"
                :disabled="form.processing"
            >
                Log in
            </button>
        </form>
        <template #footer>
            <Link v-if="canResetPassword" :href="route('password.request')" class="font-semibold text-store-primary underline">Forgot password?</Link>
            <span class="mx-2">·</span>
            <Link :href="route('register')" class="font-semibold text-store-primary underline">Create account</Link>
        </template>
    </StoreAuthCard>
</template>
