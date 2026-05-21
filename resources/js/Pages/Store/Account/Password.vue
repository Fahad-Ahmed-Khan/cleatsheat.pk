<script setup>
import StoreFormField from '@/Components/Store/StoreFormField.vue';
import StoreAccountLayout from '@/Layouts/StoreAccountLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineProps({
    seo: { type: Object, required: true },
    status: { type: String, default: '' },
});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const inputClass =
    'mt-2 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-4 text-base text-stadium-ink shadow-sm placeholder:text-stadium-secondary focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/20';

function submit() {
    form.put(route('store.account.password.update'), {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <StoreAccountLayout :seo="seo" title="Change password">
        <p v-if="status === 'password-updated'" class="mb-6 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
            Password updated.
        </p>
        <form class="max-w-lg space-y-5" @submit.prevent="submit">
            <StoreFormField label="Current password" for-id="current_password" :error="form.errors.current_password">
                <input id="current_password" v-model="form.current_password" type="password" required autocomplete="current-password" :class="inputClass">
            </StoreFormField>
            <StoreFormField label="New password" for-id="password" :error="form.errors.password">
                <input id="password" v-model="form.password" type="password" required autocomplete="new-password" :class="inputClass">
            </StoreFormField>
            <StoreFormField label="Confirm new password" for-id="password_confirmation" :error="form.errors.password_confirmation">
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" :class="inputClass">
            </StoreFormField>
            <button
                type="submit"
                class="min-h-12 rounded-xl bg-stadium-lime px-6 text-sm font-bold text-stadium-lime-ink transition hover:bg-stadium-lime/90 disabled:opacity-50"
                :disabled="form.processing"
            >
                Update password
            </button>
        </form>
    </StoreAccountLayout>
</template>
