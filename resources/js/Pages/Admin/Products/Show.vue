<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormSection from '@/Components/Admin/FormSection.vue';

const props = defineProps({
    product: { type: Object, required: true },
});

const stockTotal = computed(() => (props.product.variants || []).reduce((sum, v) => sum + (v.stock_total || 0), 0));
const skuPrimary = computed(() => props.product.variants?.[0]?.sku ?? null);
const priceMin = computed(() => {
    const nums = (props.product.variants || []).map((v) => Number(v.price)).filter((n) => Number.isFinite(n));
    return nums.length ? Math.min(...nums) : null;
});
const priceMax = computed(() => {
    const nums = (props.product.variants || []).map((v) => Number(v.price)).filter((n) => Number.isFinite(n));
    return nums.length ? Math.max(...nums) : null;
});

function formatMoney(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return '—';
    return n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

const imagesSorted = computed(() => {
    const arr = Array.isArray(props.product.images) ? [...props.product.images] : [];
    return arr.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
});
const primaryImage = computed(() => imagesSorted.value[0] ?? null);

function youtubeEmbed(url) {
    const u = String(url || '');
    const m1 = u.match(/[?&]v=([^&]+)/);
    const m2 = u.match(/youtu\.be\/([^?]+)/);
    const id = m1?.[1] || m2?.[1] || null;
    if (!id) return null;
    return `https://www.youtube.com/embed/${id}`;
}
</script>

<template>
    <Head :title="`Admin — ${product.name}`" />
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="mb-1">{{ product.name }}</h4>
                <div class="text-muted small d-flex flex-wrap gap-2">
                    <span>#{{ product.id }}</span>
                    <span v-if="skuPrimary" class="font-monospace">SKU: {{ skuPrimary }}</span>
                    <span v-if="product.brand">Brand: {{ product.brand.name }}</span>
                    <span v-if="product.category">Category: {{ product.category.name }}</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <Link class="btn btn-label-secondary" :href="route('admin.products.index')">
                    <i class="icon-base ti tabler-arrow-left icon-18px me-1"></i>
                    Back
                </Link>
                <Link class="btn btn-primary" :href="route('admin.products.edit', product.id)">
                    <i class="icon-base ti tabler-edit icon-18px me-1"></i>
                    Edit
                </Link>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Price</div>
                            <div class="h4 mb-0">
                                <span v-if="priceMin != null && priceMax != null">
                                    <span v-if="priceMin === priceMax">{{ formatMoney(priceMin) }}</span>
                                    <span v-else>{{ formatMoney(priceMin) }}–{{ formatMoney(priceMax) }}</span>
                                    <small class="text-muted ms-1">PKR</small>
                                </span>
                                <span v-else>—</span>
                            </div>
                        </div>
                        <span class="avatar bg-label-primary">
                            <span class="avatar-initial rounded">
                                <i class="icon-base ti tabler-currency-rupee icon-26px text-heading"></i>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Variants</div>
                            <div class="h4 mb-0">{{ product.variants?.length ?? 0 }}</div>
                        </div>
                        <span class="avatar bg-label-secondary">
                            <span class="avatar-initial rounded">
                                <i class="icon-base ti tabler-layers-subtract icon-26px text-heading"></i>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Total stock</div>
                            <div class="h4 mb-0">{{ stockTotal }}</div>
                        </div>
                        <span class="avatar" :class="stockTotal > 0 ? 'bg-label-success' : 'bg-label-warning'">
                            <span class="avatar-initial rounded">
                                <i class="icon-base ti tabler-box-seam icon-26px text-heading"></i>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <FormSection title="Variants & stock">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Variant</th>
                            <th>SKU</th>
                            <th class="text-nowrap">Price</th>
                            <th class="text-nowrap">Bargain</th>
                            <th>Sizes / stock</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="v in product.variants" :key="v.id">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        v-if="v.color?.hex"
                                        class="rounded-circle border"
                                        :style="{ width: '12px', height: '12px', backgroundColor: v.color.hex }"
                                    ></span>
                                    <span class="fw-semibold">{{ v.color?.name ?? 'Variant' }}</span>
                                    <span v-if="!v.is_active" class="badge bg-label-warning">inactive</span>
                                </div>
                            </td>
                            <td class="font-monospace text-muted">{{ v.sku }}</td>
                            <td class="text-nowrap fw-semibold">{{ formatMoney(v.price) }} <small class="text-muted">PKR</small></td>
                            <td class="text-nowrap">
                                <span v-if="v.bargain_enabled" class="badge bg-label-success">Enabled</span>
                                <span v-else class="badge bg-label-secondary">Off</span>
                                <div v-if="v.bargain_enabled" class="small text-muted mt-1">
                                    <span v-if="v.bargain_min_price != null">Min: {{ formatMoney(v.bargain_min_price) }}</span>
                                    <span v-if="v.bargain_max_discount_percent != null" class="ms-2">Max: {{ v.bargain_max_discount_percent }}%</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <span
                                        v-for="s in (v.sizes || [])"
                                        :key="s.id"
                                        class="badge"
                                        :class="s.stock_qty > 0 ? 'bg-label-success' : 'bg-label-secondary'"
                                    >
                                        {{ s.size_label }}: {{ s.stock_qty }}
                                    </span>
                                    <span v-if="(v.sizes || []).length === 0" class="text-muted small">No sizes</span>
                                </div>
                            </td>
                            <td class="text-end">
                                <span class="badge" :class="(v.stock_total ?? 0) > 0 ? 'bg-label-success' : 'bg-label-warning'">
                                    {{ v.stock_total ?? 0 }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="(product.variants || []).length === 0">
                            <td colspan="6" class="text-muted">No variants.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </FormSection>

        <div class="row g-3 mt-1">
            <div class="col-12 col-lg-7">
                <FormSection title="Media">
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <div class="ratio ratio-4x3 border rounded bg-body-tertiary overflow-hidden">
                                <img
                                    v-if="primaryImage?.path"
                                    :src="primaryImage.path"
                                    :alt="primaryImage.alt || product.name"
                                    class="w-100 h-100 object-fit-cover"
                                />
                                <div v-else class="d-flex align-items-center justify-content-center text-muted small">
                                    No image
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-7">
                            <div class="d-flex flex-wrap gap-2">
                                <div
                                    v-for="(img, idx) in imagesSorted"
                                    :key="idx"
                                    class="border rounded overflow-hidden bg-body-tertiary"
                                    style="width: 86px; height: 64px;"
                                >
                                    <img
                                        v-if="img.path"
                                        :src="img.path"
                                        :alt="img.alt || product.name"
                                        class="w-100 h-100 object-fit-cover"
                                    />
                                </div>
                                <div v-if="imagesSorted.length === 0" class="text-muted small">
                                    No images uploaded.
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="text-muted small">Video</div>
                                <div class="ratio ratio-16x9 border rounded bg-body-tertiary overflow-hidden mt-2">
                                    <iframe
                                        v-if="youtubeEmbed(product.video_url)"
                                        :src="youtubeEmbed(product.video_url)"
                                        title="Product video"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                    ></iframe>
                                    <video
                                        v-else-if="product.video_url && String(product.video_url).toLowerCase().includes('.mp4')"
                                        :src="product.video_url"
                                        controls
                                        class="w-100 h-100"
                                        :poster="product.video_poster || undefined"
                                    />
                                    <img
                                        v-else-if="product.video_poster"
                                        :src="product.video_poster"
                                        alt="Video poster"
                                        class="w-100 h-100 object-fit-cover"
                                    />
                                    <div v-else class="d-flex align-items-center justify-content-center text-muted small">
                                        No video
                                    </div>
                                </div>
                                <div v-if="product.video_url" class="small text-muted mt-2 text-truncate">
                                    {{ product.video_url }}
                                </div>
                            </div>
                        </div>
                    </div>
                </FormSection>

                <FormSection title="Description">
                    <div v-if="product.description" class="prose">
                        <div v-html="product.description"></div>
                    </div>
                    <div v-else class="text-muted small">No description.</div>
                </FormSection>
            </div>

            <div class="col-12 col-lg-5">
                <FormSection title="Details">
                    <div class="row g-2">
                        <div class="col-5 text-muted">Status</div>
                        <div class="col-7">
                            <span class="badge" :class="product.is_active ? 'bg-label-success' : 'bg-label-warning'">
                                {{ product.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <div class="col-5 text-muted">Slug</div>
                        <div class="col-7"><span class="font-monospace">{{ product.slug }}</span></div>

                        <div class="col-5 text-muted">Canonical</div>
                        <div class="col-7">
                            <span v-if="product.canonical_url" class="font-monospace text-truncate d-inline-block" style="max-width: 100%;">{{ product.canonical_url }}</span>
                            <span v-else class="text-muted">—</span>
                        </div>

                        <div class="col-5 text-muted">Size chart</div>
                        <div class="col-7">
                            <span v-if="product.size_chart">{{ product.size_chart.name }}</span>
                            <span v-else class="text-muted">—</span>
                        </div>

                        <div class="col-5 text-muted">Fit</div>
                        <div class="col-7">
                            <span v-if="product.fit_guidance" class="badge bg-label-secondary">{{ product.fit_guidance }}</span>
                            <span v-else class="text-muted">—</span>
                        </div>

                        <div class="col-5 text-muted">Gender</div>
                        <div class="col-7">
                            <span v-if="product.gender" class="badge bg-label-secondary">{{ product.gender }}</span>
                            <span v-else class="text-muted">—</span>
                        </div>

                        <div class="col-5 text-muted">Shoe type</div>
                        <div class="col-7">
                            <span v-if="product.shoe_type" class="badge bg-label-secondary">{{ product.shoe_type }}</span>
                            <span v-else class="text-muted">—</span>
                        </div>
                    </div>
                </FormSection>

                <FormSection title="Sizing & fit notes">
                    <div v-if="product.fit_notes" class="mb-3">
                        <div class="text-muted small mb-1">Fit notes</div>
                        <div class="border rounded p-2 bg-body-tertiary small" style="white-space: pre-wrap;">{{ product.fit_notes }}</div>
                    </div>
                    <div v-if="product.size_info">
                        <div class="text-muted small mb-1">Size info</div>
                        <div class="border rounded p-2 bg-body-tertiary small" style="white-space: pre-wrap;">{{ product.size_info }}</div>
                    </div>
                    <div v-if="!product.fit_notes && !product.size_info" class="text-muted small">—</div>
                </FormSection>

                <FormSection title="Features">
                    <ul v-if="(product.features || []).length" class="mb-0">
                        <li v-for="(f, idx) in product.features" :key="idx">{{ f }}</li>
                    </ul>
                    <div v-else class="text-muted small">No features.</div>
                </FormSection>

                <FormSection title="SEO">
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Meta title</div>
                        <div class="border rounded p-2 bg-body-tertiary small">{{ product.meta_title || '—' }}</div>
                    </div>
                    <div>
                        <div class="text-muted small mb-1">Meta description</div>
                        <div class="border rounded p-2 bg-body-tertiary small" style="white-space: pre-wrap;">{{ product.meta_description || '—' }}</div>
                    </div>
                </FormSection>
            </div>
        </div>
    </AdminLayout>
</template>

