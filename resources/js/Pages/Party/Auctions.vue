<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import MainLayout from '@/Layouts/MainLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { formatAdenaShort, formatAdenaFull } from '@/utils/adena';
import { useSwal } from '@/utils/swal';

const props = defineProps({
    cp: { type: Object, required: true },
    auctions: { type: Array, default: () => [] },
    isLeader: { type: Boolean, default: false },
    me: { type: Object, required: true },
});

const page = usePage();
const translations = computed(() => page.props.translations || {});
const t = (key, fallbackOrParams = undefined, paramsArg = undefined) => {
    const hasFallback = typeof fallbackOrParams === 'string';
    const fallback = hasFallback ? fallbackOrParams : undefined;
    const params = (hasFallback ? paramsArg : fallbackOrParams) || {};
    const raw = translations.value?.[key] ?? fallback ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};

const swal = useSwal();

const activeAuctions = computed(() => props.auctions.filter(a => a.status === 'open'));
const closedAuctions = computed(() => props.auctions.filter(a => a.status === 'closed'));
const finishedAuctions = computed(() => props.auctions.filter(a => ['fulfilled', 'cancelled'].includes(a.status)));

const tab = ref('active');

const fmtCurrency = (amount, currency) => {
    if (amount === null || amount === undefined) return '—';
    if (currency === 'adena') return formatAdenaShort(amount);
    return Number(amount).toFixed(2) + ' pts';
};
const fmtCurrencyFull = (amount, currency) => {
    if (amount === null || amount === undefined) return '—';
    if (currency === 'adena') return formatAdenaFull(amount);
    return Number(amount).toFixed(2) + ' DKP';
};
const countdown = (endsAt) => {
    if (!endsAt) return '—';
    const ms = new Date(endsAt).getTime() - Date.now();
    if (ms <= 0) return t('auction.expired', 'Expirada');
    const h = Math.floor(ms / 3.6e6);
    const m = Math.floor((ms % 3.6e6) / 60000);
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
};

// --- Place bid modal ---
const bidModalAuction = ref(null);
const bidForm = useForm({ amount: 0 });
const minBid = computed(() => {
    const a = bidModalAuction.value;
    if (!a) return 0;
    if (a.current_bid !== null) return Math.round((a.current_bid + 0.01) * 100) / 100;
    return a.starting_bid;
});
const openBid = (auction) => {
    bidModalAuction.value = auction;
    bidForm.amount = minBid.value;
};
const submitBid = () => {
    if (!bidModalAuction.value) return;
    bidForm.post(route('party.auctions.bid', bidModalAuction.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            bidModalAuction.value = null;
            swal.fire({ icon: 'success', title: t('auction.bid.placed', 'Puja registrada'), timer: 1500, showConfirmButton: false });
        },
    });
};

// --- Open auction modal (leader) ---
const openModal = ref(false);
const openForm = useForm({
    item_id: null,
    amount: 1,
    currency: props.cp.tracker_enabled ? 'points' : 'adena',
    starting_bid: 1,
    buy_now_price: null,
    duration_minutes: 1440,
});
const itemSearch = ref('');
const itemResults = ref([]);
// Uses the same endpoint the loot session + warehouse buy modals use
// in Party/Index.vue (axios + route('api.items.search')). The /api/loot/items
// path I had here doesn't exist — that was the broken search the user
// reported.
const searchItems = async () => {
    if (!itemSearch.value || itemSearch.value.length < 2) { itemResults.value = []; return; }
    try {
        const { data } = await axios.get(route('api.items.search'), { params: { q: itemSearch.value, per_page: 10 } });
        // The endpoint returns { data: [...], current_page, ... } (paginated).
        itemResults.value = (data.data || data.items || data || []).slice(0, 10);
    } catch (_) {
        itemResults.value = [];
    }
};
const pickItem = (it) => { openForm.item_id = it.id; itemSearch.value = it.name; itemResults.value = []; };
const submitOpen = () => {
    openForm.post(route('party.auctions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            openModal.value = false;
            openForm.reset();
            itemSearch.value = '';
            swal.fire({ icon: 'success', title: t('auction.open.success', 'Subasta abierta'), timer: 1500, showConfirmButton: false });
        },
    });
};

