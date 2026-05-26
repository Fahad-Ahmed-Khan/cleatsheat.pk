<script setup>
import StoreBargainIcon from '@/Components/Store/StoreBargainIcon.vue';
import { useStoreBargainApi } from '@/composables/useStoreBargainApi';
import { useStoreFormat } from '@/composables/useStoreFormat';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    /** Global kill-switch from config */
    storeBargainEnabled: { type: Boolean, default: true },
    /** Selected variant id */
    productVariantId: { type: Number, required: true },
    /** Display price (list) for hints */
    listPrice: { type: Number, required: true },
    /** Variant allows bargaining */
    variantBargainEnabled: { type: Boolean, default: false },
    colorName: { type: String, default: '' },
    /** Hide floating FAB on small screens (e.g. PDP uses sticky-bar bargain control). */
    hideMobileFab: { type: Boolean, default: false },
    /** Raise FAB above a second sticky action bar (product page). */
    stackAboveActionBar: { type: Boolean, default: false },
});

const emit = defineEmits(['locked', 'cleared']);

const api = useStoreBargainApi();
const { formatPKR } = useStoreFormat();

const customerName = ref('');
const phone = ref('');
const draft = ref('');
const session = ref(null);
const loading = ref(false);
const error = ref('');

const panelOpen = ref(false);
/** Optimistic user bubble while POST /messages is in flight */
const pendingUser = ref(null);
const isAssistantTyping = ref(false);

/** Scrollable message list (auto-scroll to latest) */
const messageLogRef = ref(null);

const messages = computed(() => {
    const base = session.value?.messages ?? [];
    if (!pendingUser.value) {
        return base;
    }
    return [
        ...base,
        {
            id: pendingUser.value.id,
            role: 'customer',
            body: pendingUser.value.body,
            meta: null,
            created_at: null,
        },
    ];
});

const canNegotiate = computed(() => props.storeBargainEnabled && props.variantBargainEnabled);

const sessionActive = computed(() => {
    const s = session.value;
    if (!s) return false;
    return s.state === 'open' || s.state === 'countered';
});

const canAccept = computed(() => {
    const s = session.value;
    if (!s || !sessionActive.value) return false;
    return s.current_offer != null && String(s.current_offer).trim() !== '';
});

const isAccepted = computed(() => session.value?.state === 'accepted');

const fabPositionClass = computed(() => {
    if (props.hideMobileFab || props.stackAboveActionBar) {
        return 'store-fab-above-purchase max-sm:right-4 max-sm:items-end sm:bottom-[5.75rem]';
    }
    return 'store-sticky-above-nav sm:bottom-[5.75rem]';
});

function openPanel() {
    if (!props.storeBargainEnabled) return;
    panelOpen.value = true;
}

function minimizePanel() {
    panelOpen.value = false;
}

function resetUi(message = '') {
    session.value = null;
    draft.value = '';
    error.value = message;
    pendingUser.value = null;
    isAssistantTyping.value = false;
    emit('cleared');
}

function sessionStorageKey() {
    return `tryino:bargain:variant:${props.productVariantId}:phone:${phone.value.trim()}`;
}

function saveSessionToStorage() {
    const s = session.value;
    if (!s || !phone.value.trim()) return;
    try {
        localStorage.setItem(
            sessionStorageKey(),
            JSON.stringify({
                session_id: s.id,
                expires_at: s.expires_at ?? null,
                customer_name: customerName.value.trim() || null,
            }),
        );
    } catch {
        // ignore storage failures
    }
}

function clearSessionFromStorage() {
    try {
        localStorage.removeItem(sessionStorageKey());
    } catch {
        // ignore
    }
}

function isExpiredIso(expiresAt) {
    if (!expiresAt) return false;
    const t = Date.parse(expiresAt);
    if (Number.isNaN(t)) return false;
    return t <= Date.now();
}

async function tryResumeSession() {
    const p = phone.value.trim();
    if (!p) return;
    let raw = null;
    try {
        raw = localStorage.getItem(sessionStorageKey());
    } catch {
        raw = null;
    }
    if (!raw) return;
    let saved = null;
    try {
        saved = JSON.parse(raw);
    } catch {
        saved = null;
    }
    const sessionId = saved?.session_id;
    if (!sessionId) return;
    if (isExpiredIso(saved?.expires_at)) {
        clearSessionFromStorage();
        return;
    }

    loading.value = true;
    isAssistantTyping.value = true;
    error.value = '';
    try {
        const res = await api.getStatus(sessionId, p);
        session.value = res.data.session;
        if (saved?.customer_name && !customerName.value.trim()) {
            customerName.value = String(saved.customer_name);
        }
        panelOpen.value = true;
        scrollChatToBottom();

        if (isExpiredIso(session.value?.expires_at) || !(session.value?.state === 'open' || session.value?.state === 'countered' || session.value?.state === 'accepted')) {
            clearSessionFromStorage();
        } else {
            saveSessionToStorage();
        }
    } catch {
        clearSessionFromStorage();
    } finally {
        loading.value = false;
        isAssistantTyping.value = false;
    }
}

