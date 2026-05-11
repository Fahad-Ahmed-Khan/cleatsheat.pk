<script setup>
import StoreBottomSheet from '@/Components/Store/StoreBottomSheet.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

const page = usePage();

const cfg = computed(() => page.props.storefrontAssistant || null);

const ui = computed(() => (cfg.value && typeof cfg.value.ui === 'object' ? cfg.value.ui : {}));
const steps = computed(() => (cfg.value && Array.isArray(cfg.value.steps) ? cfg.value.steps : []));
const mapping = computed(() => (cfg.value && typeof cfg.value.mapping === 'object' ? cfg.value.mapping : {}));

const visibleWidget = ref(false);
const open = ref(false);

const stepIndex = ref(-1); // -1 = welcome
const answers = reactive({});
const errors = reactive({});

const snoozeKey = 'tryino_storefront_assistant_snooze_until';

function stepOptions(step) {
    const opts = step?.options;
    return Array.isArray(opts) ? opts.filter((o) => o && o.value != null) : [];
}

function isMultiple(step) {
    return !!step?.multiple;
}

function normalizeArray(val) {
    if (Array.isArray(val)) return val.map((v) => String(v));
    if (val === undefined || val === null || String(val).trim() === '') return [];
    return [String(val)];
}

function setAnswer(step, val) {
    if (!step?.key) return;
    if (isMultiple(step)) {
        answers[step.key] = normalizeArray(val);
        return;
    }
    answers[step.key] = val;
}

function clearAnswer(step) {
    if (!step?.key) return;
    delete errors[step.key];
    if (isMultiple(step)) {
        answers[step.key] = [];
        return;
    }
    answers[step.key] = '';
}

function toggleBadge(step, rawVal) {
    if (!step?.key) return;
    delete errors[step.key];
    const v = String(rawVal);

    if (!isMultiple(step)) {
        answers[step.key] = answers[step.key] === v ? '' : v;
        return;
    }

    const arr = normalizeArray(answers[step.key]);
    const i = arr.indexOf(v);
    if (i === -1) arr.push(v);
    else arr.splice(i, 1);
    answers[step.key] = arr;
}

function isBadgeSelected(step, rawVal) {
    if (!step?.key) return false;
    const v = String(rawVal);
    if (!isMultiple(step)) return String(answers[step.key] ?? '') === v;
    return normalizeArray(answers[step.key]).includes(v);
}

function selectAll(step) {
    if (!step?.key) return;
    delete errors[step.key];
    const opts = stepOptions(step);
    if (!isMultiple(step)) {
        if (opts[0]) answers[step.key] = String(opts[0].value);
        return;
    }
    answers[step.key] = opts.map((o) => String(o.value));
}

function nowMs() {
    return Date.now();
}

function readSnoozeUntil() {
    try {
        const raw = localStorage.getItem(snoozeKey);
        const n = raw ? Number(raw) : 0;
        return Number.isFinite(n) ? n : 0;
    } catch {
        return 0;
    }
}

function setSnoozeDays(days) {
    const d = Math.max(0, Number(days || 0));
    const until = d <= 0 ? nowMs() : nowMs() + d * 86400 * 1000;
    try {
        localStorage.setItem(snoozeKey, String(until));
    } catch {
        // ignore
    }
}

function currentRouteName() {
    try {
        // Ziggy exposes route() globally; route().current() returns route name.
        // eslint-disable-next-line no-undef
        return route().current();
    } catch {
        return null;
    }
}

function isAllowedOnThisPage() {
    const list = cfg.value?.allowed_routes ?? [];
    if (!Array.isArray(list) || list.length === 0) return false;
    const cur = currentRouteName();
    if (!cur) return false;
    return list.includes(cur);
}

function isEnabled() {
    return !!cfg.value?.enabled;
}

function shouldShow() {
    if (!isEnabled()) return false;
    if (!isAllowedOnThisPage()) return false;
    const until = readSnoozeUntil();
    if (until && until > nowMs()) return false;
    return true;
}

let timer = null;

function scheduleWidget() {
    visibleWidget.value = false;
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }

    if (!shouldShow()) return;

    const delay = Math.max(0, Number(cfg.value?.delay_seconds ?? 0));
    timer = setTimeout(() => {
        if (shouldShow()) visibleWidget.value = true;
    }, delay * 1000);
}

function resetFlow() {
    stepIndex.value = -1;
    Object.keys(answers).forEach((k) => delete answers[k]);
    Object.keys(errors).forEach((k) => delete errors[k]);
}

function openAssistant() {
    resetFlow();
    open.value = true;
}

function closeAssistant(snooze = true) {
    open.value = false;
    if (snooze) {
        setSnoozeDays(cfg.value?.snooze_days ?? 7);
        visibleWidget.value = false;
    }
}

