<script setup>
import StoreBottomNav from '@/Components/Store/StoreBottomNav.vue';
import StoreHeaderNav from '@/Components/Store/StoreHeaderNav.vue';
import StoreMobileMenu from '@/Components/Store/StoreMobileMenu.vue';
import StoreThemeToggle from '@/Components/Store/StoreThemeToggle.vue';
import { SURFACE_TILES, useStoreCategoryHref } from '@/composables/useStoreCategoryHref';
import { useScrollDirection } from '@/composables/useScrollDirection';
import { useStoreAnalytics } from '@/composables/useStoreAnalytics';
import { useStoreWhatsApp } from '@/composables/useStoreWhatsApp';
import { applyStoreBranding } from '@/store/applyStoreBranding';
import { initStoreTheme } from '@/store/storeTheme';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, toRef, watch } from 'vue';

const page = usePage();
const navCategories = page.props.navCategories ?? [];
const storefront = computed(() => page.props.storefront ?? {});
const storeBranding = computed(() => page.props.storeBranding ?? null);
const flashPaymentError = computed(() => page.props.flashPaymentError);
const appName = computed(() => storeBranding.value?.site_name || page.props.appName || 'Store');
const hasLogo = computed(() => {
    const b = storeBranding.value;
    return !!(b?.logo_url || b?.logo_dark_url);
});
const cartCount = computed(() => Number(page.props.cartCount ?? 0));

const { orderSupportUrl } = useStoreWhatsApp();
const floatingWhatsAppUrl = computed(() => {
    const order = page.props.order;
    if (page.component === 'Store/Account/Orders/Show' && order?.order_number) {
        return orderSupportUrl(order, { includeItems: true });
    }
    return storefront.value.support_whatsapp_url ?? '#';
});

const menuOpen = ref(false);
const { headerHidden } = useScrollDirection();
const { categoryHref } = useStoreCategoryHref(toRef(() => navCategories));

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

const footerEmail = ref('');
const footerSubmitted = ref(false);

function onFooterNewsletter(e) {
    e.preventDefault();
    if (!footerEmail.value.trim()) return;
    footerSubmitted.value = true;
}

watch(storeBranding, (b) => applyStoreBranding(b), { immediate: true, deep: true });

function onInertiaNavigate() {
    menuOpen.value = false;
}

onMounted(() => {
    initStoreTheme();
    applyStoreBranding(storeBranding.value);
    bindBargainHelper();
    analytics.trackPageView();
    document.addEventListener('inertia:finish', onInertiaFinish);
    document.addEventListener('inertia:navigate', onInertiaNavigate);
});

