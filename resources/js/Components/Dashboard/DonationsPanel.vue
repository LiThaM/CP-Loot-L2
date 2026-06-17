<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { showToast } from '@/utils/swal';
import TrackerRankingCard from '@/Components/Dashboard/TrackerRankingCard.vue';

const props = defineProps({
    trackerEnabled: { type: Boolean, default: false },
    // [{ id, name, points, entries }] — shown when trackerEnabled
    trackerRanking: { type: Array, default: () => [] },
    // [{ id, name, donated, donations }] — value ranking when NO tracker
    topDonations: { type: Array, default: () => [] },
    imageProofRequired: { type: Boolean, default: false },
    localeTag: { type: String, default: 'en-US' },
});

const isEs = computed(() => String(props.localeTag || '').toLowerCase().startsWith('es'));
const tr = (es, en) => (isEs.value ? es : en);

const formatAdenaShort = (val) => {
    const n = Number(val ?? 0);
    if (!Number.isFinite(n)) return '0';
    const sign = n < 0 ? '-' : '';
    const abs = Math.abs(n);
    if (abs >= 1_000_000) { const m = abs / 1_000_000; return `${sign}${Number.isInteger(m) ? m : Number(m.toFixed(1))}kk`; }
    if (abs >= 1_000) { const k = abs / 1_000; return `${sign}${Number.isInteger(k) ? k : Number(k.toFixed(1))}k`; }
    return `${sign}${Math.trunc(abs)}`;
};
const formatAdenaFull = (val) => {
    const n = Number(val ?? 0);
    return new Intl.NumberFormat(props.localeTag).format(Number.isFinite(n) ? Math.trunc(n) : 0);
};

const reloadDonations = () => router.reload({ only: ['cpInsights'] });

// ---- shared modal state ----
const mode = ref(null); // 'adena' | 'item' | null
const submitting = ref(false);
const proof = ref(null);
const proofName = computed(() => proof.value?.name || '');

const adenaAmount = ref('');

const itemQuery = ref('');
const itemResults = ref([]);
const itemLoading = ref(false);
const selectedItem = ref(null);
const itemQty = ref(1);
let searchTimer = null;

const openAdena = () => { mode.value = 'adena'; adenaAmount.value = ''; proof.value = null; };
const openItem = () => { mode.value = 'item'; itemQuery.value = ''; itemResults.value = []; selectedItem.value = null; itemQty.value = 1; proof.value = null; };
const close = () => { mode.value = null; };

const onProof = (e) => { proof.value = e.target.files?.[0] || null; };

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

const unitPrice = computed(() => {
    const it = selectedItem.value;
    if (!it) return 0;
    return Number(it.market_price ?? it.npc_sell_price ?? 0) || 0;
});
const estimatedValue = computed(() => unitPrice.value * Math.max(1, Number(itemQty.value || 1)));

const proofMissing = computed(() => props.imageProofRequired && !proof.value);