watch(
    () => props.productVariantId,
    () => {
        resetUi('');
    },
);

function scrollChatToBottom() {
    nextTick(() => {
        const el = messageLogRef.value;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    });
}

watch(
    [messages, isAssistantTyping],
    () => scrollChatToBottom(),
    { deep: true, flush: 'post' },
);

watch(panelOpen, (open) => {
    if (open) {
        scrollChatToBottom();
    }
});

function onKeydown(e) {
    if (e.key === 'Escape' && panelOpen.value) {
        minimizePanel();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});

defineExpose({
    openPanel,
});

function makeTempMessageId() {
    const c = typeof globalThis !== 'undefined' ? globalThis.crypto : undefined;
    if (c && typeof c.randomUUID === 'function') {
        return `temp-${c.randomUUID()}`;
    }
    return `temp-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 11)}`;
}

async function startSession() {
    error.value = '';
    loading.value = true;
    isAssistantTyping.value = true;
    try {
        const res = await api.startSession({
            product_variant_id: props.productVariantId,
            customer_name: customerName.value.trim(),
            customer_phone: phone.value.trim(),
        });
        session.value = res.data.session;
        panelOpen.value = true;
        saveSessionToStorage();
        scrollChatToBottom();
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Could not start bargaining.';
    } finally {
        loading.value = false;
        isAssistantTyping.value = false;
    }
}

async function sendMessage() {
    const text = draft.value.trim();
    if (!text || !session.value || loading.value) return;
    error.value = '';
    const savedDraft = text;
    const tempId = makeTempMessageId();
    pendingUser.value = { id: tempId, body: text };
    draft.value = '';
    loading.value = true;
    isAssistantTyping.value = true;
    try {
        const res = await api.sendMessage(session.value.id, {
            customer_phone: phone.value.trim(),
            message: text,
        });
        pendingUser.value = null;
        session.value = res.data.session;
        saveSessionToStorage();
        scrollChatToBottom();
    } catch (e) {
        pendingUser.value = null;
        draft.value = savedDraft;
        error.value = e instanceof Error ? e.message : 'Message failed.';
    } finally {
        loading.value = false;
        isAssistantTyping.value = false;
    }
}

async function acceptDeal() {
    if (!session.value || loading.value) return;
    error.value = '';
    loading.value = true;
    try {
        const res = await api.accept(session.value.id, {
            customer_phone: phone.value.trim(),
        });
        session.value = res.data.session;
        saveSessionToStorage();

        const t = res.data.tracking?.bargain_accepted;
        if (t && typeof window !== 'undefined' && window.tryinoTrack?.bargainAccepted) {
            window.tryinoTrack.bargainAccepted({
                value: t.value,
                items: [
                    {
                        item_id: String(t.product_variant_id),
                        item_name: props.colorName ? `${props.colorName} · variant` : 'Product',
                        price: t.value,
                        quantity: 1,
                    },
                ],
            });
        }

        emit('locked', {
            phone: phone.value.trim(),
            acceptedPrice: String(res.data.session.accepted_price ?? ''),
        });
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Could not lock price.';
    } finally {
        loading.value = false;
    }
}

async function declineDeal() {
    if (!session.value || loading.value) return;
    error.value = '';
    loading.value = true;
    try {
        const res = await api.decline(session.value.id, {
            customer_phone: phone.value.trim(),
        });
        session.value = res.data.session;
        clearSessionFromStorage();
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Could not decline.';
    } finally {
        loading.value = false;
    }
}

function startOver() {
    clearSessionFromStorage();
    resetUi('');
}

watch(
    () => phone.value,
    () => {
        tryResumeSession();
    },
);
</script>

