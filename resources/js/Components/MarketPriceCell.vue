<script setup>
import { ref, computed, nextTick } from 'vue';
import axios from 'axios';
import { formatAdenaShort, formatAdenaFull } from '@/utils/adena.js';

const props = defineProps({
    itemId: { type: [Number, String], required: true },
    value: { type: [Number, String, null], default: null },
    // Game-default NPC sell price. Shown when no user-set value exists, and
    // also acts as the server-side floor: the API rejects market_price < this.
    fallbackPrice: { type: [Number, String, null], default: null },
    editable: { type: Boolean, default: true },
    updatedAt: { type: [String, null], default: null },
    updatedByName: { type: [String, null], default: null },
    localeTag: { type: String, default: 'en-US' },
    size: { type: String, default: 'md' },
    labelEdit: { type: String, default: 'Click to edit' },
    labelUpdated: { type: String, default: 'Updated by :user :ago' },
    labelEmpty: { type: String, default: '+ Set price' },
    labelBase: { type: String, default: 'Base price (NPC)' },
});

const emit = defineEmits(['update']);

const localValue = ref(props.value);
const editing = ref(false);
const draft = ref('');
const saving = ref(false);
const errorMsg = ref('');
const inputEl = ref(null);

const display = computed(() => {
    if (localValue.value === null || localValue.value === undefined || localValue.value === '') return null;
    const n = Number(localValue.value);
    return Number.isFinite(n) ? n : null;
});

const fallback = computed(() => {
    if (props.fallbackPrice === null || props.fallbackPrice === undefined || props.fallbackPrice === '') return null;
    const n = Number(props.fallbackPrice);
    return Number.isFinite(n) && n > 0 ? n : null;
});

const showingFallback = computed(() => display.value === null && fallback.value !== null);

const effective = computed(() => display.value !== null ? display.value : fallback.value);

const shortDisplay = computed(() => effective.value === null ? null : formatAdenaShort(effective.value, props.localeTag));

const tooltip = computed(() => {
    if (errorMsg.value) return errorMsg.value;
    if (display.value === null) {
        if (fallback.value !== null) {
            return props.labelBase + ' · ' + formatAdenaFull(fallback.value, props.localeTag) + ' a' + (props.editable ? ' · ' + props.labelEdit : '');
        }
        return props.editable ? props.labelEdit : '';
    }
    const parts = [formatAdenaFull(display.value, props.localeTag) + ' a'];
    if (props.updatedByName) {
        const ago = formatAgo(props.updatedAt);
        parts.push(props.labelUpdated
            .replace('{user}', props.updatedByName)
            .replace('{ago}', ago)
            .replace(':user', props.updatedByName)
            .replace(':ago', ago));
    }
    return parts.join(' · ');
});

const sizeClass = computed(() => props.size === 'sm' ? 'text-xs px-1.5 py-0.5' : 'text-sm px-2 py-1');

const startEdit = async () => {
    if (!props.editable || saving.value) return;
    draft.value = display.value === null ? '' : String(display.value);
    editing.value = true;
    await nextTick();
    inputEl.value?.focus();
    inputEl.value?.select();
};

const cancel = () => {
    editing.value = false;
    draft.value = '';
};

const save = async () => {
    if (!editing.value || saving.value) return;

    const raw = draft.value.trim();
    const parsedPrice = raw === '' ? null : Number(raw.replace(/[^\d]/g, ''));

    if (parsedPrice !== null && (!Number.isFinite(parsedPrice) || parsedPrice < 0)) {
        cancel();
        return;
    }
    if (parsedPrice === (display.value ?? null)) {
        cancel();
        return;
    }

    const previous = localValue.value;
    localValue.value = parsedPrice;
    editing.value = false;
    saving.value = true;
    errorMsg.value = '';

    try {
        const { data } = await axios.patch(
            route('api.items.market-price.update', props.itemId),
            { price: parsedPrice },
            { headers: { Accept: 'application/json' } }
        );
        emit('update', {
            itemId: Number(props.itemId),
            price: data.market_price,
            updatedAt: data.market_price_updated_at,
            updatedByName: data.market_price_updated_by_name,
        });
    } catch (e) {
        localValue.value = previous;
        const data = e?.response?.data;
        if (data?.errors?.price?.[0]) {
            errorMsg.value = data.errors.price[0];
        } else if (data?.message) {
            errorMsg.value = data.message;
        } else {
            errorMsg.value = '';
        }
    } finally {
        saving.value = false;
    }
};

const formatAgo = (iso) => {
    if (!iso) return '';
    const ms = Date.now() - new Date(iso).getTime();
    const s = Math.floor(ms / 1000);
    if (s < 60) return s + 's';
    const m = Math.floor(s / 60);
    if (m < 60) return m + 'm';
    const h = Math.floor(m / 60);
    if (h < 24) return h + 'h';
    const d = Math.floor(h / 24);
    return d + 'd';
};
</script>

<template>
    <span class="inline-block">
        <input
            v-if="editing"
            ref="inputEl"
            v-model="draft"
            type="text"
            inputmode="numeric"
            :disabled="saving"
            class="w-24 rounded border border-orange-400 bg-white dark:bg-zinc-800 dark:text-white font-cinzel text-orange-700 dark:text-orange-300 focus:ring-1 focus:ring-orange-400 focus:border-orange-400"
            :class="sizeClass"
            @keydown.enter.prevent="save"
            @keydown.esc.prevent="cancel"
            @blur="save"
        />
        <button
            v-else
            type="button"
            :title="tooltip"
            :disabled="!editable || saving"
            class="font-cinzel rounded transition-colors"
            :class="[
                sizeClass,
                errorMsg ? 'text-red-700 dark:text-red-300 border border-red-400/60' : '',
                !errorMsg && display !== null ? 'text-orange-700 dark:text-orange-300' : '',
                !errorMsg && showingFallback ? 'text-gray-500 dark:text-gray-400 italic border border-dotted border-gray-400/40' : '',
                !errorMsg && display === null && !showingFallback && editable ? 'text-orange-600/70 dark:text-orange-400/70 border border-dashed border-orange-400/40 hover:border-orange-400' : '',
                !errorMsg && display === null && !showingFallback && !editable ? 'text-gray-400 dark:text-gray-500' : '',
                editable
                    ? 'hover:bg-orange-100 dark:hover:bg-orange-900/30 cursor-pointer'
                    : 'cursor-default',
                saving ? 'opacity-50' : '',
            ]"
            @click="startEdit"
        >
            <span v-if="display !== null">{{ shortDisplay }}</span>
            <span v-else-if="showingFallback">{{ shortDisplay }}</span>
            <span v-else-if="editable">{{ labelEmpty }}</span>
            <span v-else>—</span>
        </button>
    </span>
</template>
