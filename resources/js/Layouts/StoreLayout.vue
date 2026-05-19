<script setup>
import StoreBottomNav from '@/Components/Store/StoreBottomNav.vue';
import StoreHeaderNav from '@/Components/Store/StoreHeaderNav.vue';
import StoreMobileMenu from '@/Components/Store/StoreMobileMenu.vue';
import StoreThemeToggle from '@/Components/Store/StoreThemeToggle.vue';
import StorefrontAssistant from '@/Components/Store/StorefrontAssistant.vue';
import { useStoreAnalytics } from '@/composables/useStoreAnalytics';
import { initStoreTheme } from '@/store/storeTheme';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const navCategories = page.props.navCategories ?? [];
const storefront = computed(() => page.props.storefront ?? {});
const flashPaymentError = computed(() => page.props.flashPaymentError);
const appName = computed(() => page.props.appName ?? 'Store');
const cartCount = computed(() => Number(page.props.cartCount ?? 0));

const menuOpen = ref(false);

const analytics = useStoreAnalytics();

let finishCount = 0;

function bindBargainHelper() {
    window.tryinoTrack = {
        bargainAccepted: (p) => analytics.trackBargainAccepted(p),
    };
}

function onInertiaFinish() {
    bindBargainHelper();
    finishCount += 1;
    if (finishCount > 1) {
        analytics.trackPageView();
    }
}

onMounted(() => {
    initStoreTheme();
    bindBargainHelper();
    analytics.trackPageView();
    document.addEventListener('inertia:finish', onInertiaFinish);
});

onUnmounted(() => {
    document.removeEventListener('inertia:finish', onInertiaFinish);
    delete window.tryinoTrack;
});
</script>

