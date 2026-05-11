<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';

const props = defineProps({
    brands: { type: Array, required: true },
    categories: { type: Array, required: true },
    colors: { type: Array, required: true },
    size_charts: { type: Array, required: true },
    enums: { type: Object, required: true },
    product: { type: Object, default: null },
});

const objectUrls = ref(new Map());

function setObjectUrl(key, file) {
    const prev = objectUrls.value.get(key);
    if (prev) {
        URL.revokeObjectURL(prev);
    }
    if (!file) {
        objectUrls.value.delete(key);
        return null;
    }
    const url = URL.createObjectURL(file);
    objectUrls.value.set(key, url);
    return url;
}

onBeforeUnmount(() => {
    for (const url of objectUrls.value.values()) {
        URL.revokeObjectURL(url);
    }
    objectUrls.value.clear();
});

function blankVariant() {
    return {
        color_id: props.colors[0]?.id ?? '',
        sku: '',
        price: '',
        compare_at_price: '',
        is_active: true,
        sizes: [
            {
                size_label: 'UK 8',
                uk_size: '8',
                eu_size: '42',
                pk_size: '8',
                stock_qty: 0,
                low_stock_threshold: 2,
            },
        ],
    };
}

function buildInitial() {
    if (props.product) {
        return {
            brand_id: props.product.brand_id,
            category_id: props.product.category_id,
            size_chart_id: props.product.size_chart_id ?? '',
            name: props.product.name,
            slug: props.product.slug,
            description: props.product.description ?? '',
            meta_title: props.product.meta_title ?? '',
            meta_description: props.product.meta_description ?? '',
            canonical_url: props.product.canonical_url ?? '',
            video_url: props.product.video_url ?? '',
            video_file: null,
            video_poster: props.product.video_poster ?? '',
            fit_guidance: props.product.fit_guidance,
            gender: props.product.gender,
            shoe_type: props.product.shoe_type,
            fit_notes: props.product.fit_notes ?? '',
            size_info: props.product.size_info ?? '',
            features: props.product.features?.length > 0 ? [...props.product.features] : [''],
            is_active: props.product.is_active,
            images:
                props.product.images?.length > 0
                    ? props.product.images.map((img, i) => ({
                          path: img.path,
                          file: null,
                          alt: img.alt ?? '',
                          sort_order: img.sort_order ?? i,
                      }))
                    : [{ path: '', file: null, alt: '', sort_order: 0 }],
            variants: props.product.variants.map((v) => ({
                color_id: v.color_id,
                sku: v.sku,
                price: v.price,
                compare_at_price: v.compare_at_price ?? '',
                is_active: v.is_active,
                sizes: v.sizes.map((s) => ({
                    size_label: s.size_label,
                    uk_size: s.uk_size ?? '',
                    eu_size: s.eu_size ?? '',
                    pk_size: s.pk_size ?? '',
                    stock_qty: s.stock_qty,
                    low_stock_threshold: s.low_stock_threshold ?? 0,
                })),
            })),
        };
    }

    return {
        brand_id: props.brands[0]?.id ?? '',
        category_id: props.categories[0]?.id ?? '',
        size_chart_id: '',
        name: '',
        slug: '',
        description: '',
        meta_title: '',
        meta_description: '',
        canonical_url: '',
        video_url: '',
        video_file: null,
        video_poster: '',
        fit_guidance: props.enums.fit_guidance[0]?.value ?? 'true_to_size',
        gender: props.enums.gender[0]?.value ?? 'men',
        shoe_type: props.enums.shoe_type[0]?.value ?? 'sneaker',
        fit_notes: '',
        size_info: '',
        features: [''],
        is_active: true,
        images: [{ path: '', file: null, alt: '', sort_order: 0 }],
        variants: [blankVariant()],
    };
}

const form = useForm(buildInitial());

watch(
    () => form.images.map((img) => img.file),
    (files) => {
        files.forEach((f, i) => {
            setObjectUrl(`img-${i}`, f || null);
        });
    },
    { deep: true },
);

