<script setup>

import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';

import StoreTrustStrip from '@/Components/Store/StoreTrustStrip.vue';

import StoreLayout from '@/Layouts/StoreLayout.vue';

import { useStoreAnalytics } from '@/composables/useStoreAnalytics';

import { useForm, usePage } from '@inertiajs/vue3';

import { computed, onMounted, ref, watch } from 'vue';



const page = usePage();



const props = defineProps({

    seo: {

        type: Object,

        required: true,

    },

    analytics_checkout: {

        type: Object,

        default: () => ({}),

    },

    gateways: {

        type: Array,

        default: () => [],

    },

    savedAddresses: {

        type: Array,

        default: () => [],

    },

});



const analytics = useStoreAnalytics();



const defaultGateway = props.gateways[0]?.code ?? 'cod';



const selectedAddressId = ref('');

const form = useForm({

    full_name: '',

    phone: '',

    line1: '',

    city: '',

    area: '',

    postal_code: '',

    notes: '',

    guest_email: '',

    payment_gateway: defaultGateway,

});

const cityQuery = ref('');
const citySuggestions = ref([]);
const cityOpen = ref(false);
const cityLoading = ref(false);
let cityTimer = null;

function syncCityQueryFromForm() {
    cityQuery.value = form.city || '';
}

syncCityQueryFromForm();

watch(
    () => form.city,
    () => {
        // keep query synced when saved address is applied
        syncCityQueryFromForm();
    },
);

const canSuggestCities = computed(() => (cityQuery.value || '').trim().length >= 1);

