<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import FormField from '@/Components/Admin/FormField.vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    url: { type: String, default: '' },
    error: { type: String, default: '' },
    fileError: { type: String, default: '' },
    hint: { type: String, default: '' },
    urlHint: { type: String, default: 'Or paste an image URL below.' },
    accept: { type: String, default: 'image/*' },
    previewSize: { type: String, default: '72px' },
});

const emit = defineEmits(['update:url', 'file']);

const preview = ref(props.url || null);

watch(
    () => props.url,
    (value) => {
        if (!preview.value || !String(preview.value).startsWith('blob:')) {
            preview.value = value || null;
        }
    },
);

function revokeBlob() {
    if (preview.value && String(preview.value).startsWith('blob:')) {
        URL.revokeObjectURL(preview.value);
    }
}

function onPick(e) {
    const file = e?.target?.files?.[0] ?? null;
    emit('file', file);
    if (file) {
        revokeBlob();
        preview.value = URL.createObjectURL(file);
    }
}

onBeforeUnmount(revokeBlob);
</script>

<template>
    <FormField :id="id" :label="label" :error="error || fileError" :hint="hint">
        <template #default="{ invalid, describedBy }">
            <div class="d-flex align-items-start gap-3 mb-2">
                <div
                    class="border rounded bg-body-tertiary overflow-hidden flex-shrink-0"
                    :style="{ width: previewSize, height: previewSize }"
                >
                    <img
                        v-if="preview"
                        :src="preview"
                        alt=""
                        class="w-100 h-100 object-fit-contain"
                    />
                    <div
                        v-else
                        class="d-flex w-100 h-100 align-items-center justify-content-center text-muted small"
                    >
                        <i class="icon-base ti tabler-photo icon-22px"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <input
                        :id="`${id}_file`"
                        type="file"
                        :accept="accept"
                        class="form-control"
                        :class="{ 'is-invalid': invalid && fileError }"
                        :aria-describedby="describedBy"
                        @change="onPick"
                    />
                    <div class="form-text">{{ urlHint }}</div>
                </div>
            </div>
            <input
                :id="id"
                :value="url"
                class="form-control"
                placeholder="https://..."
                :class="{ 'is-invalid': invalid && error }"
                :aria-describedby="describedBy"
                @input="emit('update:url', $event.target.value)"
            />
        </template>
    </FormField>
</template>