const submit = () => {
    if (submitting.value || proofMissing.value) return;
    const onSuccess = () => {
        showToast(tr('Donación enviada. Pendiente de aprobación del líder.', 'Donation sent. Pending leader approval.'));
        close();
        reloadDonations();
    };
    const opts = { preserveScroll: true, forceFormData: true, onSuccess, onFinish: () => { submitting.value = false; } };

    if (mode.value === 'adena') {
        const amount = parseInt(String(adenaAmount.value).replace(/[^\d]/g, '')) || 0;
        if (amount < 1) return;
        submitting.value = true;
        router.post(route('donations.adena'), { amount, image_proof: proof.value }, opts);
    } else if (mode.value === 'item') {
        if (!selectedItem.value) return;
        const quantity = Math.max(1, parseInt(itemQty.value || 1));
        submitting.value = true;
        router.post(route('donations.item'), { item_id: selectedItem.value.id, quantity, image_proof: proof.value }, opts);
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Ranking: tracker leaderboard when tracker is on, else donated value -->
        <TrackerRankingCard v-if="trackerEnabled" :ranking="trackerRanking" :locale-tag="localeTag" />

        <div v-else class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
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

            <div class="mt-2 flex items-center gap-2">
                <button @click="openAdena" class="flex-1 inline-flex items-center justify-center h-9 px-3 rounded-lg bg-gradient-to-r from-amber-600 to-orange-500 hover:from-amber-500 hover:to-orange-400 text-white text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-amber-950/20">
                    💰 {{ tr('Donar adena', 'Donate adena') }}
                </button>
                <button @click="openItem" class="flex-1 inline-flex items-center justify-center h-9 px-3 rounded-lg bg-white/70 hover:bg-white text-gray-900 border border-gray-200 dark:bg-gray-900/40 dark:hover:bg-gray-900/60 dark:text-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-widest transition">
                    📦 {{ tr('Donar item', 'Donate item') }}
                </button>
            </div>
        </div>

        <!-- Donate buttons for tracker CPs (sit under the tracker ranking) -->
        <div v-if="trackerEnabled" class="flex items-center gap-2">
            <button @click="openAdena" class="flex-1 inline-flex items-center justify-center h-9 px-3 rounded-lg bg-gradient-to-r from-emerald-600 to-green-500 hover:from-emerald-500 hover:to-green-400 text-white text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-emerald-950/20">
                💰 {{ tr('Donar adena (+puntos)', 'Donate adena (+points)') }}
            </button>
            <button @click="openItem" class="flex-1 inline-flex items-center justify-center h-9 px-3 rounded-lg bg-white/70 hover:bg-white text-gray-900 border border-gray-200 dark:bg-gray-900/40 dark:hover:bg-gray-900/60 dark:text-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-widest transition">
                📦 {{ tr('Donar item (+puntos)', 'Donate item (+points)') }}
            </button>
        </div>

        <!-- Donate modal (adena | item) -->
        <div v-if="mode" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="close">
            <div class="l2-panel w-full max-w-md rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-amber-700 to-orange-600 p-4 flex justify-between items-center">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">
                        {{ mode === 'adena' ? tr('💰 Donar adena', '💰 Donate adena') : tr('📦 Donar item', '📦 Donate item') }}
                    </h3>
                    <button @click="close" class="text-white/60 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-bold">
                        {{ trackerEnabled
                            ? tr('Al confirmarla el líder, sumará puntos del tracker.', 'When the leader confirms it, it adds tracker points.')
                            : tr('Se registra en /loot para que el líder la revise.', 'Recorded in /loot for the leader to review.') }}
                    </p>

                    <!-- ADENA -->
                    <div v-if="mode === 'adena'">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ tr('Cantidad de adena', 'Adena amount') }}</label>
                        <input v-model="adenaAmount" type="text" inputmode="numeric" placeholder="1000000"
                               class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl h-11 px-4 text-center font-black focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>

                    <!-- ITEM -->
                    <template v-else>
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
                                    {{ tr('Valor/ud', 'Value/unit') }}: {{ unitPrice > 0 ? formatAdenaShort(unitPrice) : tr('sin precio', 'no price') }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ tr('Cantidad', 'Quantity') }}</label>
                            <input v-model.number="itemQty" type="number" min="1"
                                   class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl h-11 px-4 text-center font-black focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                            <div v-if="selectedItem && estimatedValue > 0" class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 text-right">
                                ≈ {{ formatAdenaShort(estimatedValue) }} {{ tr('adena', 'adena') }}
                            </div>
                            <div v-else-if="selectedItem" class="text-[10px] text-amber-600 dark:text-amber-400 font-bold uppercase tracking-widest mt-2 text-right">
                                ⚠ {{ tr('Sin precio: no dará puntos/valor', 'No price: no points/value') }}
                            </div>
                        </div>
                    </template>

                    <!-- Image proof -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                            {{ tr('Captura', 'Screenshot') }}
                            <span v-if="imageProofRequired" class="text-red-500">*</span>
                            <span v-else class="text-gray-400 normal-case">({{ tr('opcional', 'optional') }})</span>
                        </label>
                        <label class="flex items-center justify-center w-full h-16 border-2 border-dashed rounded-xl cursor-pointer transition"
                               :class="proofMissing ? 'border-red-400/60' : 'border-gray-300 dark:border-gray-700'">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 truncate px-3">
                                {{ proofName || tr('Subir imagen', 'Upload image') }}
                            </span>
                            <input type="file" class="hidden" accept="image/*" @change="onProof">
                        </label>
                    </div>
                </div>
                <div class="p-5 pt-0 flex gap-3">
                    <button @click="close" class="flex-1 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 rounded-xl font-black uppercase tracking-widest text-[10px] transition">
                        {{ tr('Cancelar', 'Cancel') }}
                    </button>
                    <button @click="submit" :disabled="submitting || proofMissing"
                            class="flex-[2] py-3 bg-gradient-to-tr from-amber-600 to-orange-500 hover:from-amber-500 hover:to-orange-400 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg disabled:opacity-30">
                        {{ tr('Donar', 'Donate') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
