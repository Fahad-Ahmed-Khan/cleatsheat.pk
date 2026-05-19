<script setup>
import { computed } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    summary: { type: Object, default: () => null },
    title: { type: String, default: 'Bulk operation summary' },
});

const emit = defineEmits(['close']);

const booked = computed(() => Number(props.summary?.booked_count ?? 0));
const skippedCount = computed(() => Number(props.summary?.skipped_count ?? 0));
const skipped = computed(() => Array.isArray(props.summary?.skipped) ? props.summary.skipped : []);
</script>

<template>
    <template v-if="open">
        <div
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-labelledby="bulkSummaryModalTitle"
        >
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="bulkSummaryModalTitle" class="modal-title">{{ title }}</h5>
                        <button type="button" class="btn-close" aria-label="Close" @click="emit('close')"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <span class="badge bg-label-success fs-6 py-2 px-3">
                                Queued: {{ booked }}
                            </span>
                            <span class="badge fs-6 py-2 px-3" :class="skippedCount > 0 ? 'bg-label-warning' : 'bg-label-secondary'">
                                Skipped: {{ skippedCount }}
                            </span>
                        </div>

                        <div v-if="skipped.length" class="table-responsive border rounded">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Reason</th>
                                        <th class="text-end" style="width: 110px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, i) in skipped" :key="`${row.order_id}-${i}`">
                                        <td class="text-nowrap fw-semibold">#{{ row.order_id }}</td>
                                        <td class="small">{{ row.reason }}</td>
                                        <td class="text-end">
                                            <a
                                                :href="route('admin.orders.show', row.order_id)"
                                                class="btn btn-sm btn-outline-secondary"
                                            >Go to order</a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-muted small">
                            No rows were skipped.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" @click="emit('close')">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    </template>
</template>
