<script setup>
import { useNavCategories } from '@/composables/useNavCategories';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, toRef } from 'vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const { parents: navParents } = useNavCategories(toRef(props, 'categories'));

const page = usePage();
const pagesOpen = ref(false);
const openCategoryId = ref(null);
const navMenusRef = ref(null);

const currentPath = computed(() => {
    const url = page.url || '';
    return (url.split('?')[0] || '').split('#')[0] || '/';
});

const pageLinks = [
    { label: 'About Us', href: 'store.pages.about' },
    { label: 'FAQ', href: 'store.pages.faq' },
    { label: 'Contact', href: 'store.pages.contact' },
    { label: 'Payment Policy', href: 'store.pages.payment' },
    { label: 'Shipping Policy', href: 'store.pages.shipping' },
    { label: 'Return Policy', href: 'store.pages.returns' },
    { label: 'Privacy Policy', href: 'store.pages.privacy' },
    { label: 'Terms & Conditions', href: 'store.pages.terms' },
    { label: 'Disclaimer', href: 'store.pages.disclaimer' },
];

function navClass(active) {
    return [
        'font-display text-xs font-bold uppercase tracking-[0.08em] transition',
        active
            ? 'text-stadium-olive dark:text-stadium-lime'
            : 'text-stadium-ink hover:text-stadium-olive dark:text-stadium-inverse-text/90 dark:hover:text-stadium-lime',
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

function isCategoryBranchActive(parent) {
    const path = currentPath.value;
    if (parent.slug && path === `/c/${parent.slug}`) {
        return true;
    }
    return (parent.children ?? []).some((ch) => ch.slug && path === `/c/${ch.slug}`);
}

function toggleCategory(id) {
    openCategoryId.value = openCategoryId.value === id ? null : id;
    pagesOpen.value = false;
}

function togglePages() {
    pagesOpen.value = !pagesOpen.value;
    openCategoryId.value = null;
}

function closeMenus() {
    pagesOpen.value = false;
    openCategoryId.value = null;
}

function onDocumentClick(e) {
    if (!navMenusRef.value?.contains(e.target)) {
        closeMenus();
    }
}

function onEscape(e) {
    if (e.key === 'Escape') {
        closeMenus();
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
    <nav ref="navMenusRef" class="hidden flex-1 items-center justify-center gap-5 lg:gap-6 md:flex" aria-label="Main">
        <Link :href="route('store.home')" :class="navClass(isActive('/'))">
            Home
        </Link>
        <a
            :href="route('store.shop')"
            :class="navClass(isActive((p) => p === '/shop' || p.startsWith('/c/') || p.startsWith('/p/')))"
        >
            Shop
        </a>

        <template v-for="parent in navParents" :key="parent.id">
            <div v-if="parent.children.length" class="relative">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 font-display text-xs font-bold uppercase tracking-[0.08em] transition"
                    :class="
                        isCategoryBranchActive(parent)
                            ? 'text-stadium-olive dark:text-stadium-lime'
                            : 'text-stadium-ink hover:text-stadium-olive dark:text-stadium-inverse-text/90 dark:hover:text-stadium-lime'
                    "
                    :aria-expanded="openCategoryId === parent.id"
                    aria-haspopup="true"
                    @click.stop="toggleCategory(parent.id)"
                >
                    {{ parent.name }}
                    <svg
                        class="h-3.5 w-3.5 transition"
                        :class="openCategoryId === parent.id ? 'rotate-180' : ''"
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
                        v-if="openCategoryId === parent.id"
                        class="absolute left-1/2 top-full z-50 mt-2 min-w-[12rem] -translate-x-1/2 overflow-hidden rounded-xl border border-stadium-outline-soft bg-stadium-white py-1 shadow-stadium-lg dark:border-white/10 dark:bg-stadium-container"
                        role="menu"
                    >
                        <li role="none">
                            <Link
                                :href="route('store.category', parent.slug)"
                                role="menuitem"
                                class="block border-b border-stadium-outline-soft/30 px-4 py-2.5 text-sm font-bold text-stadium-ink transition hover:bg-stadium-muted hover:text-stadium-olive dark:border-white/10 dark:text-stadium-inverse-text dark:hover:bg-stadium-dim dark:hover:text-stadium-lime"
                                @click="closeMenus"
                            >
                                All {{ parent.name }}
                            </Link>
                        </li>
                        <li v-for="child in parent.children" :key="child.id" role="none">
                            <Link
                                :href="route('store.category', child.slug)"
                                role="menuitem"
                                class="block px-4 py-2.5 text-sm font-medium text-stadium-secondary transition hover:bg-stadium-muted hover:text-stadium-ink dark:text-stadium-inverse-text/85 dark:hover:bg-stadium-dim dark:hover:text-stadium-lime"
                                :class="isActive(`/c/${child.slug}`) ? 'font-semibold text-stadium-olive dark:text-stadium-lime' : ''"
                                @click="closeMenus"
                            >
                                {{ child.name }}
                            </Link>
                        </li>
                    </ul>
                </Transition>
            </div>
            <Link
                v-else
                :href="route('store.category', parent.slug)"
                :class="navClass(isActive(`/c/${parent.slug}`))"
            >
                {{ parent.name }}
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
        <div class="relative">
            <button
                type="button"
                class="inline-flex items-center gap-1 font-display text-xs font-bold uppercase tracking-[0.08em] transition"
                :class="
                    isActive((p) =>
                        [
                            '/about',
                            '/faq',
                            '/contact',
                            '/payment-policy',
                            '/shipping-policy',
                            '/privacy-policy',
                            '/terms-and-conditions',
                            '/return-policy',
                            '/disclaimer',
                        ].includes(p),
                    )
                        ? 'text-stadium-olive dark:text-stadium-lime'
                        : 'text-stadium-ink hover:text-stadium-olive dark:text-stadium-inverse-text/90 dark:hover:text-stadium-lime'
                "
                :aria-expanded="pagesOpen"
                aria-haspopup="true"
                @click.stop="togglePages"
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
                    class="absolute left-1/2 top-full z-50 mt-2 w-52 -translate-x-1/2 overflow-hidden rounded-xl border border-stadium-outline-soft bg-stadium-white py-1 shadow-stadium-lg dark:border-white/10 dark:bg-stadium-container"
                    role="menu"
                >
                    <li v-for="item in pageLinks" :key="item.href" role="none">
                        <Link
                            :href="route(item.href)"
                            role="menuitem"
                            class="block px-4 py-2.5 text-sm font-medium text-stadium-ink transition hover:bg-stadium-muted hover:text-stadium-olive dark:text-stadium-inverse-text dark:hover:bg-stadium-dim dark:hover:text-stadium-lime"
                            @click="closeMenus"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </Transition>
        </div>
    </nav>
</template>
