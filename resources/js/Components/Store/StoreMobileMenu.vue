<script setup>
import { useNavCategories } from '@/composables/useNavCategories';
import { Link } from '@inertiajs/vue3';
import { ref, toRef } from 'vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const { parents: navParents } = useNavCategories(toRef(props, 'categories'));
const expandedParentId = ref(null);

function toggleParent(id) {
    expandedParentId.value = expandedParentId.value === id ? null : id;
}

function closeMenu() {
    expandedParentId.value = null;
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition name="store-menu-backdrop">
            <div
                v-if="open"
                class="fixed inset-0 z-[70] bg-stadium-ink/40 backdrop-blur-sm md:hidden"
                aria-hidden="true"
                @click="closeMenu"
            />
        </Transition>
        <Transition name="store-menu-panel">
            <aside
                v-if="open"
                class="fixed inset-y-0 left-0 z-[71] flex w-[min(100vw-3rem,20rem)] flex-col bg-stadium-white shadow-stadium-lg dark:bg-stadium-container md:hidden"
                role="dialog"
                aria-modal="true"
                aria-labelledby="store-mobile-menu-title"
            >
                <div class="flex items-center justify-between border-b border-stadium-outline-soft/40 px-4 py-4 dark:border-white/10">
                    <h2 id="store-mobile-menu-title" class="font-display text-lg font-extrabold tracking-tighter text-stadium-ink">
                        Menu
                    </h2>
                    <button
                        type="button"
                        class="rounded-xl p-2 text-stadium-olive transition hover:bg-stadium-muted active:scale-95 dark:text-stadium-lime"
                        aria-label="Close menu"
                        @click="closeMenu"
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
                                @click="closeMenu"
                            >
                                Home
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.shop')"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="closeMenu"
                            >
                                Shop all
                            </Link>
                        </li>

                        <li v-for="parent in navParents" :key="parent.id">
                            <template v-if="parent.children.length">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                    :aria-expanded="expandedParentId === parent.id"
                                    @click="toggleParent(parent.id)"
                                >
                                    {{ parent.name }}
                                    <svg
                                        class="h-4 w-4 shrink-0 text-stadium-olive transition dark:text-stadium-lime"
                                        :class="expandedParentId === parent.id ? 'rotate-180' : ''"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path d="M6 9l6 6 6-6" stroke-linecap="round" />
                                    </svg>
                                </button>
                                <ul
                                    v-show="expandedParentId === parent.id"
                                    class="mb-1 ml-2 space-y-0.5 border-l-2 border-stadium-lime/50 pl-2 dark:border-stadium-lime/40"
                                >
                                    <li>
                                        <Link
                                            :href="route('store.category', parent.slug)"
                                            class="block rounded-lg px-3 py-2 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                            @click="closeMenu"
                                        >
                                            All {{ parent.name }}
                                        </Link>
                                    </li>
                                    <li v-for="child in parent.children" :key="child.id">
                                        <Link
                                            :href="route('store.category', child.slug)"
                                            class="block rounded-lg px-3 py-2 text-sm font-medium text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink"
                                            @click="closeMenu"
                                        >
                                            {{ child.name }}
                                        </Link>
                                    </li>
                                </ul>
                            </template>
                            <Link
                                v-else
                                :href="route('store.category', parent.slug)"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="closeMenu"
                            >
                                {{ parent.name }}
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
                                @click="closeMenu"
                            >
                                Cart
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.order-tracking')"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="closeMenu"
                            >
                                Track order
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.journal.index')"
                                class="block rounded-xl px-3 py-3 text-sm font-semibold text-stadium-ink transition hover:bg-stadium-muted"
                                @click="closeMenu"
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
                                @click="closeMenu"
                            >
                                Privacy policy
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.pages.terms')"
                                class="block rounded-xl px-3 py-3 text-sm font-medium text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink"
                                @click="closeMenu"
                            >
                                Terms &amp; conditions
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="route('store.pages.returns')"
                                class="block rounded-xl px-3 py-3 text-sm font-medium text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink"
                                @click="closeMenu"
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
