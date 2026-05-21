<script setup>
import StoreAuthCard from '@/Components/Store/StoreAuthCard.vue';
import StoreFormField from '@/Components/Store/StoreFormField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const inputClass =
    'mt-2 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-4 text-base text-stadium-ink shadow-sm focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/20';

function submit() {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Register" />
    <StoreAuthCard title="Create account" subtitle="Save orders, wishlist, and addresses across devices.">
        <form class="space-y-5" @submit.prevent="submit">
            <StoreFormField label="Name" for-id="name" :error="form.errors.name">
                <input id="name" v-model="form.name" type="text" required autofocus autocomplete="name" :class="inputClass">
            </StoreFormField>
            <StoreFormField label="Email" for-id="email" :error="form.errors.email">
                <input id="email" v-model="form.email" type="email" required autocomplete="username" :class="inputClass">
            </StoreFormField>
            <StoreFormField label="Password" for-id="password" :error="form.errors.password">
                <input id="password" v-model="form.password" type="password" required autocomplete="new-password" :class="inputClass">
            </StoreFormField>
            <StoreFormField label="Confirm password" for-id="password_confirmation" :error="form.errors.password_confirmation">
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" :class="inputClass">
            </StoreFormField>
            <button
                type="submit"
                class="w-full min-h-12 rounded-xl bg-stadium-lime text-sm font-bold text-stadium-lime-ink transition hover:bg-stadium-lime/90 disabled:opacity-50"
                :disabled="form.processing"
            >
                Register
            </button>
        </form>
        <template #footer>
            <Link :href="route('login')" class="font-semibold text-store-primary underline">Already have an account?</Link>
        </template>
    </StoreAuthCard>
</template>
