<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

defineProps({
    reviews: { type: Object, required: true },
    reviewFormUrl: { type: String, required: true },
    qrSvg: { type: String, required: true },
});

function destroy(id) {
    if (!window.confirm('Remove this review from the homepage testimonials?')) {
        return;
    }
    router.delete(route('admin.customer-reviews.destroy', id));
}

function printQr() {
    window.print();
}
</script>

<template>
    <Head title="Admin — Customer reviews" />
    <AdminLayout>
        <AdminPageHeader
            title="Customer reviews"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Customer reviews' }]"
        />

        <div class="row g-4 mb-4 print-hide">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Quick review QR code</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="d-inline-block p-3 bg-white border rounded" v-html="qrSvg" />
                        <p class="mt-3 mb-1 text-muted small">
                            Customers scan this code to open the review form on their phone.
                        </p>
                        <code class="d-block small text-break">{{ reviewFormUrl }}</code>
                        <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" @click="printQr">
                                Print QR
                            </button>
                            <a
                                class="btn btn-outline-secondary btn-sm"
                                :href="reviewFormUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open form
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">How it works</h5>
                        <ul class="mb-0 text-muted small">
                            <li>Print the QR and place it in your packaging or at the counter.</li>
                            <li>Submitted reviews appear in the homepage testimonials section.</li>
                            <li>Remove any review below to hide it from the storefront.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-none d-print-block text-center py-5">
            <h2 class="mb-4">Scan to leave a review</h2>
            <div class="d-inline-block" v-html="qrSvg" />
            <p class="mt-4 text-muted">{{ reviewFormUrl }}</p>
        </div>

        <div class="print-hide">
            <DataTable :paginator="reviews" empty-title="No customer reviews yet">
                <template #head>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Feedback</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="text-end">Actions</th>
                </template>
                <template #body>
                    <tr v-for="r in reviews.data" :key="r.id">
                        <td>
                            <div class="fw-semibold">{{ r.author_name }}</div>
                            <div v-if="r.city" class="text-muted small">{{ r.city }}</div>
                            <div v-if="r.email" class="text-muted small">{{ r.email }}</div>
                        </td>
                        <td>
                            <span class="text-warning" aria-hidden="true">{{ '★'.repeat(r.rating) }}</span>
                            <span class="visually-hidden">{{ r.rating }} out of 5</span>
                        </td>
                        <td class="small" style="max-width: 320px;">
                            {{ r.quote }}
                        </td>
                        <td>
                            <StatusBadge :status="r.is_published ? 'published' : 'draft'" />
                        </td>
                        <td class="text-muted small text-nowrap">
                            {{ r.created_at }}
                        </td>
                        <td class="text-end">
                            <button
                                type="button"
                                class="btn btn-outline-danger btn-sm"
                                @click="destroy(r.id)"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>

<style scoped>
@media print {
    .print-hide {
        display: none !important;
    }
}
</style>
