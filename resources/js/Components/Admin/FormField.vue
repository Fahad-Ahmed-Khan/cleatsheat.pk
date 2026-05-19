<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});

const slots = useSlots();

const describedBy = computed(() => {
    const ids = [];
    if (props.hint) ids.push(`${props.id}-hint`);
    if (props.error) ids.push(`${props.id}-error`);
    return ids.join(' ') || null;
});

const hasLabelSlot = computed(() => Boolean(slots.label));
</script>

<template>
    <div class="mb-3">
        <label class="form-label" :for="id">
            <slot v-if="hasLabelSlot" name="label" />
            <template v-else>{{ label }}</template>
        </label>
        <slot :invalid="!!error" :described-by="describedBy" />
        <div v-if="hint" :id="`${id}-hint`" class="form-text">
            {{ hint }}
        </div>
        <div v-if="error" :id="`${id}-error`" class="invalid-feedback d-block">
            {{ error }}
        </div>
    </div>
</template>
