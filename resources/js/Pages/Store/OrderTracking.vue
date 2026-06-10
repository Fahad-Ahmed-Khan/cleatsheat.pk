<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    seo: { type: Object, required: true },
    result: { type: Object, default: null },
    choices: { type: Array, default: () => [] },
    lookup: { type: Object, default: null },
    prefill_order_number: { type: String, default: null },
});

const page = usePage();

const lookupTabs = [
    { id: 'order_number', label: 'Order reference' },
    { id: 'email', label: 'Email' },
    { id: 'phone', label: 'Phone number' },
];

const lookupMode = ref('order_number');

const form = useForm({
    lookup_mode: 'order_number',
    order_number: '',
    email: '',
    phone: '',
});

const activeTab = computed(() => lookupTabs.find((t) => t.id === lookupMode.value) ?? lookupTabs[0]);

function syncFormFromPage() {
    const old = page.props.old ?? {};
    if (old.lookup_mode && lookupTabs.some((t) => t.id === old.lookup_mode)) {
        lookupMode.value = old.lookup_mode;
    } else if (old.email) {
        lookupMode.value = 'email';
    } else if (old.phone) {
        lookupMode.value = 'phone';
    } else if (props.lookup?.mode) {
        lookupMode.value = props.lookup.mode;
    }
    form.order_number = old.order_number ?? props.prefill_order_number ?? form.order_number;
    form.email = old.email ?? form.email;
    form.phone = old.phone ?? form.phone;
}

syncFormFromPage();

watch(lookupMode, () => {
    form.clearErrors();
});

function lookupPayload() {
    const mode = lookupMode.value;
    if (mode === 'email') {
        return { lookup_mode: 'email', email: form.email.trim(), order_number: '', phone: '' };
    }
    if (mode === 'phone') {
        return { lookup_mode: 'phone', phone: form.phone.trim(), order_number: '', email: '' };
    }
    return { lookup_mode: 'order_number', order_number: form.order_number.trim(), email: '', phone: '' };
}

function lookup() {
    const mode = lookupMode.value;
    if (mode === 'order_number' && !form.order_number.trim()) {
        form.setError('lookup', 'Enter your order reference.');
        return;
    }
    if (mode === 'email' && !form.email.trim()) {
        form.setError('lookup', 'Enter your checkout email.');
        return;
    }
    if (mode === 'phone' && !form.phone.trim()) {
        form.setError('lookup', 'Enter your phone number.');
        return;
    }
    form.clearErrors('lookup');
    form.transform(() => lookupPayload()).post(route('store.order-tracking.lookup'));
}

function trackChoice(choice) {
    form.clearErrors('lookup');
    // Contact was already verified to build the list; load by reference only.
    form.transform(() => ({
        lookup_mode: 'order_number',
        order_number: choice.order_number,
        email: '',
        phone: '',
    })).post(route('store.order-tracking.lookup'));
}

function statusLabel(s) {
    const map = {
        pending: 'Pending pickup',
        booked: 'Booked',
        in_transit: 'In transit',
        delivered: 'Delivered',
        failed: 'Failed',
        canceled: 'Canceled',
        processing: 'Processing',
    };
    return map[s] ?? s;
}

function paymentStatusLabel(s) {
    const map = {
        pending: 'Pending',
        paid: 'Paid',
        failed: 'Failed',
        canceled: 'Canceled',
        refunded: 'Refunded',
    };
    return map[s] ?? s;
}

function itemSummary(item) {
    const parts = [item.product_name];
    if (item.variant_label) {
        parts.push(item.variant_label);
    }
    if (item.size_label) {
        parts.push(`Size ${item.size_label}`);
    }
    return parts.join(' · ');
}

function formatPlacedAt(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatMoney(n) {
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);
}

const inputClass =
    'mt-2 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-4 text-base text-stadium-ink shadow-sm placeholder:text-stadium-secondary focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/20';
