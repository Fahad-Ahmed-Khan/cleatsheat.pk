<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineProps({
    categories: { type: Array, default: () => [] },
});

const page = usePage();
const pagesOpen = ref(false);
const pagesMenuRef = ref(null);

const currentPath = computed(() => {
    const url = page.url || '';
    return (url.split('?')[0] || '').split('#')[0] || '/';
});

const pageLinks = [
    { label: 'Privacy Policy', href: 'store.pages.privacy' },
    { label: 'Terms & Conditions', href: 'store.pages.terms' },
    { label: 'Return Policy', href: 'store.pages.returns' },
];

function navClass(active) {
    return [
        'font-display text-xs font-bold uppercase tracking-[0.08em] transition',
        active ? 'text-stadium-olive' : 'text-stadium-ink hover:text-stadium-olive',
    ];
}

function isActive(match) {
    const path = currentPath.value;
    if (typeof match === 'function') {
        return match(path);
    }
    if (Array.isArray(match)) {
        return match.some((m) => isActive(m));
    }
    return path === match;
}

function onDocumentClick(e) {
    if (!pagesOpen.value || !pagesMenuRef.value) {
        return;
    }
    if (!pagesMenuRef.value.contains(e.target)) {
        pagesOpen.value = false;
    }
}

function onEscape(e) {
    if (e.key === 'Escape') {
        pagesOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <nav class="hidden flex-1 items-center justify-center gap-5 lg:gap-6 md:flex" aria-label="Main">
        <Link :href="route('store.home')" :class="navClass(isActive('/'))">
            Home
        </Link>
        <Link
            :href="route('store.shop')"
            :class="navClass(isActive((p) => p === '/shop' || p.startsWith('/c/') || p.startsWith('/p/')))"
        >
            Shop
        </Link>
        <template v-for="c in categories" :key="c.id">
            <Link
                v-if="c.slug"
                :href="route('store.category', c.slug)"
                :class="navClass(isActive(`/c/${c.slug}`))"
            >
                {{ c.name }}
            </Link>
        </template>
        <Link
            :href="route('store.order-tracking')"
            :class="navClass(isActive((p) => p.startsWith('/track-order')))"
        >
            Track Order
        </Link>
        <Link
            :href="route('store.journal.index')"
            :class="navClass(isActive((p) => p.startsWith('/journal')))"
        >
            Blog
        </Link>
        <div ref="pagesMenuRef" class="relative">
            <button
                type="button"
                class="inline-flex items-center gap-1 font-display text-xs font-bold uppercase tracking-[0.08em] transition"
                :class="
                    isActive(['/privacy-policy', '/terms-and-conditions', '/return-policy'])
                        ? 'text-stadium-olive'
                        : 'text-stadium-ink hover:text-stadium-olive'
                "
                :aria-expanded="pagesOpen"
                aria-haspopup="true"
                @click.stop="pagesOpen = !pagesOpen"
            >
                Pages
                <svg
                    class="h-3.5 w-3.5 transition"
                    :class="pagesOpen ? 'rotate-180' : ''"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    aria-hidden="true"
                >
                    <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <ul
                    v-if="pagesOpen"
                    class="absolute left-1/2 top-full z-50 mt-2 w-52 -translate-x-1/2 overflow-hidden rounded-xl border border-stadium-outline-soft bg-stadium-white py-1 shadow-stadium-lg"
                    role="menu"
                >
                    <li v-for="item in pageLinks" :key="item.href" role="none">
                        <Link
                            :href="route(item.href)"
                            role="menuitem"
                            class="block px-4 py-2.5 text-sm font-medium text-stadium-ink transition hover:bg-stadium-muted hover:text-stadium-olive"
                            @click="pagesOpen = false"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </Transition>
        </div>
    </nav>
</template>