function validateStep(step) {
    const key = step?.key;
    if (!key) return true;

    delete errors[key];

    const required = !!step.required;
    const val = answers[key];

    if (isMultiple(step)) {
        const arr = normalizeArray(val);
        if (!required && arr.length === 0) return true;
        if (required && arr.length === 0) {
            errors[key] = 'Please select at least one.';
            return false;
        }
        return true;
    }

    if (!required && (val === undefined || val === null || String(val).trim() === '')) return true;

    if (step.type === 'number') {
        const n = Number(val);
        if (!Number.isFinite(n)) {
            errors[key] = 'Please enter a valid number.';
            return false;
        }
        const min = step.min != null ? Number(step.min) : null;
        const max = step.max != null ? Number(step.max) : null;
        if (min != null && Number.isFinite(min) && n < min) {
            errors[key] = `Must be at least ${min}.`;
            return false;
        }
        if (max != null && Number.isFinite(max) && n > max) {
            errors[key] = `Must be at most ${max}.`;
            return false;
        }
    } else if (required && (val === undefined || val === null || String(val).trim() === '')) {
        errors[key] = 'This field is required.';
        return false;
    }

    return true;
}

function next() {
    if (stepIndex.value === -1) {
        stepIndex.value = 0;
        return;
    }

    const step = steps.value[stepIndex.value];
    if (!validateStep(step)) return;

    if (stepIndex.value < steps.value.length - 1) {
        stepIndex.value += 1;
        return;
    }

    submit();
}

function back() {
    if (stepIndex.value <= -1) {
        closeAssistant(false);
        return;
    }
    stepIndex.value -= 1;
}

function submit() {
    // validate all steps (in case user jumps)
    for (const s of steps.value) {
        if (!validateStep(s)) {
            const i = steps.value.findIndex((x) => x.key === s.key);
            stepIndex.value = i >= 0 ? i : 0;
            return;
        }
    }

    const params = new URLSearchParams();
    for (const s of steps.value) {
        const key = s.key;
        const qp = mapping.value?.[key];
        if (!qp) continue;
        const val = answers[key];
        if (Array.isArray(val)) {
            const arr = normalizeArray(val).filter((x) => String(x).trim() !== '');
            if (arr.length === 0) continue;
            arr.forEach((v) => params.append(`${String(qp)}[]`, String(v).trim()));
            continue;
        }
        if (val === undefined || val === null || String(val).trim() === '') continue;
        params.set(String(qp), String(val).trim());
    }

    const q = params.toString();
    const href = `${route('store.shop')}${q ? `?${q}` : ''}`;
    window.location.assign(href);
}

function onInertiaFinish() {
    scheduleWidget();
}

onMounted(() => {
    scheduleWidget();
    document.addEventListener('inertia:finish', onInertiaFinish);
});

onUnmounted(() => {
    document.removeEventListener('inertia:finish', onInertiaFinish);
    if (timer) clearTimeout(timer);
});
</script>

