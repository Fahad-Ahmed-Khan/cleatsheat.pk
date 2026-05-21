<script setup>
import StoreFormField from '@/Components/Store/StoreFormField.vue';
import StoreAccountLayout from '@/Layouts/StoreAccountLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    seo: { type: Object, required: true },
    addresses: { type: Array, default: () => [] },
    editing: { type: Object, default: null },
});

const cardClass =
    'rounded-2xl bg-stadium-white p-5 shadow-stadium ring-1 ring-stadium-outline-soft/50';

const inputClass =
    'mt-2 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-4 text-base text-stadium-ink shadow-sm placeholder:text-stadium-secondary focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/20';

const isEditing = computed(() => props.editing !== null);

const form = useForm({
    full_name: props.editing?.full_name ?? '',
    phone: props.editing?.phone ?? '',
    line1: props.editing?.line1 ?? '',
    city: props.editing?.city ?? '',
    area: props.editing?.area ?? '',
    postal_code: props.editing?.postal_code ?? '',
    is_default: props.editing?.is_default ?? false,
});

function submit() {
    if (isEditing.value) {
        form.patch(route('store.account.addresses.update', props.editing.id));
    } else {
        form.post(route('store.account.addresses.store'), {
            onSuccess: () => form.reset(),
        });
    }
}
</script>

<template>
    <StoreAccountLayout :seo="seo" title="Saved addresses">
        <ul v-if="addresses.length" class="space-y-3">
            <li v-for="addr in addresses" :key="addr.id" :class="cardClass">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p v-if="addr.is_default" class="text-xs font-bold uppercase text-store-primary">Default</p>
                        <p class="mt-1 text-sm font-semibold text-stadium-ink">{{ addr.full_name }}</p>
                        <p class="mt-1 text-sm text-stadium-secondary">
                            {{ addr.phone }}<br>
                            {{ addr.line1 }}<br>
                            {{ addr.city }}<span v-if="addr.area">, {{ addr.area }}</span>
                        </p>
                    </div>
                    <div class="flex gap-3 text-sm font-semibold">
                        <Link :href="route('store.account.addresses', { edit: addr.id })" class="text-store-primary underline">Edit</Link>
                        <Link
                            :href="route('store.account.addresses.destroy', addr.id)"
                            method="delete"
                            as="button"
                            class="text-red-600 underline"
                            onclick="return confirm('Delete this address?')"
                        >
                            Delete
                        </Link>
                    </div>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-stadium-secondary">No saved addresses yet.</p>

        <section class="mt-10 max-w-lg">
            <h2 class="text-sm font-semibold text-stadium-ink">{{ isEditing ? 'Edit address' : 'Add address' }}</h2>
            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <StoreFormField label="Full name" for-id="addr-name" :error="form.errors.full_name">
                    <input id="addr-name" v-model="form.full_name" type="text" required :class="inputClass">
                </StoreFormField>
                <StoreFormField label="Phone" for-id="addr-phone" :error="form.errors.phone">
                    <input id="addr-phone" v-model="form.phone" type="tel" required :class="inputClass">
                </StoreFormField>
                <StoreFormField label="Address line" for-id="addr-line1" :error="form.errors.line1">
                    <input id="addr-line1" v-model="form.line1" type="text" required :class="inputClass">
                </StoreFormField>
                <StoreFormField label="City" for-id="addr-city" :error="form.errors.city">
                    <input id="addr-city" v-model="form.city" type="text" required :class="inputClass">
                </StoreFormField>
                <StoreFormField label="Area" for-id="addr-area" :error="form.errors.area">
                    <input id="addr-area" v-model="form.area" type="text" :class="inputClass">
                </StoreFormField>
                <StoreFormField label="Postal code" for-id="addr-postal" :error="form.errors.postal_code">
                    <input id="addr-postal" v-model="form.postal_code" type="text" :class="inputClass">
                </StoreFormField>
                <label class="flex items-center gap-2 text-sm text-stadium-ink">
                    <input v-model="form.is_default" type="checkbox" class="rounded border-stadium-outline-soft text-store-primary focus:ring-store-primary/20">
                    Set as default address
                </label>
                <div class="flex gap-3">
                    <button
                        type="submit"
                        class="min-h-12 rounded-xl bg-stadium-lime px-6 text-sm font-bold text-stadium-lime-ink transition hover:bg-stadium-lime/90 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ isEditing ? 'Save changes' : 'Add address' }}
                    </button>
                    <Link
                        v-if="isEditing"
                        :href="route('store.account.addresses')"
                        class="inline-flex min-h-12 items-center px-4 text-sm font-semibold text-stadium-secondary underline"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </section>
    </StoreAccountLayout>
</template>
