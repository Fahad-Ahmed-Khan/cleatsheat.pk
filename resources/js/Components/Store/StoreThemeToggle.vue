<script setup>
import { toggleStoreTheme, getStoreTheme } from '@/store/storeTheme';
import { onMounted, ref } from 'vue';

const theme = ref('light');

onMounted(() => {
    theme.value = getStoreTheme();
});

function toggle() {
    theme.value = toggleStoreTheme();
}
</script>

<template>
    <button
        type="button"
        class="flex items-center justify-center rounded-xl border border-stadium-outline-soft/60 bg-stadium-muted p-2 text-stadium-ink transition hover:bg-stadium-container-high active:scale-95"
        :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggle"
    >
        <svg
            v-if="theme === 'dark'"
            class="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke-linecap="round" />
        </svg>
        <svg
            v-else
            class="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        >
            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
</template>
