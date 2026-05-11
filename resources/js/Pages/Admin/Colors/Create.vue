<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const form = useForm({
    name: '',
    slug: '',
    hex: '#888888',
});

function submit() {
    form.post(route('admin.colors.store'));
}
</script>

<template>
    <Head title="Admin — New color" />
    <AdminLayout>
        <AdminPageHeader
            title="New color"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Colors', href: route('admin.colors.index') }, { label: 'New' }]"
        />

        <form @submit.prevent="submit">
            <FormSection title="Color">
                <FormField id="color_name" label="Name" :error="form.errors.name">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="color_name"
                            v-model="form.name"
                            required
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="color_slug" label="Slug" :error="form.errors.slug">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="color_slug"
                            v-model="form.slug"
                            required
                            pattern="[a-z0-9-]+"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField id="color_hex" label="Hex" :error="form.errors.hex" hint="Format: #RRGGBB">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="color_hex"
                            v-model="form.hex"
                            type="text"
                            pattern="^#[0-9A-Fa-f]{6}$"
                            class="form-control font-monospace"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Create</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.colors.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
