<script setup>
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
            // keep history visible but don't auto-resume further
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

/** Optimistic row id — avoid crypto.randomUUID (missing in some browsers / non-secure contexts). */
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
        // Try to restore session when user re-enters same phone.
        tryResumeSession();
    },
);
</script>

<template>
    <div v-if="storeBargainEnabled">
        <!-- Slim discovery strip (stays in page flow) -->
        <section
            class="mt-8 rounded-2xl border border-emerald-200/90 bg-emerald-50/40 p-4 ring-1 ring-emerald-100 sm:p-4"
            aria-label="Bargain"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-emerald-950">
                        Bargain on this price
                    </h2>
                    <p v-if="!variantBargainEnabled" class="mt-1 text-sm text-emerald-900/80">
                        Bargaining is not enabled for this colour.
                    </p>
                    <p v-else class="mt-1 text-sm leading-relaxed text-emerald-900/85">
                        List {{ formatPKR(listPrice) }}. Chat from the corner widget — same phone at checkout after you lock a deal.
                    </p>
                </div>
                <button
                    v-if="variantBargainEnabled"
                    type="button"
                    class="shrink-0 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                    @click="openPanel"
                >
                    Open chat
                </button>
            </div>
        </section>

        <!-- Floating Messenger-style widget -->
        <Teleport to="body">
            <div
                v-if="canNegotiate"
                class="fixed right-4 z-[70] flex flex-col-reverse items-end gap-2 max-sm:bottom-[calc(5.5rem+env(safe-area-inset-bottom,0px))] sm:bottom-4"
                aria-live="polite"
            >
                <!-- Expanded panel -->
                <div
                    v-if="panelOpen"
                    id="bargain-chat-panel"
                    class="flex max-h-[min(72vh,560px)] w-[min(100vw-2rem,380px)] flex-col overflow-hidden rounded-2xl border border-stone-200/90 bg-white shadow-2xl ring-1 ring-stone-900/5"
                    role="dialog"
                    aria-modal="false"
                    aria-label="Bargain chat"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-stone-100 bg-emerald-800 px-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">
                                Bargain
                            </p>
                            <p class="truncate text-[11px] text-emerald-100/90">
                                {{ formatPKR(listPrice) }}
                                <span v-if="colorName"> · {{ colorName }}</span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-emerald-100 transition hover:bg-white/10 hover:text-white"
                            aria-label="Minimize chat"
                            @click="minimizePanel"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-3">
                        <div v-if="error" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200">
                            {{ error }}
                        </div>

                        <div v-if="!session" class="space-y-3">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wide text-stone-600">Your name</span>
                                <input
                                    v-model="customerName"
                                    type="text"
                                    autocomplete="name"
                                    placeholder="e.g. Fahad"
                                    class="mt-1 w-full min-h-11 rounded-xl border border-stone-200 bg-white px-3 text-stone-900 shadow-sm placeholder:text-stone-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400/40"
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-wide text-stone-600">Mobile (Pakistan)</span>
                                <input
                                    v-model="phone"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="03XX XXXXXXX"
                                    class="mt-1 w-full min-h-11 rounded-xl border border-stone-200 bg-white px-3 text-stone-900 shadow-sm placeholder:text-stone-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400/40"
                                >
                            </label>
                            <button
                                type="button"
                                class="w-full min-h-11 rounded-xl bg-emerald-700 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:bg-emerald-300"
                                :disabled="loading || !phone.trim() || !customerName.trim()"
                                @click="startSession"
                            >
                                {{ loading ? 'Starting…' : 'Start bargaining' }}
                            </button>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                ref="messageLogRef"
                                class="max-h-[min(44vh,320px)] space-y-2 overflow-y-auto rounded-xl bg-stone-50/90 p-3 ring-1 ring-stone-100"
                                role="log"
                            >
                                <div
                                    v-for="m in messages"
                                    :key="m.id"
                                    class="flex flex-col gap-0.5 text-sm"
                                    :class="m.role === 'customer' ? 'items-end' : 'items-start'"
                                >
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-stone-400">
                                        {{ m.role === 'customer' ? 'You' : 'Shop' }}
                                    </span>
                                    <p
                                        class="max-w-[95%] rounded-2xl px-3 py-2 leading-relaxed"
                                        :class="
                                            m.role === 'customer'
                                                ? 'bg-emerald-700 text-white'
                                                : 'bg-white text-stone-900 ring-1 ring-stone-200/80'
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
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-stone-400">Shop</span>
                                    <div
                                        class="flex items-center gap-1 rounded-2xl bg-white px-4 py-3 ring-1 ring-stone-200/80"
                                    >
                                        <span class="store-bargain-dot rounded-full bg-stone-400 [animation-delay:0ms]" />
                                        <span class="store-bargain-dot rounded-full bg-stone-400 [animation-delay:150ms]" />
                                        <span class="store-bargain-dot rounded-full bg-stone-400 [animation-delay:300ms]" />
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
                                        class="w-full resize-none rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm text-stone-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400/40"
                                        @keydown.enter.exact.prevent="sendMessage"
                                    />
                                </label>
                                <button
                                    type="button"
                                    class="min-h-11 w-full shrink-0 rounded-xl bg-stone-900 px-4 text-sm font-semibold text-white disabled:bg-stone-300"
                                    :disabled="loading || !draft.trim()"
                                    @click="sendMessage"
                                >
                                    Send
                                </button>
                            </div>

                            <div v-if="sessionActive" class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="min-h-10 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-emerald-300"
                                    :disabled="loading || !canAccept"
                                    @click="acceptDeal"
                                >
                                    Accept locked offer
                                </button>
                                <button
                                    type="button"
                                    class="min-h-10 rounded-xl bg-white px-4 text-sm font-semibold text-stone-800 ring-1 ring-stone-300"
                                    :disabled="loading"
                                    @click="declineDeal"
                                >
                                    Walk away
                                </button>
                            </div>

                            <p v-if="sessionActive && !canAccept" class="text-xs text-stone-600">
                                Send an offer first; once the shop names a price, you can accept to lock it for checkout.
                            </p>

                            <div
                                v-if="isAccepted"
                                class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white shadow-inner"
                            >
                                Locked at {{ formatPKR(Number(session.accepted_price)) }}. Add to bag below with this phone (
                                {{ phone.trim() }}) so the cart picks up this price.
                            </div>

                            <button
                                type="button"
                                class="text-xs font-semibold text-emerald-900 underline underline-offset-2"
                                @click="startOver"
                            >
                                Start over
                            </button>
                        </div>
                    </div>
                </div>

                <!-- FAB -->
                <button
                    type="button"
                    class="flex h-14 items-center gap-2 rounded-full bg-emerald-700 pl-4 pr-5 text-sm font-semibold text-white shadow-lg ring-2 ring-white/30 transition hover:bg-emerald-600"
                    :aria-expanded="panelOpen"
                    aria-controls="bargain-chat-panel"
                    @click="panelOpen = !panelOpen"
                >
                    <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                        />
                    </svg>
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