<template>
    <div class="min-h-dvh bg-stadium font-store text-stadium-ink antialiased">
        <p
            v-if="flashPaymentError"
            class="border-b border-red-200 bg-red-50 px-4 py-3 text-center text-sm text-red-800"
            role="alert"
        >
            {{ flashPaymentError }}
        </p>
        <StoreMobileMenu :open="menuOpen" :categories="navCategories" @close="menuOpen = false" />
        <header
            class="sticky top-0 z-40 border-b border-stadium-outline-soft bg-stadium-white shadow-sm"
        >
            <div
                class="mx-auto flex max-w-content items-center justify-between gap-3 px-4 py-3 sm:px-6"
            >
                <!-- Mobile: menu + brand + search -->
                <button
                    type="button"
                    class="flex items-center justify-center rounded-xl p-2 text-stadium-olive transition hover:bg-stadium-muted active:scale-95 md:hidden"
                    aria-label="Open menu"
                    @click="menuOpen = true"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
                    </svg>
                </button>

                <Link
                    :href="route('store.home')"
                    class="flex flex-1 items-center justify-center gap-2 md:flex-none md:justify-start"
                >
                    <span class="hidden text-2xl leading-none text-stadium-lime md:inline" aria-hidden="true">⚽</span>
                    <span class="font-display text-lg font-extrabold tracking-tighter text-stadium-ink md:text-xl">
                        {{ appName }}
                    </span>
                </Link>

                <StoreHeaderNav :categories="navCategories" />

                <div class="flex items-center gap-1 text-stadium-ink-variant sm:gap-2">
                    <StoreThemeToggle />
                    <Link
                        :href="route('store.shop')"
                        class="flex items-center justify-center rounded-xl p-2 transition hover:bg-stadium-muted active:scale-95"
                        aria-label="Search catalogue"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M20 20l-3-3" stroke-linecap="round" />
                        </svg>
                    </Link>
                    <Link
                        :href="route('store.cart')"
                        class="relative flex items-center justify-center rounded-xl p-2 transition hover:bg-stadium-muted active:scale-95"
                        aria-label="Shopping bag"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 7h15l-1.5 11h-12L6 7z" stroke-linejoin="round" />
                            <path d="M9 7V5a3 3 0 116 0v2" stroke-linecap="round" />
                        </svg>
                        <span
                            v-if="cartCount > 0"
                            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-stadium-lime px-1 text-[10px] font-bold leading-none text-stadium-ink"
                        >
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </Link>
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="route('login')"
                        class="hidden text-sm font-medium text-stadium-secondary transition hover:text-stadium-ink sm:inline"
                    >
                        Log in
                    </Link>
                    <Link
                        v-else
                        :href="route('dashboard')"
                        class="hidden text-sm font-medium text-stadium-secondary transition hover:text-stadium-ink sm:inline"
                    >
                        Account
                    </Link>
                </div>
            </div>
            <div
                class="no-scrollbar flex gap-2 overflow-x-auto border-t border-stadium-outline-soft bg-stadium-muted px-4 py-2.5 md:hidden"
            >
                <Link
                    :href="route('store.home')"
                    class="shrink-0 rounded-xl border border-stadium-outline-soft/50 bg-stadium-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-stadium-ink shadow-sm"
                >
                    Home
                </Link>
                <Link
                    :href="route('store.shop')"
                    class="shrink-0 rounded-xl border border-stadium-outline-soft/50 bg-stadium-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-stadium-ink shadow-sm"
                >
                    Shop
                </Link>
                <template v-for="c in navCategories" :key="c.id">
                    <Link
                        v-if="c.slug"
                        :href="route('store.category', c.slug)"
                        class="shrink-0 rounded-xl border border-stadium-outline-soft/50 bg-stadium-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-stadium-ink shadow-sm"
                    >
                        {{ c.name }}
                    </Link>
                </template>
                <Link
                    :href="route('store.order-tracking')"
                    class="shrink-0 rounded-xl border border-stadium-outline-soft/50 bg-stadium-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-stadium-ink shadow-sm"
                >
                    Track
                </Link>
                <Link
                    :href="route('store.journal.index')"
                    class="shrink-0 rounded-xl border border-stadium-outline-soft/50 bg-stadium-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-stadium-ink shadow-sm"
                >
                    Blog
                </Link>
            </div>
        </header>
        <main class="pb-[calc(5rem+env(safe-area-inset-bottom))] sm:pb-0">
            <slot />
        </main>
        <a
            v-if="storefront.support_whatsapp_url && storefront.support_whatsapp_url !== '#'"
            :href="storefront.support_whatsapp_url"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-36 right-4 z-30 flex h-12 w-12 items-center justify-center rounded-full bg-stadium-olive text-xl text-white shadow-lg ring-2 ring-white/40 transition duration-200 hover:scale-105 hover:bg-stadium-ink sm:bottom-8 sm:h-14 sm:w-14 sm:text-2xl"
            aria-label="Chat on WhatsApp"
        >
            <span aria-hidden="true">💬</span>
        </a>
        <StorefrontAssistant />
        <StoreBottomNav />
        <footer class="mt-12 border-t-4 border-stadium-lime bg-stadium-inverse px-4 py-12 text-stadium-inverse-text sm:px-6">
            <div class="mx-auto grid max-w-content gap-10 md:grid-cols-4 md:gap-12">
                <div class="md:col-span-2">
                    <Link :href="route('store.home')" class="inline-flex items-center gap-2 font-display text-xl font-extrabold tracking-tighter text-white">
                        <span class="text-2xl leading-none text-stadium-lime" aria-hidden="true">⚽</span>
                        {{ appName }}
                    </Link>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-stadium-inverse-text/80">
                        Premium football boots and footwear — fast nationwide delivery, trusted checkout.
                    </p>
                </div>
                <div>
                    <p class="font-display text-xs font-bold uppercase tracking-widest text-stadium-lime">
                        Shop
                    </p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li>
                            <Link :href="route('store.shop')" class="hover:text-stadium-lime">All products</Link>
                        </li>
                        <li>
                            <Link :href="route('store.journal.index')" class="hover:text-stadium-lime">Blog</Link>
                        </li>
                        <li>
                            <Link :href="route('store.order-tracking')" class="hover:text-stadium-lime">Track order</Link>
                        </li>
                    </ul>
                </div>
                <div>
                    <p class="font-display text-xs font-bold uppercase tracking-widest text-stadium-lime">
                        Information
                    </p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li>
                            <Link :href="route('store.pages.privacy')" class="hover:text-stadium-lime">Privacy policy</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.terms')" class="hover:text-stadium-lime">Terms &amp; conditions</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.returns')" class="hover:text-stadium-lime">Return policy</Link>
                        </li>
                    </ul>
                </div>
                <div>
                    <p class="font-display text-xs font-bold uppercase tracking-widest text-stadium-lime">
                        Account
                    </p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li>
                            <Link v-if="!$page.props.auth.user" :href="route('login')" class="hover:text-stadium-lime">
                                Log in
                            </Link>
                            <Link v-else :href="route('dashboard')" class="hover:text-stadium-lime">
                                Dashboard
                            </Link>
                        </li>
                        <li>
                            <Link :href="route('store.cart')" class="hover:text-stadium-lime">Cart</Link>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="mx-auto mt-10 max-w-content border-t border-white/10 pt-8 text-center text-xs text-stadium-inverse-text/60">
                © {{ new Date().getFullYear() }} {{ appName }} · Pakistan
            </div>
        </footer>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