// --- Leader actions ---
const fulfill = (auction) => {
    swal.fire({
        icon: 'question', title: t('auction.fulfill.confirm_title', '¿Entregar el item al ganador?'),
        text: t('auction.fulfill.confirm_text', 'Se asignará el item al ganador y se descontará su puja.'),
        showCancelButton: true,
    }).then(r => {
        if (!r.isConfirmed) return;
        router.post(route('party.auctions.fulfill', auction.id), {}, { preserveScroll: true });
    });
};
const cancel = (auction) => {
    swal.fire({
        icon: 'warning', title: t('auction.cancel.confirm_title', '¿Cancelar la subasta?'),
        text: t('auction.cancel.confirm_text', 'El item volverá al almacén. No se puede deshacer.'),
        showCancelButton: true,
    }).then(r => {
        if (!r.isConfirmed) return;
        router.post(route('party.auctions.cancel', auction.id), {}, { preserveScroll: true });
    });
};

const statusBadgeClass = (status) => {
    if (status === 'open') return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    if (status === 'closed') return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    if (status === 'fulfilled') return 'bg-blue-500/15 text-blue-700 dark:text-blue-300';
    return 'bg-gray-500/15 text-gray-500';
};
</script>

<template>
    <Head :title="t('auction.page.title', 'Subastas de CP')" />
    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">{{ t('auction.page.title', 'Subastas de CP') }}</h2>
                    <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">{{ cp.name }}</p>
                </div>
                <button v-if="isLeader" @click="openModal = true"
                        class="px-6 py-3 bg-gradient-to-tr from-amber-700 to-orange-600 hover:from-amber-600 hover:to-orange-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg shadow-amber-950/40 active:scale-95">
                    + {{ t('auction.open.cta', 'Abrir subasta') }}
                </button>
            </div>
        </template>
        <div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <!-- Available balances strip -->
            <div class="l2-panel p-4 rounded-2xl border-gray-200 dark:border-gray-800 flex items-center justify-between flex-wrap gap-3">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('auction.my_available', 'Tu disponible') }}</div>
                <div class="flex items-center gap-4">
                    <div v-if="cp.tracker_enabled" class="text-right">
                        <div class="text-[9px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">DKP</div>
                        <div class="text-base font-cinzel text-amber-700 dark:text-amber-300">{{ me.available_points.toFixed(2) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[9px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Adena</div>
                        <div class="text-base font-cinzel text-emerald-700 dark:text-emerald-300" v-tooltip="formatAdenaFull(me.available_adena)">{{ formatAdenaShort(me.available_adena) }}</div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 border-b border-gray-200 dark:border-gray-800">
                <button v-for="tabId in ['active','closed','finished']" :key="tabId" @click="tab = tabId"
                        class="px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 transition"
                        :class="tab === tabId ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">
                    {{ t('auction.tab.' + tabId, tabId) }}
                    <span class="ml-1 text-[10px] opacity-60">({{ tabId === 'active' ? activeAuctions.length : tabId === 'closed' ? closedAuctions.length : finishedAuctions.length }})</span>
                </button>
            </div>

            <!-- Auction cards -->
            <div class="space-y-3">
                <div v-for="a in (tab === 'active' ? activeAuctions : tab === 'closed' ? closedAuctions : finishedAuctions)" :key="a.id"
                     class="l2-panel rounded-2xl p-4 sm:p-6 border-gray-200 dark:border-gray-800">
                    <div class="flex flex-wrap items-center gap-4">
                        <img v-if="a.item?.image_url" :src="a.item.image_url" class="w-14 h-14 rounded-xl border border-gray-200 dark:border-gray-700 shrink-0 object-cover">
                        <div v-else class="w-14 h-14 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 shrink-0"></div>

                        <div class="flex-1 min-w-[180px]">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-gray-900 dark:text-white">{{ a.item?.name }}</span>
                                <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded" :class="statusBadgeClass(a.status)">{{ t('auction.status.' + a.status, a.status) }}</span>
                                <span class="text-xs text-gray-500">x{{ a.amount }}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                <span class="mr-3">{{ t('auction.starting_at', 'Inicial') }}: <span class="font-cinzel">{{ fmtCurrency(a.starting_bid, a.currency) }}</span></span>
                                <span v-if="a.buy_now_price" class="mr-3">{{ t('auction.buy_now', 'Buy now') }}: <span class="font-cinzel">{{ fmtCurrency(a.buy_now_price, a.currency) }}</span></span>
                                <span v-if="a.status === 'open'" class="text-amber-700 dark:text-amber-300 font-bold">{{ t('auction.ends_in', 'Termina en') }} {{ countdown(a.ends_at) }}</span>
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-[10px] uppercase tracking-widest text-gray-500">{{ t('auction.current_bid', 'Puja actual') }}</div>
                            <div class="font-cinzel font-bold text-amber-700 dark:text-amber-300 text-lg" v-tooltip="fmtCurrencyFull(a.current_bid, a.currency)">{{ fmtCurrency(a.current_bid, a.currency) }}</div>
                            <div v-if="a.current_bidder" class="flex items-center gap-2 justify-end mt-1">
                                <UserAvatar :user="a.current_bidder" size="xs" />
                                <span class="text-[10px] text-gray-500">{{ a.current_bidder.name }}</span>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button v-if="a.status === 'open' && a.current_bidder?.id !== me.id" @click="openBid(a)"
                                    class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-[10px] font-black uppercase tracking-widest">
                                {{ t('auction.bid.cta', 'Pujar') }}
                            </button>
                            <button v-if="isLeader && a.status === 'closed'" @click="fulfill(a)"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-black uppercase tracking-widest">
                                {{ t('auction.fulfill.cta', 'Entregar') }}
                            </button>
                            <button v-if="isLeader && ['open','closed'].includes(a.status)" @click="cancel(a)"
                                    class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg text-[10px] font-black uppercase tracking-widest">
                                {{ t('auction.cancel.cta', 'Cancelar') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="a.winner && a.status === 'fulfilled'" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-800 flex items-center gap-2 text-xs text-gray-500">
                        🏆 {{ t('auction.winner', 'Ganador') }}: <UserAvatar :user="a.winner" size="xs" /> <span class="font-bold">{{ a.winner.name }}</span>
                    </div>
                </div>

                <div v-if="(tab === 'active' ? activeAuctions : tab === 'closed' ? closedAuctions : finishedAuctions).length === 0"
                     class="text-center py-12 text-sm text-gray-500">
                    {{ t('auction.empty.' + tab, 'No hay subastas.') }}
                </div>
            </div>
        </div>

        <!-- Open auction modal (leader) — uses the same shape as the Sell / Assign modals in Party/Index.vue:
             l2-panel container + colored gradient header. -->
        <div v-if="openModal" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4 bg-black/90 backdrop-blur-sm" @click.self="openModal = false">
            <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-lg max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-amber-900 to-orange-800 p-4 flex justify-between items-center border-b border-amber-500/20">
                    <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">{{ t('auction.open.title', 'Nueva subasta') }}</div>
                    <button @click="openModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar flex-1">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('auction.open.item', 'Item') }} *</label>
                        <input v-model="itemSearch" @input="searchItems" type="text" :placeholder="t('auction.open.item_search', 'Buscar item…')"
                               class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 h-11 px-4 font-bold shadow-inner dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                        <div v-if="itemResults.length" class="mt-1 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-black max-h-40 overflow-y-auto custom-scrollbar">
                            <button v-for="it in itemResults" :key="it.id" type="button" @click="pickItem(it)"
                                    class="w-full text-left px-3 py-2 hover:bg-amber-500/10 text-sm flex items-center gap-2">
                                <img v-if="it.image_url" :src="it.image_url" class="w-6 h-6 rounded border border-gray-200 dark:border-gray-700">
                                <span class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ it.name }}</span>
                                <span class="text-[10px] text-gray-500 ml-auto">{{ it.grade || '—' }}</span>
                            </button>
                        </div>
                        <p v-if="openForm.errors.item_id" class="text-[10px] text-red-500 mt-1">{{ openForm.errors.item_id }}</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('auction.open.amount', 'Cantidad') }} *</label>
                            <input v-model.number="openForm.amount" type="number" min="1"
                                   class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 h-11 px-4 font-bold dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('auction.open.currency', 'Moneda') }} *</label>
                            <select v-model="openForm.currency" class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 h-11 px-4 font-bold dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                                <option v-if="cp.tracker_enabled" value="points">DKP points</option>
                                <option value="adena">Adena</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('auction.open.starting_bid', 'Puja inicial') }} *</label>
                            <input v-model.number="openForm.starting_bid" type="number" min="1" step="0.01"
                                   class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 h-11 px-4 font-bold dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('auction.open.buy_now', 'Buy now (opcional)') }}</label>
                            <input v-model.number="openForm.buy_now_price" type="number" min="0" step="0.01" placeholder="—"
                                   class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 h-11 px-4 font-bold dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('auction.open.duration', 'Duración') }} *</label>
                        <select v-model.number="openForm.duration_minutes" class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 h-11 px-4 font-bold dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                            <option :value="15">15 min</option>
                            <option :value="60">1 hora</option>
                            <option :value="360">6 horas</option>
                            <option :value="1440">24 horas</option>
                            <option :value="4320">3 días</option>
                        </select>
                    </div>
                </div>

                <div class="p-6 pt-0 flex space-x-4">
                    <button @click="openModal = false" class="flex-1 py-3.5 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ t('common.cancel', 'Cancelar') }}</button>
                    <button @click="submitOpen" :disabled="!openForm.item_id || openForm.processing"
                            class="flex-[2] py-3.5 bg-gradient-to-tr from-amber-700 to-orange-600 hover:from-amber-600 hover:to-orange-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-amber-950/50 disabled:opacity-30">
                        {{ t('common.save', 'Guardar') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Place bid modal — purple gradient header (matches the "bid action" purple accent of regular CP UI) -->
        <div v-if="bidModalAuction" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4 bg-black/90 backdrop-blur-sm" @click.self="bidModalAuction = null">
            <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-sm rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">{{ t('auction.bid.title', 'Pujar') }} — {{ bidModalAuction.item?.name }}</div>
                    <button @click="bidModalAuction = null" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-amber-500/5 border border-amber-500/20 p-3 text-center">
                            <div class="text-[9px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">{{ t('auction.bid.min', 'Mínimo') }}</div>
                            <div class="font-cinzel text-amber-700 dark:text-amber-300 mt-1">{{ fmtCurrency(minBid, bidModalAuction.currency) }}</div>
                        </div>
                        <div class="rounded-xl bg-emerald-500/5 border border-emerald-500/20 p-3 text-center">
                            <div class="text-[9px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">{{ t('auction.my_available', 'Tu disponible') }}</div>
                            <div class="font-cinzel text-emerald-700 dark:text-emerald-300 mt-1">{{ bidModalAuction.currency === 'points' ? me.available_points.toFixed(2) : formatAdenaShort(me.available_adena) }}</div>
                        </div>
                    </div>
                    <input v-model.number="bidForm.amount" type="number" :min="minBid" step="0.01"
                           class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-500 h-12 px-4 font-bold text-lg dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                    <p v-if="bidForm.errors.amount" class="text-[10px] text-red-500">{{ bidForm.errors.amount }}</p>
                </div>
                <div class="p-6 pt-0 flex gap-3">
                    <button @click="bidModalAuction = null" class="flex-1 py-3.5 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ t('common.cancel', 'Cancelar') }}</button>
                    <button @click="submitBid" :disabled="bidForm.processing"
                            class="flex-[2] py-3.5 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30">
                        {{ t('auction.bid.confirm', 'Pujar') }}
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
