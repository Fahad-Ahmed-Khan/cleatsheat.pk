<script setup>
import { computed } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});

const describedBy = computed(() => {
    const ids = [];
    if (props.hint) ids.push(`${props.id}-hint`);
    if (props.error) ids.push(`${props.id}-error`);
    return ids.join(' ') || null;
});
</script>

<template>
    <div class="mb-3">
        <label class="form-label" :for="id">{{ label }}</label>
        <slot :invalid="!!error" :described-by="describedBy" />
        <div v-if="hint" :id="`${id}-hint`" class="form-text">
            {{ hint }}
        </div>
        <div v-if="error" :id="`${id}-error`" class="invalid-feedback d-block">
            {{ error }}
        </div>
    </div>
</template>