<template>
    <div v-if="storeBargainEnabled">
        <!-- In-page discovery strip -->
        <section
            class="mt-8 rounded-2xl border border-stadium-outline-soft/60 bg-stadium-muted/80 p-4 ring-1 ring-stadium-outline-soft/40 sm:p-4"
            aria-label="Bargain"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-stadium-lime/20 text-stadium-lime ring-1 ring-stadium-lime/35"
                            aria-hidden="true"
                        >
                            <StoreBargainIcon class="h-4 w-4" />
                        </span>
                        <h2 class="font-display text-sm font-bold uppercase tracking-wide text-stadium-ink">
                            Bargain on this price
                        </h2>
                    </div>
                    <p v-if="!variantBargainEnabled" class="mt-2 text-sm text-stadium-secondary">
                        Bargaining is not enabled for this colour.
                    </p>
                    <p v-else class="mt-2 text-sm leading-relaxed text-stadium-secondary">
                        List {{ formatPKR(listPrice) }}. Chat to negotiate — use the same phone at checkout after you lock a deal.
                    </p>
                </div>
                <button
                    v-if="variantBargainEnabled"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-2 rounded-2xl bg-stadium-lime px-4 py-2.5 text-sm font-bold text-stadium-lime-ink shadow-md ring-1 ring-stadium-lime/40 transition hover:bg-stadium-lime/90 active:scale-[0.98]"
                    @click="openPanel"
                >
                    <StoreBargainIcon class="h-4 w-4" />
                    Open chat
                </button>
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="canNegotiate"
                class="fixed z-[70] flex flex-col-reverse gap-2 max-sm:left-4 max-sm:items-start sm:right-4 sm:items-end"
                :class="fabPositionClass"
                aria-live="polite"
            >
                <!-- Expanded panel -->
                <div
                    v-if="panelOpen"
                    id="bargain-chat-panel"
                    class="flex max-h-[min(72vh,560px)] w-[min(100vw-2rem,380px)] flex-col overflow-hidden rounded-2xl border border-stadium-outline-soft/80 bg-stadium-white shadow-2xl ring-1 ring-stadium-outline-soft/50 dark:shadow-black/40"
                    role="dialog"
                    aria-modal="false"
                    aria-label="Bargain chat"
                >
                    <div
                        class="flex items-center justify-between gap-2 border-b border-stadium-outline-soft/60 bg-stadium-inverse px-3 py-2.5"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-stadium-lime text-stadium-lime-ink"
                                aria-hidden="true"
                            >
                                <StoreBargainIcon class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-display text-sm font-bold uppercase tracking-wide text-stadium-inverse-text">
                                    Bargain
                                </p>
                                <p class="truncate text-[11px] tabular-nums text-stadium-inverse-text/75">
                                    {{ formatPKR(listPrice) }}
                                    <span v-if="colorName"> · {{ colorName }}</span>
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2 text-stadium-inverse-text/80 transition hover:bg-white/10 hover:text-stadium-inverse-text"
                            aria-label="Minimize chat"
                            @click="minimizePanel"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto bg-stadium-muted/30 p-3 dark:bg-stadium-container/50">
                        <div
                            v-if="error"
                            class="mb-3 rounded-xl bg-red-500/10 px-3 py-2 text-sm text-red-700 ring-1 ring-red-500/25 dark:text-red-300"
                        >
                            {{ error }}
                        </div>

                        <div v-if="!session" class="space-y-3">
                            <label class="block">
                                <span class="text-label text-stadium-secondary">Your name</span>
                                <input
                                    v-model="customerName"
                                    type="text"
                                    autocomplete="name"
                                    placeholder="e.g. Fahad"
                                    class="mt-1 w-full min-h-11 rounded-xl border border-stadium-outline-soft bg-stadium-white px-3 text-stadium-ink shadow-sm placeholder:text-stadium-secondary focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/25"
                                >
                            </label>
                            <label class="block">
                                <span class="text-label text-stadium-secondary">Mobile (Pakistan)</span>
                                <input
                                    v-model="phone"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="03XX XXXXXXX"
                                    class="mt-1 w-full min-h-11 rounded-xl border border-stadium-outline-soft bg-stadium-white px-3 text-stadium-ink shadow-sm placeholder:text-stadium-secondary focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/25"
                                >
                            </label>
                            <button
                                type="button"
                                class="inline-flex w-full min-h-11 items-center justify-center gap-2 rounded-2xl bg-stadium-lime text-sm font-bold text-stadium-lime-ink shadow-md ring-1 ring-stadium-lime/40 transition hover:bg-stadium-lime/90 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="loading || !phone.trim() || !customerName.trim()"
                                @click="startSession"
                            >
                                <StoreBargainIcon class="h-4 w-4" />
                                {{ loading ? 'Starting…' : 'Start bargaining' }}
                            </button>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                ref="messageLogRef"
                                class="max-h-[min(44vh,320px)] space-y-2 overflow-y-auto rounded-xl bg-stadium-muted/90 p-3 ring-1 ring-stadium-outline-soft/70"
                                role="log"
                            >
                                <div
                                    v-for="m in messages"
                                    :key="m.id"
                                    class="flex flex-col gap-0.5 text-sm"
                                    :class="m.role === 'customer' ? 'items-end' : 'items-start'"
                                >
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-stadium-secondary">
                                        {{ m.role === 'customer' ? 'You' : 'Shop' }}
                                    </span>
                                    <p
                                        class="max-w-[95%] rounded-2xl px-3 py-2 leading-relaxed"
                                        :class="
                                            m.role === 'customer'
                                                ? 'bg-stadium-lime font-medium text-stadium-lime-ink'
                                                : 'bg-stadium-white text-stadium-ink ring-1 ring-stadium-outline-soft/80'
                                        "
                                    >
                                        {{ m.body }}
                                    </p>
                                </div>

                                <div
                                    v-if="isAssistantTyping && sessionActive"
                                    class="flex flex-col gap-0.5 text-sm items-start"
                                    aria-hidden="true"
                                >
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-stadium-secondary">Shop</span>
                                    <div
                                        class="flex items-center gap-1 rounded-2xl bg-stadium-white px-4 py-3 ring-1 ring-stadium-outline-soft/80"
                                    >
                                        <span class="store-bargain-dot rounded-full bg-stadium-lime [animation-delay:0ms]" />
                                        <span class="store-bargain-dot rounded-full bg-stadium-lime [animation-delay:150ms]" />
                                        <span class="store-bargain-dot rounded-full bg-stadium-lime [animation-delay:300ms]" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="sessionActive" class="flex flex-col gap-2">
                                <label class="min-w-0 flex-1">
                                    <span class="sr-only">Your message</span>
                                    <textarea
                                        v-model="draft"
                                        rows="2"
                                        placeholder="Make an offer (e.g. can you do around 11.5k?)"
                                        class="w-full resize-none rounded-xl border border-stadium-outline-soft bg-stadium-white px-3 py-2 text-sm text-stadium-ink shadow-sm placeholder:text-stadium-secondary focus:border-store-primary focus:outline-none focus:ring-2 focus:ring-store-primary/25"
                                        @keydown.enter.exact.prevent="sendMessage"
                                    />
                                </label>
                                <button
                                    type="button"
                                    class="min-h-11 w-full shrink-0 rounded-2xl bg-stadium-inverse px-4 text-sm font-bold text-stadium-inverse-text transition hover:bg-stadium-inverse/90 disabled:opacity-40"
                                    :disabled="loading || !draft.trim()"
                                    @click="sendMessage"
                                >
                                    Send
                                </button>
                            </div>

                            <div v-if="sessionActive" class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-2xl bg-stadium-lime px-4 text-sm font-bold text-stadium-lime-ink ring-1 ring-stadium-lime/40 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="loading || !canAccept"
                                    @click="acceptDeal"
                                >
                                    Accept locked offer
                                </button>
                                <button
                                    type="button"
                                    class="min-h-10 rounded-2xl bg-stadium-white px-4 text-sm font-semibold text-stadium-ink ring-1 ring-stadium-outline-soft transition hover:bg-stadium-muted"
                                    :disabled="loading"
                                    @click="declineDeal"
                                >
                                    Walk away
                                </button>
                            </div>

                            <p v-if="sessionActive && !canAccept" class="text-xs text-stadium-secondary">
                                Send an offer first; once the shop names a price, you can accept to lock it for checkout.
                            </p>

                            <div
                                v-if="isAccepted"
                                class="rounded-xl bg-stadium-lime/15 px-4 py-3 text-sm font-medium text-stadium-ink ring-1 ring-stadium-lime/45"
                            >
                                Locked at {{ formatPKR(Number(session.accepted_price)) }}. Add to bag below with this phone (
                                {{ phone.trim() }}) so the cart picks up this price.
                            </div>

                            <button
                                type="button"
                                class="text-xs font-semibold text-stadium-lime-muted underline underline-offset-2 hover:text-stadium-lime"
                                @click="startOver"
                            >
                                Start over
                            </button>
                        </div>
                    </div>
                </div>

                <!-- FAB (desktop / when not using sticky bar) -->
                <button
                    type="button"
                    class="inline-flex h-14 items-center gap-2 rounded-2xl bg-stadium-lime pl-3.5 pr-4 text-sm font-bold text-stadium-lime-ink shadow-lg ring-2 ring-stadium-outline-soft/60 transition hover:bg-stadium-lime/90 active:scale-[0.98]"
                    :class="hideMobileFab ? 'hidden sm:inline-flex' : 'inline-flex'"
                    :aria-expanded="panelOpen"
                    aria-controls="bargain-chat-panel"
                    @click="panelOpen = !panelOpen"
                >
                    <StoreBargainIcon class="h-5 w-5" />
                    Bargain
                </button>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.store-bargain-dot {
    width: 6px;
    height: 6px;
    animation: store-bargain-bounce 1.05s ease-in-out infinite;
}

@keyframes store-bargain-bounce {
    0%,
    60%,
    100% {
        transform: translateY(0);
        opacity: 0.35;
    }
    30% {
        transform: translateY(-4px);
        opacity: 1;
    }
}
</style>
