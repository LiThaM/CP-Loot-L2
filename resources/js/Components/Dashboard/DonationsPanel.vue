<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useSwal, showToast } from '@/utils/swal';

const props = defineProps({
    // { target, donated_7d, adena_7d, items_7d, can_set } | null
    donationGoal: { type: Object, default: null },
    // [{ id, name, donated, donations }]
    topDonations: { type: Array, default: () => [] },
    localeTag: { type: String, default: 'en-US' },
});

const isEs = computed(() => String(props.localeTag || '').toLowerCase().startsWith('es'));
const tr = (es, en) => (isEs.value ? es : en);

const goal = computed(() => props.donationGoal || {});
const target = computed(() => Number(goal.value.target || 0));
const donated = computed(() => Number(goal.value.donated_7d || 0));
const canSet = computed(() => !!goal.value.can_set);
const pct = computed(() => {
    if (target.value <= 0) return 0;
    return Math.min(100, Math.round((donated.value / target.value) * 100));
});
const reached = computed(() => target.value > 0 && donated.value >= target.value);

const formatAdenaShort = (val) => {
    const n = Number(val ?? 0);
    if (!Number.isFinite(n)) return '0';
    const sign = n < 0 ? '-' : '';
    const abs = Math.abs(n);
    if (abs >= 1_000_000) {
        const m = abs / 1_000_000;
        return `${sign}${Number.isInteger(m) ? m : Number(m.toFixed(1))}kk`;
    }
    if (abs >= 1_000) {
        const k = abs / 1_000;
        return `${sign}${Number.isInteger(k) ? k : Number(k.toFixed(1))}k`;
    }
    return `${sign}${Math.trunc(abs)}`;
};
const formatAdenaFull = (val) => {
    const n = Number(val ?? 0);
    return new Intl.NumberFormat(props.localeTag).format(Number.isFinite(n) ? Math.trunc(n) : 0);
};

const reloadDonations = () => router.reload({ only: ['cpInsights', 'donationGoal'] });

const setGoal = async () => {
    const { value } = await useSwal().fire({
        title: tr('Objetivo semanal de donaciones', 'Weekly donation goal'),
        text: tr('Meta de adena (incluye items donados). Vacío o 0 para quitarla.',
                 'Adena target (item donations count too). Empty or 0 to clear.'),
        input: 'number',
        inputValue: target.value || '',
        inputAttributes: { min: 0, step: 1, placeholder: '50000000' },
        showCancelButton: true,
        confirmButtonText: tr('Guardar', 'Save'),
        cancelButtonText: tr('Cancelar', 'Cancel'),
    });
    if (value === undefined) return; // dismissed
    router.patch(route('donations.weekly_goal'), { goal: parseInt(value || 0) || 0 }, {
        preserveScroll: true,
        onSuccess: () => { showToast(tr('Objetivo actualizado', 'Goal updated')); reloadDonations(); },
    });
};

const donateAdena = async () => {
    const { value: amount } = await useSwal().fire({
        title: tr('Donar adena a la CP', 'Donate adena to the CP'),
        text: tr('¿Cuánta adena quieres donar al fondo común?', 'How much adena to donate to the common fund?'),
        input: 'number',
        inputAttributes: { min: 1, step: 1, placeholder: '1000000' },
        showCancelButton: true,
        confirmButtonText: tr('Donar', 'Donate'),
        cancelButtonText: tr('Cancelar', 'Cancel'),
        inputValidator: (v) => (!v || parseInt(v) < 1) ? tr('Introduce una cantidad válida', 'Enter a valid amount') : undefined,
    });
    if (amount) {
        router.post(route('adena.donate'), { amount: parseInt(amount) }, {
            preserveScroll: true,
            onSuccess: () => { showToast(tr('¡Gracias por tu donación!', 'Thanks for your donation!')); reloadDonations(); },
        });
    }
};

// ---- Item donation modal ----
const itemModalOpen = ref(false);
const itemQuery = ref('');
const itemResults = ref([]);
const itemLoading = ref(false);
const selectedItem = ref(null);
const itemQty = ref(1);
const itemSubmitting = ref(false);
let searchTimer = null;

const openItemModal = () => {
    itemModalOpen.value = true;
    itemQuery.value = '';
    itemResults.value = [];
    selectedItem.value = null;
    itemQty.value = 1;
};
const closeItemModal = () => { itemModalOpen.value = false; };

