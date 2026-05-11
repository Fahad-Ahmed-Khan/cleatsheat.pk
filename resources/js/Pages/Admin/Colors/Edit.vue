<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    color: { type: Object, required: true },
});

const form = useForm({
    name: props.color.name,
    slug: props.color.slug,
    hex: props.color.hex,
});

function submit() {
    form.put(route('admin.colors.update', props.color.id));
}
</script>

<template>
    <Head title="Admin — Edit color" />
    <AdminLayout>
        <AdminPageHeader
            title="Edit color"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Colors', href: route('admin.colors.index') }, { label: props.color.name }]"
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

                <FormField id="color_hex" label="Hex" :error="form.errors.hex">
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
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.colors.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