const labelClass = 'text-label text-stadium-secondary';
const cardClass =
    'rounded-2xl bg-stadium-white p-6 shadow-stadium ring-1 ring-stadium-outline-soft/50';
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div
            class="mx-auto px-4 py-10 sm:py-14"
            :class="result ? 'max-w-5xl' : 'max-w-lg'"
        >
            <h1 class="text-display-md text-stadium-ink">
                Track your order
            </h1>
            <p class="mt-2 text-body-lg text-stadium-secondary">
                Choose how you want to look up your order, then enter the matching detail below.
            </p>

            <form
                v-if="!result && !choices.length"
                :class="[cardClass, 'mt-8']"
                @submit.prevent="lookup"
            >
                <div
                    role="tablist"
                    aria-label="How to find your order"
                    class="flex rounded-2xl bg-stadium-muted p-1 ring-1 ring-stadium-outline-soft/80"
                >
                    <button
                        v-for="tab in lookupTabs"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        class="min-h-11 flex-1 rounded-xl px-2 text-xs font-bold transition sm:text-sm"
                        :class="
                            lookupMode === tab.id
                                ? 'bg-store-primary text-store-primary-fg shadow-sm ring-2 ring-store-primary/40'
                                : 'text-stadium-secondary hover:text-stadium-ink'
                        "
                        :aria-selected="lookupMode === tab.id"
                        @click="lookupMode = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="mt-5" role="tabpanel" :aria-labelledby="`tab-${lookupMode}`">
                    <label :for="`track-${lookupMode}`" :class="labelClass">
                        {{ activeTab.label }}
                    </label>

                    <input
                        v-if="lookupMode === 'order_number'"
                        id="track-order_number"
                        v-model="form.order_number"
                        type="text"
                        required
                        :class="inputClass"
                        placeholder="e.g. TR-XXXXXXXXXX"
                        autocomplete="off"
                    >
                    <input
                        v-else-if="lookupMode === 'email'"
                        id="track-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        :class="inputClass"
                        placeholder="Email used at checkout"
                    >
                    <input
                        v-else
                        id="track-phone"
                        v-model="form.phone"
                        type="tel"
                        required
                        autocomplete="tel"
                        :class="inputClass"
                        placeholder="e.g. 0300 1234567"
                    >

                    <p class="mt-2 text-xs text-stadium-secondary">
                        <template v-if="lookupMode === 'order_number'">
                            From your confirmation message or receipt.
                        </template>
                        <template v-else-if="lookupMode === 'email'">
                            We will list all orders placed with this email if there is more than one.
                        </template>
                        <template v-else>
                            Use the same number you gave for delivery.
                        </template>
                    </p>
                </div>

                <p v-if="form.errors.lookup" class="mt-4 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.lookup }}
                </p>
                <button
                    type="submit"
                    class="mt-5 w-full min-h-12 rounded-xl bg-stadium-lime text-sm font-bold text-stadium-lime-ink transition hover:bg-stadium-lime/90 active:scale-[0.99] disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Looking up…' : 'Find order' }}
                </button>
            </form>

            <!-- Multiple orders: ask user to pick one -->
            <section v-if="choices.length && !result" class="mt-8 space-y-4">
                <div :class="cardClass">
                    <h2 class="text-sm font-semibold text-stadium-ink">
                        Multiple orders found
                    </h2>
                    <p class="mt-1 text-sm text-stadium-secondary">
                        Select the order you want to track.
                    </p>
                </div>
                <ul class="space-y-3">
                    <li v-for="c in choices" :key="c.order_number">
                        <button
                            type="button"
                            class="flex w-full min-h-14 flex-col items-start gap-1 rounded-2xl bg-stadium-white px-5 py-4 text-left shadow-sm ring-1 ring-stadium-outline-soft transition hover:ring-store-primary/40 active:scale-[0.99] sm:flex-row sm:items-center sm:justify-between"
                            :disabled="form.processing"
                            @click="trackChoice(c)"
                        >
                            <span class="font-mono text-base font-bold text-stadium-ink">{{ c.order_number }}</span>
                            <span class="text-xs text-stadium-secondary">
                                {{ formatPlacedAt(c.placed_at) }}
                                · {{ statusLabel(c.status) }}
                                · {{ formatMoney(c.grand_total) }}
                            </span>
                        </button>
                    </li>
                </ul>
                <button
                    type="button"
                    class="text-sm font-semibold text-stadium-ink underline decoration-stadium-outline-soft underline-offset-4"
                    @click="form.get(route('store.order-tracking'))"
                >
                    Search again
                </button>
            </section>

            <section v-if="result" class="mt-10">
                <button
                    type="button"
                    class="text-sm font-semibold text-stadium-ink underline decoration-stadium-outline-soft underline-offset-4"
                    @click="form.get(route('store.order-tracking'))"
                >
                    ← Track another order
                </button>

                <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start lg:gap-8">
                    <!-- Left: order, products, payment -->
                    <div class="space-y-6">
                        <div :class="cardClass">
                            <h2 :class="labelClass">
                                Order details
                            </h2>
                            <p class="mt-3 font-mono text-lg font-bold text-stadium-ink">
                                {{ result.order_number }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="rounded-full bg-stadium-muted px-3 py-1 text-xs font-bold uppercase tracking-wide text-stadium-ink">
                                    {{ statusLabel(result.status) }}
                                </span>
                                <span
                                    v-if="result.payment?.status"
                                    class="rounded-full bg-store-primary/15 px-3 py-1 text-xs font-bold uppercase tracking-wide text-stadium-ink ring-1 ring-store-primary/30"
                                >
                                    {{ paymentStatusLabel(result.payment.status) }}
                                </span>
                            </div>
                            <p v-if="result.placed_at" class="mt-3 text-sm text-stadium-secondary">
                                Placed {{ formatPlacedAt(result.placed_at) }}
                            </p>
                        </div>

                        <div v-if="result.items?.length" :class="cardClass">
                            <h2 :class="labelClass">
                                Products
                            </h2>
                            <ul class="mt-4 space-y-4">
                                <li
                                    v-for="item in result.items"
                                    :key="item.id"
                                    class="flex gap-4 rounded-xl bg-stadium-muted/50 p-3 ring-1 ring-stadium-outline-soft/50"
                                >
                                    <div
                                        class="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-stadium-white ring-1 ring-stadium-outline-soft/80"
                                    >
                                        <img
                                            v-if="item.image_url"
                                            :src="item.image_url"
                                            :alt="item.product_name"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center text-[10px] font-semibold uppercase tracking-wide text-stadium-secondary"
                                        >
                                            No image
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold leading-snug text-stadium-ink">
                                            {{ itemSummary(item) }}
                                        </p>
                                        <p v-if="item.sku" class="mt-0.5 text-xs text-stadium-secondary">
                                            SKU {{ item.sku }}
                                        </p>
                                        <p class="mt-1 text-xs text-stadium-secondary">
                                            Qty {{ item.quantity }}
                                            <span v-if="item.quantity > 1" class="tabular-nums">
                                                · {{ formatMoney(item.unit_price) }} each
                                            </span>
                                        </p>
                                        <p class="mt-2 text-sm font-bold tabular-nums text-stadium-ink">
                                            {{ formatMoney(item.line_total) }}
                                        </p>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div v-if="result.payment || result.totals" :class="cardClass">
                            <h2 :class="labelClass">
                                Payment details
                            </h2>
                            <p v-if="result.payment?.gateway_label" class="mt-3 text-sm text-stadium-ink">
                                <span class="text-stadium-secondary">Method </span>
                                <span class="font-semibold">{{ result.payment.gateway_label }}</span>
                            </p>

                            <dl v-if="result.totals" class="mt-4 space-y-2 text-sm">
                                <div class="flex justify-between gap-4 text-stadium-secondary">
                                    <dt>Subtotal</dt>
                                    <dd class="tabular-nums text-stadium-ink">
                                        {{ formatMoney(result.totals.subtotal) }}
                                    </dd>
                                </div>
                                <div
                                    v-if="result.totals.discount_total > 0"
                                    class="flex justify-between gap-4 text-stadium-secondary"
                                >
                                    <dt>Discount</dt>
                                    <dd class="tabular-nums text-stadium-ink">
                                        −{{ formatMoney(result.totals.discount_total) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-4 text-stadium-secondary">
                                    <dt>Shipping</dt>
                                    <dd class="tabular-nums text-stadium-ink">
                                        {{ formatMoney(result.totals.shipping_total) }}
                                    </dd>
                                </div>
                                <div
                                    v-if="result.totals.cod_fee > 0"
                                    class="flex justify-between gap-4 text-stadium-secondary"
                                >
                                    <dt>COD fee</dt>
                                    <dd class="tabular-nums text-stadium-ink">
                                        {{ formatMoney(result.totals.cod_fee) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-4 border-t border-stadium-outline-soft/40 pt-3 font-semibold text-stadium-ink">
                                    <dt>Total</dt>
                                    <dd class="tabular-nums">
                                        {{ formatMoney(result.totals.grand_total) }}
                                    </dd>
                                </div>
                            </dl>

                            <ul
                                v-if="result.payments?.length > 1"
                                class="mt-6 space-y-3 border-t border-stadium-outline-soft/40 pt-4"
                            >
                                <li
                                    v-for="(p, idx) in result.payments"
                                    :key="idx"
                                    class="rounded-xl bg-stadium-muted/80 px-3 py-2.5 text-sm"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-medium text-stadium-ink">{{ p.gateway_label }}</span>
                                        <span class="text-xs font-semibold uppercase text-stadium-secondary">
                                            {{ paymentStatusLabel(p.status) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 tabular-nums text-stadium-secondary">
                                        {{ formatMoney(p.amount) }}
                                        <span v-if="p.paid_at"> · Paid {{ formatPlacedAt(p.paid_at) }}</span>
                                    </p>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Right: shipment timeline -->
                    <div class="space-y-6 lg:sticky lg:top-24">
                        <div
                            v-if="!result.shipments?.length"
                            :class="cardClass"
                        >
                            <h2 :class="labelClass">
                                Delivery timeline
                            </h2>
                            <p class="mt-4 text-sm text-stadium-secondary">
                                Shipment updates will appear here once your order is booked with a courier.
                            </p>
                        </div>

                        <div
                            v-for="s in result.shipments"
                            :key="s.id"
                            :class="cardClass"
                        >
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 :class="labelClass">
                                    {{ result.shipments.length > 1 ? `Shipment ${result.shipments.indexOf(s) + 1}` : 'Delivery timeline' }}
                                </h2>
                                <span
                                    class="rounded-full bg-store-primary/15 px-3 py-1 text-xs font-bold uppercase tracking-wide text-stadium-ink ring-1 ring-store-primary/30"
                                >
                                    {{ statusLabel(s.status) }}
                                </span>
                            </div>

                            <div class="mt-4 space-y-2 rounded-xl bg-stadium-muted/60 p-4 text-sm ring-1 ring-stadium-outline-soft/50">
                                <p class="text-stadium-ink">
                                    <span class="text-stadium-secondary">Courier </span>
                                    <span class="font-semibold">{{ s.courier ?? '—' }}</span>
                                </p>
                                <p class="font-mono text-xs font-semibold text-stadium-ink sm:text-sm">
                                    {{ s.tracking_number ?? 'Awaiting booking' }}
                                </p>
                                <a
                                    v-if="s.label_url"
                                    :href="s.label_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-block text-xs font-semibold text-store-secondary hover:underline"
                                >
                                    Open shipping label
                                </a>
                            </div>

                            <ol
                                v-if="s.events?.length"
                                class="relative mt-6 ml-1.5 space-y-6 border-l-2 border-stadium-outline-soft/70 pl-6"
                            >
                                <li
                                    v-for="(e, i) in s.events"
                                    :key="i"
                                    class="relative"
                                >
                                    <span
                                        class="absolute -left-[1.6rem] top-1 flex h-3 w-3 rounded-full ring-4 ring-stadium-white"
                                        :class="i === 0 ? 'bg-store-primary' : 'bg-stadium-outline-soft'"
                                        aria-hidden="true"
                                    />
                                    <p
                                        class="text-sm font-semibold leading-snug"
                                        :class="i === 0 ? 'text-stadium-ink' : 'text-stadium-secondary'"
                                    >
                                        {{ e.description ?? e.status ?? 'Update' }}
                                    </p>
                                    <p v-if="e.occurred_at" class="mt-1 text-xs text-stadium-secondary">
                                        {{ new Date(e.occurred_at).toLocaleString() }}
                                    </p>
                                </li>
                            </ol>
                            <p
                                v-else
                                class="mt-6 text-sm text-stadium-secondary"
                            >
                                No tracking events yet. Check back soon.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </StoreLayout>
</template>