const onSearchInput = () => {
    selectedItem.value = null;
    clearTimeout(searchTimer);
    const q = itemQuery.value.trim();
    if (q.length < 3) { itemResults.value = []; itemLoading.value = false; return; }
    itemLoading.value = true;
    searchTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('api.items.search'), { params: { q, per_page: 8 } });
            itemResults.value = data.items || [];
        } catch (_) { itemResults.value = []; }
        finally { itemLoading.value = false; }
    }, 300);
};

const pickItem = (it) => { selectedItem.value = it; itemResults.value = []; itemQuery.value = it.name; };

const estimatedValue = computed(() => {
    const unit = Number(selectedItem.value?.market_price ?? 0);
    return unit > 0 ? unit * Math.max(1, Number(itemQty.value || 1)) : 0;
});

const submitItemDonation = () => {
    if (!selectedItem.value || itemSubmitting.value) return;
    const qty = Math.max(1, parseInt(itemQty.value || 1));
    itemSubmitting.value = true;
    router.post(route('donations.item'), { item_id: selectedItem.value.id, quantity: qty }, {
        preserveScroll: true,
        onSuccess: () => {
            showToast(tr('¡Gracias por tu donación de items!', 'Thanks for your item donation!'));
            closeItemModal();
            reloadDonations();
        },
        onFinish: () => { itemSubmitting.value = false; },
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Weekly goal KPI -->
        <div class="l2-panel p-5 rounded-lg border border-amber-500/20 bg-gradient-to-b from-amber-500/5 to-transparent backdrop-blur">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-700 dark:text-amber-300/90">
                        🎯 {{ tr('Objetivo semanal', 'Weekly goal') }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                        {{ tr('Donaciones (adena + items) · últimos 7 días', 'Donations (adena + items) · last 7 days') }}
                    </div>
                </div>
                <button v-if="canSet" @click="setGoal"
                        class="text-[10px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-600 dark:text-amber-300 dark:hover:text-amber-200 transition">
                    {{ target > 0 ? tr('Editar meta', 'Edit goal') : tr('Fijar meta', 'Set goal') }}
                </button>
            </div>

            <template v-if="target > 0">
                <div class="flex items-end justify-between mb-2">
                    <div class="text-2xl font-cinzel" :class="reached ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-300'"
                         v-tooltip="formatAdenaFull(donated)">
                        {{ formatAdenaShort(donated) }}
                    </div>
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-widest" v-tooltip="formatAdenaFull(target)">
                        / {{ formatAdenaShort(target) }} · {{ pct }}%
                    </div>
                </div>
                <div class="h-3 w-full rounded-full bg-gray-200 dark:bg-black/40 overflow-hidden border border-gray-300/40 dark:border-gray-700/60">
                    <div class="h-full rounded-full transition-all duration-700"
                         :class="reached ? 'bg-gradient-to-r from-emerald-500 to-green-400' : 'bg-gradient-to-r from-amber-500 to-orange-400'"
                         :style="{ width: pct + '%' }"></div>
                </div>
                <div v-if="reached" class="mt-2 text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">
                    ✓ {{ tr('¡Objetivo alcanzado!', 'Goal reached!') }}
                </div>
            </template>
            <template v-else>
                <div class="text-2xl font-cinzel text-amber-700 dark:text-amber-300" v-tooltip="formatAdenaFull(donated)">
                    {{ formatAdenaShort(donated) }}
                </div>
                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1">
                    {{ canSet ? tr('Fija una meta para ver el progreso', 'Set a goal to track progress') : tr('Donado esta semana', 'Donated this week') }}
                </div>
            </template>

            <div class="flex items-center gap-2 mt-4">
                <button @click="donateAdena"
                        class="flex-1 inline-flex items-center justify-center h-9 px-3 rounded-lg bg-gradient-to-r from-amber-600 to-orange-500 hover:from-amber-500 hover:to-orange-400 text-white text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-amber-950/20">
                    💰 {{ tr('Donar adena', 'Donate adena') }}
                </button>
                <button @click="openItemModal"
                        class="flex-1 inline-flex items-center justify-center h-9 px-3 rounded-lg bg-white/70 hover:bg-white text-gray-900 border border-gray-200 dark:bg-gray-900/40 dark:hover:bg-gray-900/60 dark:text-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-widest transition">
                    📦 {{ tr('Donar item', 'Donate item') }}
                </button>
            </div>
        </div>

        <!-- Donations ranking -->
        <div class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-700 dark:text-purple-300/80 mb-3">
                🏆 {{ tr('Ranking de donaciones', 'Donations ranking') }}
                <span class="text-gray-500">· {{ tr('7 días', '7 days') }}</span>
            </div>
            <div v-if="topDonations.length === 0" class="text-sm text-gray-600 dark:text-gray-500 italic py-4 text-center">
                {{ tr('Aún no hay donaciones', 'No donations yet') }}
            </div>
            <div v-else class="space-y-2">
                <div v-for="(d, idx) in topDonations.slice(0, 5)" :key="d.id"
                     class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center text-[10px] font-black text-white shrink-0"
                             :class="idx === 0 ? 'bg-gradient-to-tr from-amber-500 to-orange-400' : 'bg-gradient-to-tr from-purple-600/35 to-blue-600/35 border border-purple-500/20'">
                            {{ idx + 1 }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] font-black text-gray-900 dark:text-white truncate">{{ d.name }}</div>
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                                {{ Number(d.donations || 0) }} {{ tr('donaciones', 'donations') }}
                            </div>
                        </div>
                    </div>
                    <div class="text-sm font-black font-cinzel text-amber-700 dark:text-amber-300 shrink-0" v-tooltip="formatAdenaFull(d.donated || 0)">
                        {{ formatAdenaShort(d.donated || 0) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Donate-item modal -->
        <div v-if="itemModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="closeItemModal">
            <div class="l2-panel w-full max-w-md rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-amber-700 to-orange-600 p-4 flex justify-between items-center">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">📦 {{ tr('Donar item a la CP', 'Donate item to the CP') }}</h3>
                    <button @click="closeItemModal" class="text-white/60 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="relative">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ tr('Buscar item', 'Search item') }}</label>
                        <input v-model="itemQuery" @input="onSearchInput" type="text" :placeholder="tr('Mínimo 3 letras…', 'At least 3 letters…')"
                               class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl h-11 px-4 focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        <div v-if="itemResults.length || itemLoading" class="absolute z-50 mt-2 w-full bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto dark:bg-gray-950 dark:border-gray-800">
                            <div v-if="itemLoading" class="p-3 text-sm text-gray-500 italic">{{ tr('Buscando…', 'Searching…') }}</div>
                            <button v-for="it in itemResults" :key="it.id" type="button" @click="pickItem(it)"
                                    class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-amber-50 dark:hover:bg-amber-900/20 transition">
                                <img v-if="it.image_url" :src="it.image_url" class="w-7 h-7 rounded border border-gray-200 dark:border-gray-700 shrink-0">
                                <div v-else class="w-7 h-7 rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60 shrink-0"></div>
                                <span class="flex-1 min-w-0 text-xs font-bold text-gray-900 dark:text-gray-100 truncate">{{ it.name }}</span>
                                <span v-if="it.grade" class="text-[9px] font-black uppercase text-purple-600 dark:text-purple-300">{{ it.grade }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="selectedItem" class="flex items-center gap-3 p-3 rounded-xl border border-amber-300/40 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/20">
                        <img v-if="selectedItem.image_url" :src="selectedItem.image_url" class="w-9 h-9 rounded border border-gray-200 dark:border-gray-700">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-black text-gray-900 dark:text-white truncate">{{ selectedItem.name }}</div>
                            <div class="text-[9px] text-gray-500 uppercase tracking-widest">
                                {{ tr('Precio mercado', 'Market price') }}: {{ selectedItem.market_price != null ? formatAdenaShort(selectedItem.market_price) : '—' }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ tr('Cantidad', 'Quantity') }}</label>
                        <input v-model.number="itemQty" type="number" min="1"
                               class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl h-11 px-4 text-center font-black focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        <div v-if="estimatedValue > 0" class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 text-right">
                            ≈ {{ formatAdenaShort(estimatedValue) }} {{ tr('adena', 'adena') }}
                        </div>
                    </div>
                </div>
                <div class="p-5 pt-0 flex gap-3">
                    <button @click="closeItemModal" class="flex-1 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 rounded-xl font-black uppercase tracking-widest text-[10px] transition">
                        {{ tr('Cancelar', 'Cancel') }}
                    </button>
                    <button @click="submitItemDonation" :disabled="!selectedItem || itemSubmitting"
                            class="flex-[2] py-3 bg-gradient-to-tr from-amber-600 to-orange-500 hover:from-amber-500 hover:to-orange-400 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg disabled:opacity-30">
                        {{ tr('Donar', 'Donate') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
