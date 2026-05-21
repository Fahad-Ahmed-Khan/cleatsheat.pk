<script setup>
import FormField from '@/Components/Admin/FormField.vue';
import { computed, onMounted, watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    rootParents: { type: Array, default: () => [] },
    presetParentId: { type: [Number, String, null], default: null },
    defaultKind: { type: String, default: 'parent' },
    hasChildren: { type: Boolean, default: false },
    isEdit: { type: Boolean, default: false },
});

const categoryKind = computed({
    get() {
        if (props.hasChildren) {
            return 'parent';
        }
        return props.form.parent_id ? 'sub' : 'parent';
    },
    set(kind) {
        if (props.hasChildren && kind === 'sub') {
            return;
        }
        if (kind === 'parent') {
            props.form.parent_id = '';
        } else if (props.rootParents.length && !props.form.parent_id) {
            props.form.parent_id = props.rootParents[0].id;
        }
    },
});

const isSubcategory = computed(() => categoryKind.value === 'sub');

const imageHint = computed(() =>
    isSubcategory.value
        ? 'Shown on the home page subcategory cards and as the social (OG) image.'
        : 'Shown on home category tabs, the department spotlight, and as the OG image.',
);

watch(
    () => props.presetParentId,
    (id) => {
        if (id) {
            props.form.parent_id = id;
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.defaultKind === 'sub' && props.rootParents.length && !props.form.parent_id) {
        props.form.parent_id = props.presetParentId ?? props.rootParents[0].id;
    }
});

function slugifyName() {
    if (props.isEdit && props.form.slug) {
        return;
    }
    props.form.slug = String(props.form.name ?? '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
</script>

<template>
    <div class="mb-4">
        <label class="form-label d-block">Category type</label>
        <div class="btn-group" role="group" aria-label="Category type">
            <input
                id="kind_parent"
                type="radio"
                class="btn-check"
                name="category_kind"
                value="parent"
                :checked="categoryKind === 'parent'"
                :disabled="hasChildren"
                @change="categoryKind = 'parent'"
            />
            <label class="btn btn-outline-primary" for="kind_parent">Parent category</label>

            <input
                id="kind_sub"
                type="radio"
                class="btn-check"
                name="category_kind"
                value="sub"
                :checked="categoryKind === 'sub'"
                :disabled="hasChildren"
                @change="categoryKind = 'sub'"
            />
            <label class="btn btn-outline-primary" for="kind_sub">Subcategory</label>
        </div>
        <p v-if="hasChildren" class="form-text text-muted mb-0">
            This category has subcategories, so it must stay a parent.
        </p>
        <p v-else class="form-text text-muted mb-0">
            <strong>Parent</strong> appears as a tab on the storefront home page.
            <strong>Subcategory</strong> appears inside that tab’s grid.
        </p>
    </div>

    <FormField
        v-if="isSubcategory"
        id="cat_parent"
        label="Parent category"
        :error="form.errors.parent_id"
    >
        <template #default="{ invalid, describedBy }">
            <select
                id="cat_parent"
                v-model="form.parent_id"
                class="form-select"
                required
                :class="{ 'is-invalid': invalid }"
                :aria-describedby="describedBy"
            >
                <option value="" disabled>Select parent…</option>
                <option v-for="p in rootParents" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <p v-if="!rootParents.length" class="form-text text-warning mb-0">
                Create a parent category first, then add subcategories under it.
            </p>
        </template>
    </FormField>

    <FormField id="cat_name" label="Name" :error="form.errors.name">
        <template #default="{ invalid, describedBy }">
            <input
                id="cat_name"
                v-model="form.name"
                required
                class="form-control"
                :class="{ 'is-invalid': invalid }"
                :aria-describedby="describedBy"
                @blur="slugifyName"
            />
        </template>
    </FormField>

    <FormField id="cat_slug" label="Slug" :error="form.errors.slug">
        <template #default="{ invalid, describedBy }">
            <input
                id="cat_slug"
                v-model="form.slug"
                required
                pattern="[a-z0-9-]+"
                class="form-control font-monospace"
                :class="{ 'is-invalid': invalid }"
                :aria-describedby="describedBy"
            />
        </template>
    </FormField>

    <FormField id="cat_sort" label="Sort order" hint="Lower numbers appear first." :error="form.errors.sort_order">
        <template #default="{ invalid, describedBy }">
            <input
                id="cat_sort"
                v-model.number="form.sort_order"
                type="number"
                min="0"
                class="form-control"
                :class="{ 'is-invalid': invalid }"
                :aria-describedby="describedBy"
            />
        </template>
    </FormField>

    <div class="mb-3">
        <div class="form-check">
            <input id="cat_active" v-model="form.is_active" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="cat_active">Active (visible on storefront)</label>
        </div>
        <div v-if="form.errors.is_active" class="invalid-feedback d-block">{{ form.errors.is_active }}</div>
    </div>

    <FormField id="cat_meta_title" label="Meta title" :error="form.errors.meta_title">
        <template #default="{ invalid, describedBy }">
            <input
                id="cat_meta_title"
                v-model="form.meta_title"
                class="form-control"
                :class="{ 'is-invalid': invalid }"
                :aria-describedby="describedBy"
            />
        </template>
    </FormField>

    <FormField id="cat_meta_desc" label="Meta description" :error="form.errors.meta_description">
        <template #default="{ invalid, describedBy }">
            <textarea
                id="cat_meta_desc"
                v-model="form.meta_description"
                rows="2"
                class="form-control"
                :class="{ 'is-invalid': invalid }"
                :aria-describedby="describedBy"
            />
        </template>
    </FormField>

    <FormField id="cat_og" label="Category image URL" :hint="imageHint" :error="form.errors.og_image_url">
        <template #default="{ invalid, describedBy }">
            <input
                id="cat_og"
                v-model="form.og_image_url"
                type="url"
                class="form-control"
                placeholder="https://..."
                :class="{ 'is-invalid': invalid }"
                :aria-describedby="describedBy"
            />
            <div v-if="form.og_image_url" class="mt-2">
                <img
                    :src="form.og_image_url"
                    alt="Preview"
                    class="rounded border"
                    style="max-height: 120px; max-width: 100%; object-fit: cover"
                    loading="lazy"
                    @error="($event.target).style.display = 'none'"
                />
            </div>
        </template>
    </FormField>

    <FormField id="cat_intro" label="Category intro (HTML)" :error="form.errors.intro_html">
        <template #default="{ invalid, describedBy }">
            <textarea
                id="cat_intro"
                v-model="form.intro_html"
                rows="4"
                class="form-control font-monospace"
                :class="{ 'is-invalid': invalid }"
                :aria-describedby="describedBy"
            />
        </template>
    </FormField>
</template>