const isEdit = computed(() => !!props.product?.id);

function submit() {
    const opts = { preserveScroll: true, forceFormData: true };
    if (isEdit.value) {
        // Real PUT/PATCH with multipart bodies cannot be parsed by PHP, so we
        // explicitly POST with method spoofing whenever the form may contain files.
        form.transform((data) => ({
            ...data,
            _method: 'put',
            features: (data.features || []).filter((f) => f && String(f).trim() !== ''),
            size_chart_id: data.size_chart_id === '' ? null : data.size_chart_id,
        }));
        form.post(route('admin.products.update', props.product.id), opts);
    } else {
        form.transform((data) => ({
            ...data,
            features: (data.features || []).filter((f) => f && String(f).trim() !== ''),
            size_chart_id: data.size_chart_id === '' ? null : data.size_chart_id,
        }));
        form.post(route('admin.products.store'), opts);
    }
}

function destroyProduct() {
    if (!props.product?.id) {
        return;
    }
    if (!window.confirm('Delete this product?')) {
        return;
    }
    router.delete(route('admin.products.destroy', props.product.id));
}

function addFeature() {
    form.features.push('');
}

function removeFeature(i) {
    if (form.features.length <= 1) {
        form.features[0] = '';
        return;
    }
    form.features.splice(i, 1);
}

function addImage() {
    form.images.push({ path: '', file: null, alt: '', sort_order: form.images.length });
}

function removeImage(i) {
    setObjectUrl(`img-${i}`, null);
    form.images.splice(i, 1);
}

function onPickImageFile(i, e) {
    const file = e?.target?.files?.[0] ?? null;
    form.images[i].file = file;
}

function onPickVideoFile(e) {
    const file = e?.target?.files?.[0] ?? null;
    form.video_file = file;
    setObjectUrl('video', file || null);
}

function clearVideoFile() {
    form.video_file = null;
    setObjectUrl('video', null);
    const input = document.getElementById('product-video-input');
    if (input) {
        input.value = '';
    }
}

function addVariant() {
    form.variants.push(blankVariant());
}

function removeVariant(i) {
    if (form.variants.length <= 1) {
        return;
    }
    form.variants.splice(i, 1);
}

function addSize(vi) {
    form.variants[vi].sizes.push({
        size_label: 'UK 9',
        uk_size: '9',
        eu_size: '43',
        pk_size: '9',
        stock_qty: 0,
        low_stock_threshold: 2,
    });
}

function removeSize(vi, si) {
    if (form.variants[vi].sizes.length <= 1) {
        return;
    }
    form.variants[vi].sizes.splice(si, 1);
}
</script>