onUnmounted(() => {
    document.removeEventListener('inertia:finish', onInertiaFinish);
    document.removeEventListener('inertia:navigate', onInertiaNavigate);
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
            class="sticky top-0 z-40 border-b border-stadium-outline-soft bg-stadium-white/95 shadow-sm backdrop-blur-md transition-transform duration-300 dark:border-white/10 dark:bg-stadium-inverse/95"
            :class="headerHidden ? '-translate-y-full md:translate-y-0' : 'translate-y-0'"
        >
            <div
                class="store-container flex items-center justify-between gap-3 py-3"
            >
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
                    <template v-if="hasLogo">
                        <img
                            v-if="storeBranding?.logo_url"
                            :src="storeBranding.logo_url"
                            :alt="appName"
                            width="140"
                            height="36"
                            fetchpriority="low"
                            class="h-8 w-auto max-w-[140px] object-contain object-left dark:hidden md:h-9"
                        />
                        <img
                            v-if="storeBranding?.logo_dark_url"
                            :src="storeBranding.logo_dark_url"
                            :alt="appName"
                            width="140"
                            height="36"
                            fetchpriority="low"
                            class="hidden h-8 w-auto max-w-[140px] object-contain object-left dark:block md:h-9"
                        />
                        <img
                            v-else-if="storeBranding?.logo_url"
                            :src="storeBranding.logo_url"
                            :alt="appName"
                            width="140"
                            height="36"
                            fetchpriority="low"
                            class="hidden h-8 w-auto max-w-[140px] object-contain object-left dark:block md:h-9"
                        />
                    </template>
                    <template v-else>
                        <span class="hidden text-2xl leading-none text-store-primary md:inline" aria-hidden="true">⚽</span>
                        <span class="font-display text-lg font-extrabold tracking-tighter text-stadium-ink md:text-xl">
                            {{ appName }}
                        </span>
                    </template>
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
                            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-stadium-lime px-1 text-[10px] font-bold leading-none text-stadium-lime-ink"
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
                        :href="route('store.account.dashboard')"
                        class="hidden text-sm font-medium text-stadium-secondary transition hover:text-stadium-ink sm:inline"
                    >
                        Account
                    </Link>
                </div>
            </div>
        </header>
        <main
            :key="page.component"
            class="pb-[calc(5rem+env(safe-area-inset-bottom))] sm:pb-0"
        >
            <slot />
        </main>
        <a
            v-if="floatingWhatsAppUrl && floatingWhatsAppUrl !== '#'"
            :href="floatingWhatsAppUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="store-sticky-above-nav fixed right-4 z-30 flex h-12 w-12 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg ring-2 ring-white/50 transition duration-200 hover:scale-105 hover:bg-[#20BD5A] active:scale-95 sm:bottom-8 sm:h-14 sm:w-14"
            aria-label="Chat on WhatsApp"
        >
            <svg class="h-7 w-7 sm:h-8 sm:w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path
                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                />
            </svg>
        </a>
        <StoreBottomNav />
        <footer class="mt-12 border-t-4 border-stadium-lime bg-stadium-inverse py-12 text-stadium-inverse-text">
            <div class="store-container grid gap-10 lg:grid-cols-12 lg:gap-8">
                <div class="lg:col-span-4">
                    <Link :href="route('store.home')" class="inline-flex items-center gap-2 font-display text-xl font-extrabold tracking-tighter text-white">
                        <span class="text-2xl leading-none text-stadium-lime" aria-hidden="true">⚽</span>
                        {{ appName }}
                    </Link>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-stadium-inverse-text/80">
                        Original used football boots for Pakistani players — inspected condition, UK/EU sizing, COD nationwide.
                    </p>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-stadium-lime">
                        COD · JazzCash · Easypaisa
                    </p>
                </div>
                <div class="lg:col-span-2">
                    <p class="text-label text-stadium-lime">Shop</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li>
                            <Link :href="route('store.category', 'football-shoes')" class="hover:text-stadium-lime">Football shoes</Link>
                        </li>
                        <li>
                            <Link :href="route('store.category', 'football-cleats')" class="hover:text-stadium-lime">Cleats</Link>
                        </li>
                        <li>
                            <Link :href="route('store.category', 'grippers')" class="hover:text-stadium-lime">Grippers</Link>
                        </li>
                        <li>
                            <Link :href="route('store.category', 'football-socks')" class="hover:text-stadium-lime">Football socks</Link>
                        </li>
                        <li>
                            <Link :href="route('store.category', 'accessories')" class="hover:text-stadium-lime">Accessories</Link>
                        </li>
                        <li v-for="tile in SURFACE_TILES" :key="tile.short">
                            <Link
                                :href="categoryHref(tile.fragments)"
                                class="hover:text-stadium-lime"
                            >
                                {{ tile.title }}
                            </Link>
                        </li>
                        <li>
                            <Link :href="route('store.shop')" class="hover:text-stadium-lime">All boots</Link>
                        </li>
                    </ul>
                </div>
                <div class="lg:col-span-2">
                    <p class="text-label text-stadium-lime">Support</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li>
                            <Link :href="route('store.pages.about')" class="hover:text-stadium-lime">About us</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.faq')" class="hover:text-stadium-lime">FAQ</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.contact')" class="hover:text-stadium-lime">Contact</Link>
                        </li>
                        <li>
                            <Link :href="route('store.order-tracking')" class="hover:text-stadium-lime">Track order</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.returns')" class="hover:text-stadium-lime">Returns</Link>
                        </li>
                        <li>
                            <Link :href="route('store.journal.index')" class="hover:text-stadium-lime">Size guides</Link>
                        </li>
                        <li>
                            <a
                                v-if="storefront.support_whatsapp_url && storefront.support_whatsapp_url !== '#'"
                                :href="storefront.support_whatsapp_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="hover:text-stadium-lime"
                            >
                                WhatsApp sizing
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="lg:col-span-2">
                    <p class="text-label text-stadium-lime">Legal</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li>
                            <Link :href="route('store.pages.payment')" class="hover:text-stadium-lime">Payment policy</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.shipping')" class="hover:text-stadium-lime">Shipping policy</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.privacy')" class="hover:text-stadium-lime">Privacy</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.terms')" class="hover:text-stadium-lime">Terms</Link>
                        </li>
                        <li>
                            <Link :href="route('store.pages.disclaimer')" class="hover:text-stadium-lime">Disclaimer</Link>
                        </li>
                        <li>
                            <Link :href="route('store.cart')" class="hover:text-stadium-lime">Cart</Link>
                        </li>
                        <li>
                            <Link v-if="!$page.props.auth.user" :href="route('login')" class="hover:text-stadium-lime">
                                Log in
                            </Link>
                            <Link v-else :href="route('store.account.dashboard')" class="hover:text-stadium-lime">Account</Link>
                        </li>
                    </ul>
                </div>
                <div class="lg:col-span-2">
                    <p class="text-label text-stadium-lime">Join the squad</p>
                    <p class="mt-2 text-xs text-stadium-inverse-text/70">Drop alerts & early access.</p>
                    <form class="mt-3 flex flex-col gap-2" @submit="onFooterNewsletter">
                        <label class="sr-only" for="footer-email">Email</label>
                        <input
                            id="footer-email"
                            v-model="footerEmail"
                            type="email"
                            required
                            placeholder="Email"
                            class="min-h-10 rounded-lg border border-white/10 bg-white/10 px-3 text-sm text-white placeholder:text-white/40 focus:border-stadium-lime focus:outline-none"
                        />
                        <button
                            type="submit"
                            class="min-h-10 rounded-lg bg-stadium-lime text-xs font-bold uppercase text-stadium-lime-ink"
                        >
                            {{ footerSubmitted ? 'Thanks!' : 'Subscribe' }}
                        </button>
                    </form>
                </div>
            </div>
            <div class="store-container mt-10 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-8 text-xs text-stadium-inverse-text/60 md:flex-row">
                <p>© {{ new Date().getFullYear() }} {{ appName }} · Pakistan</p>
                <p>Football boots · Cleats · FG · SG · AG · Turf</p>
            </div>
        </footer>
    </div>
</template>
