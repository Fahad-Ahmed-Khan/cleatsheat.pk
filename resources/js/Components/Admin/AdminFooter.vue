<script setup>
defineProps({
    rightText: { type: String, default: 'v1' },
});
</script>

<template>
    <footer class="content-footer footer bg-footer-theme">
        <div class="container-xxl">
            <div class="footer-container d-flex align-items-center justify-content-between py-2">
                <div class="text-muted small">Tryino Admin</div>
                <div class="text-muted small">{{ rightText }}</div>
            </div>
        </div>
    </footer>
</template>