<template>
    <div>
        <AdminPageHeader
            :title="isEdit ? 'Edit product' : 'New product'"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Products', href: route('admin.products.index') }, { label: isEdit ? form.name || 'Product' : 'New' }]"
        >
            <template #actions>
                <button v-if="isEdit" type="button" class="btn btn-outline-danger btn-sm" @click="destroyProduct">
                    Delete
                </button>
            </template>
        </AdminPageHeader>

        <form class="mt-3" @submit.prevent="submit">
            <FormSection title="Core">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Brand</label>
                        <select v-model="form.brand_id" class="form-select" required>
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <div v-if="form.errors.brand_id" class="invalid-feedback d-block">{{ form.errors.brand_id }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Category</label>
                        <select v-model="form.category_id" class="form-select" required>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Size chart override</label>
                        <select v-model="form.size_chart_id" class="form-select">
                            <option value="">Default (match brand / gender / type)</option>
                            <option v-for="ch in size_charts" :key="ch.id" :value="ch.id">{{ ch.label }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Name</label>
                        <input v-model="form.name" type="text" class="form-control" required />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Slug</label>
                        <input v-model="form.slug" type="text" class="form-control font-monospace" required />
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input id="prod_active" v-model="form.is_active" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="prod_active">Active on storefront</label>
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Description & fit">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Description (HTML)</label>
                        <textarea v-model="form.description" rows="5" class="form-control font-monospace" />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Fit guidance</label>
                        <select v-model="form.fit_guidance" class="form-select">
                            <option v-for="o in enums.fit_guidance" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Gender</label>
                        <select v-model="form.gender" class="form-select">
                            <option v-for="o in enums.gender" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Shoe type</label>
                        <select v-model="form.shoe_type" class="form-select">
                            <option v-for="o in enums.shoe_type" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Fit notes</label>
                        <textarea v-model="form.fit_notes" rows="2" class="form-control" />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Size info (SEO — UK/EU/PK guidance)</label>
                        <textarea v-model="form.size_info" rows="3" class="form-control" placeholder="e.g. UK 8 ≈ EU 42.5. Half sizes: size up for wide feet." />
                    </div>
                </div>
            </FormSection>

            <FormSection title="Features" description="Short bullet points shown on the product page.">
                <div class="vstack gap-2">
                    <div v-for="(_, i) in form.features" :key="i" class="d-flex gap-2">
                        <input v-model="form.features[i]" type="text" class="form-control" placeholder="Feature" />
                        <button type="button" class="btn btn-outline-secondary" @click="removeFeature(i)">Remove</button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="addFeature">+ Add feature</button>
                    </div>
                </div>
            </FormSection>

            <FormSection title="SEO">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Meta title</label>
                        <input v-model="form.meta_title" type="text" class="form-control" />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Meta description</label>
                        <textarea v-model="form.meta_description" rows="2" class="form-control" />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Canonical URL</label>
                        <input v-model="form.canonical_url" type="text" class="form-control" />
                    </div>
                </div>
            </FormSection>

            <FormSection title="Video" description="Paste a YouTube/Vimeo/direct .mp4 URL, or upload a video file (≤ 50 MB).">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="ratio ratio-16x9 border rounded bg-body-tertiary overflow-hidden">
                        <video
                            v-if="objectUrls.get('video')"
                            :src="objectUrls.get('video')"
                            controls
                            class="w-100 h-100"
                        />
                        <img
                            v-else-if="form.video_poster"
                            :src="form.video_poster"
                            alt="Video poster"
                            class="w-100 h-100"
                        >
                        <div v-else class="d-flex w-100 h-100 align-items-center justify-content-center small text-muted">
                            No video
                        </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Video URL</label>
                            <input
                                v-model="form.video_url"
                                type="text"
                                class="form-control"
                                placeholder="https://www.youtube.com/watch?v=… or https://…/clip.mp4"
                            >
                            <div v-if="form.errors.video_url" class="invalid-feedback d-block">{{ form.errors.video_url }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Or upload a video file</label>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <input
                                    id="product-video-input"
                                    type="file"
                                    accept="video/mp4,video/webm,video/quicktime"
                                    class="form-control"
                                    @change="onPickVideoFile"
                                >
                                <button
                                    v-if="form.video_file"
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    @click="clearVideoFile"
                                >
                                    Clear
                                </button>
                            </div>
                            <div class="form-text">Upload will override the URL above when saved.</div>
                            <div v-if="form.errors.video_file" class="invalid-feedback d-block">{{ form.errors.video_file }}</div>
                        </div>
                        <div>
                            <label class="form-label">Poster image URL (optional)</label>
                            <input
                                v-model="form.video_poster"
                                type="text"
                                class="form-control"
                                placeholder="https://…/poster.jpg"
                            >
                            <div v-if="form.errors.video_poster" class="invalid-feedback d-block">{{ form.errors.video_poster }}</div>
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Images">
                <div class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="addImage">+ Image</button>
                </div>
                <div class="vstack gap-3">
                    <div
                        v-for="(img, i) in form.images"
                        :key="i"
                        class="border rounded p-3"
                    >
                        <div class="row g-3 align-items-start">
                            <div class="col-12 col-md-auto">
                                <div class="border rounded bg-body-tertiary overflow-hidden" style="width: 120px; height: 150px;">
                                <img
                                    v-if="objectUrls.get(`img-${i}`) || img.path"
                                    :src="objectUrls.get(`img-${i}`) || img.path"
                                    :alt="img.alt || form.name || 'Product image'"
                                    class="w-100 h-100"
                                >
                                <div v-else class="d-flex w-100 h-100 align-items-center justify-content-center small text-muted">
                                    No image
                                </div>
                            </div>
                            </div>
                            <div class="col-12 col-md">
                                <div class="mb-3">
                                    <label class="form-label">Upload</label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="form-control"
                                        @change="(e) => onPickImageFile(i, e)"
                                    >
                                    <div class="form-text">Upload will override the URL/path below.</div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL / path</label>
                            <input
                                v-model="img.path"
                                type="text"
                                class="form-control"
                                placeholder="https://..."
                            >
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label class="form-label">Alt</label>
                            <input
                                v-model="img.alt"
                                type="text"
                                class="form-control"
                            >
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Sort</label>
                            <input
                                v-model.number="img.sort_order"
                                type="number"
                                min="0"
                                class="form-control"
                            >
                            </div>
                        </div>
                        <div>
                            <button type="button" class="btn btn-link text-danger p-0" @click="removeImage(i)">Remove image</button>
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Variants & inventory">
                <div class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="addVariant">+ Variant</button>
                </div>

                <div v-for="(variant, vi) in form.variants" :key="vi" class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="fw-semibold">Variant {{ vi + 1 }}</div>
                        <button v-if="form.variants.length > 1" type="button" class="btn btn-link text-danger p-0" @click="removeVariant(vi)">Remove variant</button>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Color</label>
                            <select v-model="variant.color_id" class="form-select" required>
                                <option v-for="col in colors" :key="col.id" :value="col.id">{{ col.name }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">SKU</label>
                            <input v-model="variant.sku" type="text" class="form-control font-monospace" required />
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Price (PKR)</label>
                            <input v-model="variant.price" type="number" min="0" step="1" class="form-control" required />
                        </div>
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Compare-at</label>
                            <input v-model="variant.compare_at_price" type="number" min="0" step="1" class="form-control" />
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input :id="`variant-active-${vi}`" v-model="variant.is_active" type="checkbox" class="form-check-input" />
                                <label class="form-check-label" :for="`variant-active-${vi}`">Variant active</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="fw-semibold">Sizes (UK / EU / PK + stock)</div>
                            <button type="button" class="btn btn-sm btn-outline-primary" @click="addSize(vi)">+ Size row</button>
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Label</th>
                                        <th>UK</th>
                                        <th>EU</th>
                                        <th>PK</th>
                                        <th>Stock</th>
                                        <th>Low at</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(sz, si) in variant.sizes" :key="si">
                                        <td><input v-model="sz.size_label" class="form-control form-control-sm" required /></td>
                                        <td><input v-model="sz.uk_size" class="form-control form-control-sm" /></td>
                                        <td><input v-model="sz.eu_size" class="form-control form-control-sm" /></td>
                                        <td><input v-model="sz.pk_size" class="form-control form-control-sm" /></td>
                                        <td><input v-model.number="sz.stock_qty" type="number" min="0" class="form-control form-control-sm" required /></td>
                                        <td><input v-model.number="sz.low_stock_threshold" type="number" min="0" class="form-control form-control-sm" /></td>
                                        <td class="text-end">
                                            <button v-if="variant.sizes.length > 1" type="button" class="btn btn-sm btn-outline-danger" @click="removeSize(vi, si)">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </FormSection>

            <div v-if="Object.keys(form.errors).length" class="alert alert-danger">
                <div class="fw-semibold">Fix validation errors</div>
                <ul class="mb-0 mt-2">
                    <li v-for="(msg, key) in form.errors" :key="key">
                        {{ key }}: {{ Array.isArray(msg) ? msg[0] : msg }}
                    </li>
                </ul>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                    {{ isEdit ? 'Save changes' : 'Create product' }}
                </button>
                <Link :href="route('admin.products.index')" class="btn btn-outline-secondary">
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>
