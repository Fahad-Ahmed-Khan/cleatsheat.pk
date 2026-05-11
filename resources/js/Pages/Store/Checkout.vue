<script setup>
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreTrustStrip from '@/Components/Store/StoreTrustStrip.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { useStoreAnalytics } from '@/composables/useStoreAnalytics';
import { useForm, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';

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
});

const analytics = useStoreAnalytics();

onMounted(() => {
    if (props.analytics_checkout?.items?.length) {
        analytics.trackBeginCheckout(props.analytics_checkout);
    }
});

const defaultGateway = props.gateways[0]?.code ?? 'cod';

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
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="mx-auto max-w-lg px-4 pb-40 pt-8 sm:max-w-xl sm:pb-16">
            <h1 class="text-2xl font-semibold tracking-tight text-stone-900">
                Checkout
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-stone-600">
                One-handed friendly fields. Your details stay on our encrypted session until the order is placed.
            </p>

            <StoreTrustStrip compact class="mt-8" />

            <form class="mt-10 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Full name</label>
                    <input
                        v-model="form.full_name"
                        type="text"
                        required
                        autocomplete="name"
                        class="mt-2 w-full min-h-14 rounded-2xl border border-stone-200 bg-white px-4 text-base text-stone-900 shadow-sm ring-stone-900/5 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-900/15"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Phone (WhatsApp)</label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        required
                        autocomplete="tel"
                        inputmode="tel"
                        class="mt-2 w-full min-h-14 rounded-2xl border border-stone-200 bg-white px-4 text-base text-stone-900 shadow-sm focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-900/15"
                    >
                </div>
                <div v-if="!$page.props.auth.user">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Email</label>
                    <input
                        v-model="form.guest_email"
                        type="email"
                        required
                        autocomplete="email"
                        inputmode="email"
                        class="mt-2 w-full min-h-14 rounded-2xl border border-stone-200 bg-white px-4 text-base focus:outline-none focus:ring-2 focus:ring-stone-900/15"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Street address</label>
                    <input
                        v-model="form.line1"
                        type="text"
                        required
                        autocomplete="street-address"
                        class="mt-2 w-full min-h-14 rounded-2xl border border-stone-200 bg-white px-4 text-base focus:outline-none focus:ring-2 focus:ring-stone-900/15"
                    >
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">City</label>
                        <input
                            v-model="form.city"
                            type="text"
                            required
                            class="mt-2 w-full min-h-14 rounded-2xl border border-stone-200 px-3 text-base focus:outline-none focus:ring-2 focus:ring-stone-900/15"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Area</label>
                        <input
                            v-model="form.area"
                            type="text"
                            class="mt-2 w-full min-h-14 rounded-2xl border border-stone-200 px-3 text-base focus:outline-none focus:ring-2 focus:ring-stone-900/15"
                        >
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Postal code</label>
                    <input
                        v-model="form.postal_code"
                        type="text"
                        autocomplete="postal-code"
                        class="mt-2 w-full min-h-14 rounded-2xl border border-stone-200 px-4 text-base focus:outline-none focus:ring-2 focus:ring-stone-900/15"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Order notes</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="mt-2 w-full rounded-2xl border border-stone-200 px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-stone-900/15"
                    />
                </div>

                <fieldset>
                    <legend class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                        Payment
                    </legend>
                    <div class="mt-3 space-y-2">
                        <label
                            v-for="g in gateways"
                            :key="g.code"
                            class="flex min-h-14 cursor-pointer flex-col gap-1 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-medium transition"
                            :class="form.payment_gateway === g.code ? 'ring-2 ring-stone-900' : 'hover:border-stone-300'"
                        >
                            <div class="flex w-full items-center gap-4">
                                <input
                                    v-model="form.payment_gateway"
                                    type="radio"
                                    name="payment_gateway"
                                    :value="g.code"
                                    class="h-5 w-5 shrink-0 border-stone-300 text-stone-900"
                                >
                                <span>{{ g.label }}</span>
                            </div>
                            <p v-if="feeHint(g)" class="pl-9 text-xs font-normal text-stone-500">
                                {{ feeHint(g) }}
                            </p>
                        </label>
                    </div>
                </fieldset>

                <p v-if="page.props.errors?.checkout" class="text-sm text-red-600">
                    {{ page.props.errors.checkout }}
                </p>

                <button
                    type="submit"
                    class="hidden w-full min-h-14 rounded-2xl bg-stone-900 text-base font-semibold text-white shadow-lg transition hover:bg-stone-800 active:scale-[0.99] disabled:opacity-50 sm:block"
                    :disabled="form.processing"
                >
                    Place order
                </button>
            </form>
        </div>

        <!-- Mobile sticky submit -->
        <div
            class="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200/90 bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur-md sm:hidden"
        >
            <button
                type="button"
                class="w-full min-h-14 rounded-2xl bg-stone-900 text-base font-semibold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-50"
                :disabled="form.processing"
                @click="submit"
            >
                Place order securely
            </button>
        </div>
    </StoreLayout>
</template>
