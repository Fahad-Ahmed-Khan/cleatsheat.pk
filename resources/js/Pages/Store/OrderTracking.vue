<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    seo: { type: Object, required: true },
    result: { type: Object, default: null },
});

const form = useForm({
    order_number: '',
    email: '',
});

function lookup() {
    form.post(route('store.order-tracking.lookup'));
}

function statusLabel(s) {
    const map = {
        pending: 'Pending pickup',
        booked: 'Booked',
        in_transit: 'In transit',
        delivered: 'Delivered',
        failed: 'Failed',
        canceled: 'Canceled',
    };
    return map[s] ?? s;
}
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
    </Head>
    <StoreLayout>
        <div class="mx-auto max-w-lg px-4 py-10">
            <h1 class="text-2xl font-semibold text-stone-900">
                Track your order
            </h1>
            <p class="mt-2 text-sm text-stone-600">
                Enter your order reference and the email used at checkout.
            </p>

            <form class="mt-8 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200" @submit.prevent="lookup">
                <div>
                    <label class="text-xs font-semibold uppercase text-stone-500">Order reference</label>
                    <input
                        v-model="form.order_number"
                        type="text"
                        required
                        class="mt-2 w-full min-h-12 rounded-xl border border-stone-200 px-4 text-base"
                        placeholder="e.g. TR-XXXXXXXXXX"
                    >
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase text-stone-500">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="mt-2 w-full min-h-12 rounded-xl border border-stone-200 px-4 text-base"
                    >
                </div>
                <p v-if="form.errors.order_number || form.errors.email" class="text-sm text-red-600">
                    {{ form.errors.order_number ?? form.errors.email }}
                </p>
                <button
                    type="submit"
                    class="w-full min-h-12 rounded-xl bg-stone-900 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Show tracking
                </button>
            </form>

            <section v-if="result" class="mt-10 space-y-6">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase text-stone-500">
                        Order
                    </p>
                    <p class="mt-1 font-mono text-lg font-semibold text-stone-900">
                        {{ result.order_number }}
                    </p>
                    <p class="mt-2 text-sm text-stone-600">
                        Order {{ result.status }} · Payment {{ result.payment_status }}
                    </p>
                </div>

                <div
                    v-for="s in result.shipments"
                    :key="s.id"
                    class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200"
                >
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold uppercase text-stone-500">
                            Shipment
                        </h2>
                        <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-800">
                            {{ statusLabel(s.status) }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-stone-800">
                        <span class="text-stone-500">Courier</span>
                        {{ s.courier ?? '—' }}
                    </p>
                    <p class="mt-1 font-mono text-sm text-stone-900">
                        {{ s.tracking_number ?? 'Awaiting booking' }}
                    </p>
                    <a
                        v-if="s.label_url"
                        :href="s.label_url"
                        target="_blank"
                        rel="noopener"
                        class="mt-4 inline-block text-sm font-medium text-stone-900 underline"
                    >
                        Open shipping label
                    </a>

                    <ul v-if="s.events?.length" class="mt-6 border-t border-stone-100 pt-4">
                        <li
                            v-for="(e, i) in s.events"
                            :key="i"
                            class="flex gap-3 border-l-2 border-stone-200 py-2 pl-4 text-sm"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-stone-900">
                                    {{ e.description ?? e.status ?? 'Update' }}
                                </p>
                                <p v-if="e.occurred_at" class="text-xs text-stone-500">
                                    {{ new Date(e.occurred_at).toLocaleString() }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </StoreLayout>
</template>