<template>
    <button
        v-if="visibleWidget"
        type="button"
        class="fixed bottom-24 right-4 z-30 flex items-center gap-2 rounded-full bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white shadow-lg ring-1 ring-white/10 transition hover:bg-stone-800 sm:bottom-24"
        :aria-label="ui.open_button_label || 'Open assistant'"
        @click="openAssistant"
    >
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-white/10" aria-hidden="true">?</span>
        <span>{{ ui.open_button_label || 'Need help?' }}</span>
    </button>

    <StoreBottomSheet :open="open" :title="ui.title || 'Storefront Assistant'" @close="closeAssistant(true)">
        <p v-if="ui.subtitle" class="mt-1 text-sm text-stone-500">
            {{ ui.subtitle }}
        </p>

        <div v-if="stepIndex === -1" class="mt-5 space-y-4">
            <p class="text-sm text-stone-700">
                {{ ui.welcome || 'Tell us a bit about what you need.' }}
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl bg-stone-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-stone-800"
                    @click="next"
                >
                    {{ ui.start_button_label || 'Start' }}
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-stone-100 px-4 py-3 text-sm font-semibold text-stone-800 ring-1 ring-stone-200 transition hover:bg-stone-200"
                    @click="closeAssistant(true)"
                >
                    {{ ui.close_button_label || 'Not now' }}
                </button>
            </div>
        </div>

        <div v-else class="mt-5">
            <div class="mb-4 flex items-center justify-between text-xs text-stone-500">
                <span>Step {{ stepIndex + 1 }} of {{ steps.length }}</span>
                <button type="button" class="underline underline-offset-2 hover:text-stone-800" @click="closeAssistant(true)">
                    {{ ui.close_button_label || 'Not now' }}
                </button>
            </div>

            <div v-if="steps[stepIndex]" class="space-y-3">
                <div>
                    <p class="text-sm font-semibold text-stone-900">
                        {{ steps[stepIndex].label }}
                        <span v-if="steps[stepIndex].required" class="text-red-600" aria-hidden="true">*</span>
                    </p>
                    <p v-if="steps[stepIndex].hint" class="mt-1 text-xs text-stone-500">
                        {{ steps[stepIndex].hint }}
                    </p>
                </div>

                <!-- number -->
                <div v-if="steps[stepIndex].type === 'number' && !steps[stepIndex].options">
                    <input
                        :id="`sa_${steps[stepIndex].key}`"
                        v-model="answers[steps[stepIndex].key]"
                        type="number"
                        inputmode="decimal"
                        class="w-full rounded-xl border border-stone-200 bg-white px-3 py-3 text-sm text-stone-900 shadow-sm outline-none ring-0 focus:border-stone-400"
                        :placeholder="steps[stepIndex].placeholder || ''"
                        :min="steps[stepIndex].min"
                        :max="steps[stepIndex].max"
                    />
                </div>

                <!-- badges (options) -->
                <div v-else-if="steps[stepIndex].options && stepOptions(steps[stepIndex]).length" class="space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="min-h-10 rounded-full bg-white px-4 py-2 text-xs font-semibold text-stone-700 shadow-sm ring-1 ring-stone-200 transition hover:ring-stone-300"
                            @click="clearAnswer(steps[stepIndex])"
                        >
                            Any
                        </button>
                        <button
                            v-if="isMultiple(steps[stepIndex])"
                            type="button"
                            class="min-h-10 rounded-full bg-white px-4 py-2 text-xs font-semibold text-stone-700 shadow-sm ring-1 ring-stone-200 transition hover:ring-stone-300"
                            @click="selectAll(steps[stepIndex])"
                        >
                            Select all
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="opt in stepOptions(steps[stepIndex])"
                            :key="String(opt.value)"
                            type="button"
                            class="min-h-10 rounded-full px-4 py-2 text-xs font-semibold transition"
                            :class="
                                isBadgeSelected(steps[stepIndex], opt.value)
                                    ? 'bg-stone-900 text-white'
                                    : 'bg-stone-50 text-stone-700 ring-1 ring-stone-200 hover:bg-stone-100'
                            "
                            @click="toggleBadge(steps[stepIndex], opt.value)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <!-- text -->
                <div v-else-if="steps[stepIndex].type === 'text'">
                    <input
                        :id="`sa_${steps[stepIndex].key}`"
                        v-model="answers[steps[stepIndex].key]"
                        type="text"
                        class="w-full rounded-xl border border-stone-200 bg-white px-3 py-3 text-sm text-stone-900 shadow-sm outline-none ring-0 focus:border-stone-400"
                        :placeholder="steps[stepIndex].placeholder || ''"
                    />
                </div>

                <!-- select -->
                <div v-else-if="steps[stepIndex].type === 'select'">
                    <select
                        :id="`sa_${steps[stepIndex].key}`"
                        v-model="answers[steps[stepIndex].key]"
                        class="w-full rounded-xl border border-stone-200 bg-white px-3 py-3 text-sm text-stone-900 shadow-sm outline-none ring-0 focus:border-stone-400"
                    >
                        <option value="">
                            — Select —
                        </option>
                        <option v-for="opt in steps[stepIndex].options || []" :key="String(opt.value)" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <!-- radio -->
                <div v-else-if="steps[stepIndex].type === 'radio'" class="space-y-2">
                    <label
                        v-for="opt in steps[stepIndex].options || []"
                        :key="String(opt.value)"
                        class="flex cursor-pointer items-center justify-between rounded-xl border border-stone-200 bg-white px-3 py-3 text-sm text-stone-900 shadow-sm transition hover:border-stone-300"
                    >
                        <span>{{ opt.label }}</span>
                        <input
                            v-model="answers[steps[stepIndex].key]"
                            type="radio"
                            class="h-4 w-4"
                            :name="`sa_${steps[stepIndex].key}`"
                            :value="opt.value"
                        />
                    </label>
                </div>

                <p v-if="errors[steps[stepIndex].key]" class="text-sm text-red-700">
                    {{ errors[steps[stepIndex].key] }}
                </p>

                <div class="mt-4 flex gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-stone-100 px-4 py-3 text-sm font-semibold text-stone-800 ring-1 ring-stone-200 transition hover:bg-stone-200"
                        @click="back"
                    >
                        {{ ui.back_button_label || 'Back' }}
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-stone-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-stone-800"
                        @click="next"
                    >
                        {{ stepIndex < steps.length - 1 ? ui.next_button_label || 'Next' : ui.submit_button_label || 'Show matches' }}
                    </button>
                </div>

                <p class="mt-4 text-[11px] text-stone-500">
                    Tip: you can also browse <Link :href="route('store.shop')" class="font-medium underline underline-offset-2">all shoes</Link>.
                </p>
            </div>
        </div>
    </StoreBottomSheet>
</template>