async function fetchCitySuggestions() {
    if (!canSuggestCities.value) {
        citySuggestions.value = [];
        return;
    }

    cityLoading.value = true;
    try {
        const q = encodeURIComponent(cityQuery.value.trim());
        const res = await fetch(route('store.checkout.cities', { q }), {
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        const rows = Array.isArray(json?.cities) ? json.cities : [];
        citySuggestions.value = rows.slice(0, 25);
    } catch (e) {
        citySuggestions.value = [];
    } finally {
        cityLoading.value = false;
    }
}

function scheduleCitySuggest() {
    cityOpen.value = true;
    if (cityTimer) clearTimeout(cityTimer);
    cityTimer = setTimeout(fetchCitySuggestions, 200);
}

function pickCity(name) {
    form.city = name;
    cityQuery.value = name;
    cityOpen.value = false;
    citySuggestions.value = [];
}

function closeCityDropdownSoon() {
    // allow click selection to register before closing
    setTimeout(() => {
        cityOpen.value = false;
    }, 150);
}

onMounted(() => {

    if (props.analytics_checkout?.items?.length) {

        analytics.trackBeginCheckout(props.analytics_checkout);

    }

    const defaultAddr = props.savedAddresses.find((a) => a.is_default) ?? props.savedAddresses[0];

    if (defaultAddr) {

        applyAddress(defaultAddr.id);

    } else if (page.props.auth?.user) {

        const u = page.props.auth.user;

        if (!form.full_name && u.name) form.full_name = u.name;

        if (!form.phone && u.phone) form.phone = u.phone;

    }

});

function applyAddress(id) {

    const addr = props.savedAddresses.find((a) => String(a.id) === String(id));

    if (!addr) return;

    selectedAddressId.value = String(addr.id);

    form.full_name = addr.full_name;

    form.phone = addr.phone;

    form.line1 = addr.line1;

    form.city = addr.city;

    form.area = addr.area ?? '';

    form.postal_code = addr.postal_code ?? '';

    syncCityQueryFromForm();

}

function submit() {

    form.post(route('store.checkout.store'));

}



function feeHint(g) {

    const parts = [];

    if (g.fee_fixed > 0) {

        parts.push(`PKR ${g.fee_fixed} fixed`);

    }

    if (g.fee_percent > 0) {

        parts.push(`${g.fee_percent}%`);

    }

    if (parts.length === 0) {

        return null;

    }

    return `Fee: ${parts.join(' + ')} (applied before COD surcharge)`;

}



const labelClass = 'block text-label text-stadium-secondary';

const inputClass =

    'mt-2 w-full min-h-14 rounded-2xl border border-stadium-outline-soft bg-stadium-white px-4 text-base text-stadium-ink shadow-sm placeholder:text-stadium-secondary focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/20';

</script>



<template>

    <StoreSeoHead :seo="seo" />

    <StoreLayout>

        <div class="mx-auto max-w-lg px-4 pb-44 pt-8 sm:max-w-xl sm:pb-16">

            <h1 class="text-display-md text-stadium-ink">Checkout</h1>

            <p class="mt-2 text-body-lg text-stadium-secondary">

                One-handed friendly fields. Your details stay on our encrypted session until the order is placed.

            </p>



            <StoreTrustStrip compact class="mt-8" />



            <form class="mt-10 space-y-5" @submit.prevent="submit">

                <div v-if="savedAddresses.length">

                    <label :class="labelClass">Saved address</label>

                    <select

                        v-model="selectedAddressId"

                        :class="inputClass"

                        @change="applyAddress(selectedAddressId)"

                    >

                        <option value="">Enter details manually</option>

                        <option v-for="addr in savedAddresses" :key="addr.id" :value="String(addr.id)">

                            {{ addr.full_name }} — {{ addr.city }}{{ addr.is_default ? ' (default)' : '' }}

                        </option>

                    </select>

                </div>

                <div>

                    <label :class="labelClass">Full name</label>

                    <input

                        v-model="form.full_name"

                        type="text"

                        required

                        autocomplete="name"

                        :class="inputClass"

                    >

                </div>

                <div>

                    <label :class="labelClass">Phone (WhatsApp)</label>

                    <input

                        v-model="form.phone"

                        type="tel"

                        required

                        autocomplete="tel"

                        inputmode="tel"

                        :class="inputClass"

                    >

                </div>

                <div v-if="!$page.props.auth.user">

                    <p class="mb-4 text-sm text-stadium-secondary">
                        Have an account?
                        <a :href="route('login')" class="font-semibold text-store-primary underline">Sign in</a>
                        or
                        <a :href="route('register')" class="font-semibold text-store-primary underline">register</a>
                        for saved addresses and order history.
                    </p>

                    <label :class="labelClass">Email</label>

                    <input

                        v-model="form.guest_email"

                        type="email"

                        required

                        autocomplete="email"

                        inputmode="email"

                        :class="inputClass"

                    >

                </div>

                <div>

                    <label :class="labelClass">Street address</label>

                    <input

                        v-model="form.line1"

                        type="text"

                        required

                        autocomplete="street-address"

                        :class="inputClass"

                    >

                </div>

                <div class="grid grid-cols-2 gap-3">

                    <div>

                        <label :class="labelClass">City</label>

                        <div class="relative">
                            <input
                                v-model="cityQuery"
                                type="text"
                                required
                                autocomplete="address-level2"
                                :class="inputClass"
                                placeholder="Start typing…"
                                @input="() => { form.city = cityQuery; scheduleCitySuggest(); }"
                                @focus="scheduleCitySuggest"
                                @blur="closeCityDropdownSoon"
                            >

                            <div
                                v-if="cityOpen && (cityLoading || citySuggestions.length)"
                                class="absolute z-20 mt-2 w-full overflow-hidden rounded-2xl border border-stadium-outline-soft bg-stadium-white shadow-lg"
                            >
                                <div v-if="cityLoading" class="px-4 py-3 text-sm text-stadium-ink/70">Searching…</div>
                                <button
                                    v-for="name in citySuggestions"
                                    :key="name"
                                    type="button"
                                    class="block w-full px-4 py-3 text-left text-sm hover:bg-stadium-surface"
                                    @mousedown.prevent="pickCity(name)"
                                >
                                    {{ name }}
                                </button>
                            </div>
                        </div>

                    </div>

                    <div>

                        <label :class="labelClass">Area</label>

                        <input v-model="form.area" type="text" :class="inputClass">

                    </div>

                </div>

                <div>

                    <label :class="labelClass">Postal code</label>

                    <input

                        v-model="form.postal_code"

                        type="text"

                        autocomplete="postal-code"

                        :class="inputClass"

                    >

                </div>

                <div>

                    <label :class="labelClass">Order notes</label>

                    <textarea

                        v-model="form.notes"

                        rows="3"

                        :class="inputClass + ' min-h-0 py-3'"

                    />

                </div>



                <fieldset>

                    <legend :class="labelClass">Payment</legend>

                    <div class="mt-3 space-y-2">

                        <label

                            v-for="g in gateways"

                            :key="g.code"

                            class="flex min-h-14 cursor-pointer flex-col gap-1 rounded-2xl border border-stadium-outline-soft bg-stadium-white px-4 py-3 text-sm font-medium text-stadium-ink transition"

                            :class="

                                form.payment_gateway === g.code

                                    ? 'ring-2 ring-store-primary border-store-primary/40'

                                    : 'hover:border-stadium-outline'

                            "

                        >

                            <div class="flex w-full items-center gap-4">

                                <input

                                    v-model="form.payment_gateway"

                                    type="radio"

                                    name="payment_gateway"

                                    :value="g.code"

                                    class="h-5 w-5 shrink-0 border-stadium-outline-soft text-store-primary focus:ring-store-primary/30"

                                >

                                <span>{{ g.label }}</span>

                            </div>

                            <p v-if="feeHint(g)" class="pl-9 text-xs font-normal text-stadium-secondary">

                                {{ feeHint(g) }}

                            </p>

                        </label>

                    </div>

                </fieldset>



                <p v-if="page.props.errors?.checkout" class="text-sm text-red-600 dark:text-red-400">

                    {{ page.props.errors.checkout }}

                </p>



                <button

                    type="submit"

                    class="hidden w-full min-h-14 rounded-2xl bg-stadium-lime text-base font-bold text-stadium-lime-ink shadow-lg transition hover:bg-stadium-lime/90 active:scale-[0.99] disabled:opacity-50 sm:block"

                    :disabled="form.processing"

                >

                    {{ form.processing ? 'Placing order…' : 'Place order' }}

                </button>

            </form>

        </div>



        <!-- Mobile sticky submit -->

        <div

            class="store-sticky-above-nav fixed inset-x-0 z-[45] border-t border-stadium-outline-soft bg-stadium-white px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-stadium-nav sm:hidden"

        >

            <button

                type="button"

                class="w-full min-h-14 rounded-2xl bg-stadium-lime text-base font-bold text-stadium-lime-ink shadow-lg transition hover:bg-stadium-lime/90 active:scale-[0.98] disabled:opacity-50"

                :disabled="form.processing"

                @click="submit"

            >

                {{ form.processing ? 'Placing order…' : 'Place order securely' }}

            </button>

        </div>

    </StoreLayout>

</template>


