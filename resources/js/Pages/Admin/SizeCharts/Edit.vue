<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    brands: { type: Array, required: true },
    enums: { type: Object, required: true },
    chart: { type: Object, required: true },
});

const form = useForm({
    brand_id: props.chart.brand_id,
    name: props.chart.name,
    gender: props.chart.gender ?? '',
    shoe_type: props.chart.shoe_type ?? '',
    rows: props.chart.rows?.length
        ? props.chart.rows.map((r) => ({
              sort_order: r.sort_order ?? 0,
              label: r.label ?? '',
              uk_size: r.uk_size ?? '',
              eu_size: r.eu_size ?? '',
              pk_size: r.pk_size ?? '',
              foot_cm: r.foot_cm ?? null,
          }))
        : [
              {
                  sort_order: 0,
                  label: '',
                  uk_size: '',
                  eu_size: '',
                  pk_size: '',
                  foot_cm: null,
              },
          ],
});

function addRow() {
    form.rows.push({
        sort_order: form.rows.length,
        label: '',
        uk_size: '',
        eu_size: '',
        pk_size: '',
        foot_cm: null,
    });
}

function removeRow(i) {
    if (form.rows.length <= 1) {
        return;
    }
    form.rows.splice(i, 1);
}

function submit() {
    form.put(route('admin.size-charts.update', props.chart.id));
}

function destroyChart() {
    if (!window.confirm('Delete this size chart?')) {
        return;
    }
    router.delete(route('admin.size-charts.destroy', props.chart.id));
}
</script>

<template>
    <Head title="Admin — Edit size chart" />
    <AdminLayout>
        <AdminPageHeader
            title="Edit size chart"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Size charts', href: route('admin.size-charts.index') }, { label: form.name || 'Chart' }]"
        >
            <template #actions>
                <button type="button" class="btn btn-outline-danger btn-sm" @click="destroyChart">Delete</button>
            </template>
        </AdminPageHeader>

        <form @submit.prevent="submit">
            <FormSection title="Chart details">
                <FormField id="sc_brand" label="Brand" :error="form.errors.brand_id">
                    <template #default="{ invalid, describedBy }">
                        <select
                            id="sc_brand"
                            v-model="form.brand_id"
                            required
                            class="form-select"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        >
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </template>
                </FormField>

                <FormField id="sc_name" label="Chart name" :error="form.errors.name">
                    <template #default="{ invalid, describedBy }">
                        <input
                            id="sc_name"
                            v-model="form.name"
                            required
                            class="form-control"
                            :class="{ 'is-invalid': invalid }"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <FormField id="sc_gender" label="Gender filter" :error="form.errors.gender">
                            <template #default="{ invalid, describedBy }">
                                <select
                                    id="sc_gender"
                                    v-model="form.gender"
                                    class="form-select"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                >
                                    <option value="">—</option>
                                    <option v-for="o in enums.gender" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </template>
                        </FormField>
                    </div>
                    <div class="col-12 col-md-6">
                        <FormField id="sc_shoe_type" label="Shoe type filter" :error="form.errors.shoe_type">
                            <template #default="{ invalid, describedBy }">
                                <select
                                    id="sc_shoe_type"
                                    v-model="form.shoe_type"
                                    class="form-select"
                                    :class="{ 'is-invalid': invalid }"
                                    :aria-describedby="describedBy"
                                >
                                    <option value="">—</option>
                                    <option v-for="o in enums.shoe_type" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </template>
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Rows (UK · EU · PK · cm)">
                <div class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="addRow">
                        + Row
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Label</th>
                                <th>UK</th>
                                <th>EU</th>
                                <th>PK</th>
                                <th>Foot cm</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in form.rows" :key="i">
                                <td><input v-model="row.label" class="form-control form-control-sm" /></td>
                                <td><input v-model="row.uk_size" class="form-control form-control-sm" /></td>
                                <td><input v-model="row.eu_size" class="form-control form-control-sm" /></td>
                                <td><input v-model="row.pk_size" class="form-control form-control-sm" /></td>
                                <td><input v-model.number="row.foot_cm" type="number" step="0.1" min="0" class="form-control form-control-sm" /></td>
                                <td class="text-end">
                                    <button v-if="form.rows.length > 1" type="button" class="btn btn-sm btn-outline-danger" @click="removeRow(i)">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <template #actions>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Save</button>
                    <Link class="btn btn-outline-secondary" :href="route('admin.size-charts.index')">Cancel</Link>
                </template>
            </FormSection>
        </form>
    </AdminLayout>
</template>
