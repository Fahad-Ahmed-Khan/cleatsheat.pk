<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    open: { type: Boolean, required: true },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <Transition name="store-menu-backdrop">
            <div
                v-if="open"
                class="fixed inset-0 z-[70] bg-stadium-ink/40 backdrop-blur-sm md:hidden"
                aria-hidden="true"
                @click="emit('close')"
            />
        </Transition>
        <Transition name="store-menu-panel">
            <aside
                v-if="open"
                class="fixed inset-y-0 left-0 z-[71] flex w-[min(100vw-3rem,20rem)] flex-col bg-stadium-white shadow-stadium-lg md:hidden"
                role="dialog"
                aria-modal="true"
                aria-labelledby="store-mobile-menu-title"
            >
                <div class="flex items-center justify-between border-b border-stadium-outline-soft/40 px-4 py-4">
                    <h2 id="store-mobile-menu-title" class="font-display text-lg font-extrabold tracking-tighter text-stadium-ink">
                        Menu
                    </h2>
                    <button
                        type="button"
                        class="rounded-xl p-2 text-stadium-olive transition hover:bg-stadium-muted active:scale-95"
                        aria-label="Close menu"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 overflow-y-auto px-3 py-4">
                    <p class="px-2 text-[11px] font-bold uppercase tracking-widest text-stadium-outline">
                        Browse
                    </p>
                    <ul class="mt-3 space-y-1">
                        <li>
                            <Link
                                :href="route('store.home')"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="emit('close')"
                            >
                                Home
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.shop')"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="emit('close')"
                            >
                                Shop all
                            </Link>
                        </li>
                        <li v-for="c in categories" :key="c.id">
                            <Link
                                v-if="c.slug"
                                :href="route('store.category', c.slug)"
                                class="block rounded-xl px-3 py-3 text-sm font-medium text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink"
                                @click="emit('close')"
                            >
                                {{ c.name }}
                            </Link>
                        </li>
                    </ul>
                    <p class="mt-6 px-2 text-[11px] font-bold uppercase tracking-widest text-stadium-outline">
                        Orders
                    </p>
                    <ul class="mt-3 space-y-1">
                        <li>
                            <Link
                                :href="route('store.cart')"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="emit('close')"
                            >
                                Cart
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.order-tracking')"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="emit('close')"
                            >
                                Track order
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.journal.index')"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="emit('close')"
                            >
                                Blog
                            </Link>
                        </li>
                    </ul>
                    <p class="mt-6 px-2 text-[11px] font-bold uppercase tracking-widest text-stadium-outline">
                        Pages
                    </p>
                    <ul class="mt-3 space-y-1">
                        <li>
                            <Link
                                :href="route('store.pages.privacy')"
                                class="block rounded-xl px-3 py-3 text-sm font-medium text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink"
                                @click="emit('close')"
                            >
                                Privacy policy
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.pages.terms')"
                                class="block rounded-xl px-3 py-3 text-sm font-medium text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink"
                                @click="emit('close')"
                            >
                                Terms &amp; conditions
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.pages.returns')"
                                class="block rounded-xl px-3 py-3 text-sm font-medium text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink"
                                @click="emit('close')"
                            >
                                Return policy
                            </Link>
                        </li>
                    </ul>
                </nav>
            </aside>
        </Transition>
    </Teleport>
</template>

<style scoped>
.store-menu-backdrop-enter-active,
.store-menu-backdrop-leave-active {
    transition: opacity 0.2s ease;
}
.store-menu-backdrop-enter-from,
.store-menu-backdrop-leave-to {
    opacity: 0;
}
.store-menu-panel-enter-active,
.store-menu-panel-leave-active {
    transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}
.store-menu-panel-enter-from,
.store-menu-panel-leave-to {
    transform: translateX(-100%);
}
</style>
