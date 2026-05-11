<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
</script>

<template>
    <Head title="Admin — Design system" />
    <AdminLayout>
        <AdminPageHeader
            title="Design system"
            subtitle="Preview of admin components (dev-only page file; add a route if you want it accessible)."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Design system' }]"
        >
            <template #actions>
                <Link class="btn btn-primary btn-sm" :href="route('admin.dashboard')">
                    Back
                </Link>
            </template>
        </AdminPageHeader>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <StatCard title="Example KPI" :value="123" :href="route('admin.products.index')" />
            </div>
            <div class="col-12 col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Badge</div>
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <StatusBadge status="paid" />
                            <StatusBadge status="pending" />
                            <StatusBadge status="failed" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <FormSection title="Form section" description="Example field rendering">
            <FormField id="exampleName" label="Name" :error="''" hint="This is a hint.">
                <template #default="{ invalid, describedBy }">
                    <input
                        id="exampleName"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': invalid }"
                        :aria-describedby="describedBy"
                        value="Example"
                        readonly
                    />
                </template>
            </FormField>
        </FormSection>

        <DataTable
            title="Data table"
            :paginator="{ data: [], links: [] }"
            empty-title="Nothing here yet"
            empty-description="This is the empty state."
        >
            <template #head>
                <th>Name</th>
                <th>Status</th>
            </template>
            <template #body>
                <tr>
                    <td>Row 1</td>
                    <td><StatusBadge status="active" /></td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>

