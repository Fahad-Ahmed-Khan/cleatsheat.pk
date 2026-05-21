<script setup>
import StoreFormField from '@/Components/Store/StoreFormField.vue';
import StoreAccountLayout from '@/Layouts/StoreAccountLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    seo: { type: Object, required: true },
    mustVerifyEmail: { type: Boolean, default: false },
    status: { type: String, default: '' },
});

const page = usePage();
const user = page.props.auth.user;

const form = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
});

const deleteForm = useForm({ password: '' });

const inputClass =
    'mt-2 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-4 text-base text-stadium-ink shadow-sm placeholder:text-stadium-secondary focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/20';

function submitProfile() {
    form.patch(route('store.account.profile.update'));
}

function submitDelete() {
    if (!confirm('Delete your account? This cannot be undone.')) return;
    deleteForm.delete(route('store.account.profile.destroy'));
}
</script>

<template>
    <StoreAccountLayout :seo="seo" title="Profile">
        <p v-if="status === 'profile-updated'" class="mb-6 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
            Profile saved.
        </p>

        <form class="max-w-lg space-y-5" @submit.prevent="submitProfile">
            <StoreFormField label="Name" for-id="name" :error="form.errors.name">
                <input id="name" v-model="form.name" type="text" required autocomplete="name" :class="inputClass">
            </StoreFormField>
            <StoreFormField label="Email" for-id="email" :error="form.errors.email">
                <input id="email" v-model="form.email" type="email" required autocomplete="username" :class="inputClass">
            </StoreFormField>
            <StoreFormField label="Phone" for-id="phone" :error="form.errors.phone" hint="Used for order updates and WhatsApp.">
                <input id="phone" v-model="form.phone" type="tel" autocomplete="tel" :class="inputClass">
            </StoreFormField>
            <button
                type="submit"
                class="min-h-12 rounded-xl bg-stadium-lime px-6 text-sm font-bold text-stadium-lime-ink transition hover:bg-stadium-lime/90 disabled:opacity-50"
                :disabled="form.processing"
            >
                Save profile
            </button>
        </form>

        <section class="mt-12 max-w-lg rounded-2xl border border-red-200 bg-red-50/50 p-6">
            <h2 class="text-sm font-semibold text-red-900">Delete account</h2>
            <p class="mt-1 text-sm text-red-800">Permanently remove your account and sign out.</p>
            <form class="mt-4 space-y-4" @submit.prevent="submitDelete">
                <StoreFormField label="Confirm password" for-id="delete-password" :error="deleteForm.errors.password">
                    <input id="delete-password" v-model="deleteForm.password" type="password" required :class="inputClass">
                </StoreFormField>
                <button
                    type="submit"
                    class="min-h-12 rounded-xl bg-red-600 px-6 text-sm font-bold text-white transition hover:bg-red-700 disabled:opacity-50"
                    :disabled="deleteForm.processing"
                >
                    Delete account
                </button>
            </form>
        </section>
    </StoreAccountLayout>
</template>
