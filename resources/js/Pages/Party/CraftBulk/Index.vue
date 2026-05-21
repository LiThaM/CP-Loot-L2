<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    cp: { type: Object, required: true },
});

const page = usePage();
const appLocale = computed(() => page.props.app?.locale || 'es');
const $t = (key, params = {}) => {
    const raw = page.props.translations?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m);
};

const fmt = (n) => Number(n || 0).toLocaleString(appLocale.value === 'es' ? 'es-ES' : 'en-US');

// --- Recipe picker -------------------------------------------------------
const query = ref('');
const searching = ref(false);
const searchResults = ref([]);
const dropdownOpen = ref(false);

const orders = ref([]); // [{recipe: {id,name,output_qty,output_item}, qty}]

// Latest in-flight request — cancelled when a newer query comes in so the
// stale response can't overwrite the newer one (typing fast used to
// occasionally leave the dropdown showing results for a previous query).
let searchAbort = null;

const runSearch = debounce(async (q) => {
    if (q.length < 2) {
        searchResults.value = [];
        searching.value = false;
        return;
    }
    if (searchAbort) searchAbort.abort();
    searchAbort = new AbortController();
    try {
        const { data } = await axios.get(route('public.recipes.search'), {
            params: { q, chronicle: props.cp.chronicle },
            signal: searchAbort.signal,
        });
        searchResults.value = Array.isArray(data) ? data : [];
    } catch (e) {
        if (!axios.isCancel(e)) {
            searchResults.value = [];
        }
    } finally {
        searching.value = false;
    }
}, 300);

watch(query, (val) => {
    const q = String(val || '').trim();
    // Set the flag synchronously so the spinner appears immediately,
    // before the 300ms debounce window elapses.
    if (q.length >= 2) {
        searching.value = true;
        dropdownOpen.value = true;
    } else {
        searching.value = false;
        searchResults.value = [];
    }
    runSearch(q);
});

const addRecipe = (r) => {
    const existing = orders.value.find((o) => o.recipe.id === r.id);
    if (existing) { existing.qty += 1; }
    else { orders.value.push({ recipe: r, qty: 1 }); }
    query.value = '';
    searchResults.value = [];
    dropdownOpen.value = false;
};
const removeOrder = (idx) => orders.value.splice(idx, 1);
const updateQty = (idx, val) => {
    const n = Math.max(1, Math.min(999, parseInt(val) || 1));
    orders.value[idx].qty = n;
};

// --- Plan ----------------------------------------------------------------
const planResult = ref(null);
const planning = ref(false);
const planError = ref('');

const submitPlan = async () => {
    if (orders.value.length === 0) return;
    planning.value = true;
    planError.value = '';
    try {
        const { data } = await axios.post(route('party.craft_bulk.plan'), {
            orders: orders.value.map((o) => ({ recipe_id: o.recipe.id, qty: o.qty })),
        });
        planResult.value = data;
    } catch (e) {
        planError.value = e.response?.data?.message || $t('party.craft_bulk.error.generic');
    } finally {
        planning.value = false;
    }
};
const clearPlan = () => { planResult.value = null; planError.value = ''; expandedRows.value = {}; };

// Per-row toggle for the "Para qué se usa este material" breakdown.
const expandedRows = ref({});
const toggleRow = (id) => { expandedRows.value[id] = !expandedRows.value[id]; };

// Compact summary "Helmet, Crafted Leather, +1 más" used as a hint next
// to the material name when the row is collapsed.
const usageHint = (usages) => {
    if (!Array.isArray(usages) || usages.length === 0) return '';
    if (usages.length === 1) return usages[0].for;
    if (usages.length === 2) return `${usages[0].for}, ${usages[1].for}`;
    return `${usages[0].for}, ${usages[1].for} +${usages.length - 2}`;
};
</script>

<template>
    <Head :title="$t('party.craft_bulk.title')" />
    <MainLayout>
        <div class="max-w-[1400px] mx-auto px-4 py-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('party.craft_bulk.title') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('party.craft_bulk.subtitle', { chronicle: cp.chronicle }) }}</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- LEFT: recipe picker -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 space-y-4">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('party.craft_bulk.left.title') }}</h2>

                    <div class="relative">
                        <input v-model="query" type="text"
                               :placeholder="$t('party.craft_bulk.left.search_ph')"
                               @focus="dropdownOpen = true"
                               @blur="setTimeout(() => dropdownOpen = false, 200)"
                               class="w-full h-10 pl-3 pr-10 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
                        <!-- Spinner inside the input on the right -->
                        <svg v-if="searching" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin text-purple-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <div v-if="dropdownOpen && (searching || query.trim().length >= 2)"
                             class="absolute z-20 left-0 right-0 mt-1 max-h-72 overflow-y-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl">
                            <div v-if="searching" class="px-3 py-3 flex items-center gap-2 text-xs text-gray-500 italic">
                                <svg class="w-3.5 h-3.5 animate-spin text-purple-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ $t('party.craft_bulk.left.searching') }}
                            </div>
                            <template v-else>
                                <button v-for="r in searchResults" :key="r.id"
                                        type="button"
                                        @mousedown.prevent="addRecipe(r)"
                                        class="w-full px-3 py-2 flex items-center gap-2 text-sm hover:bg-purple-50 dark:hover:bg-purple-900/20 text-left">
                                    <img v-if="r.output_item?.image_url" :src="r.output_item.image_url" class="w-7 h-7 rounded" />
                                    <div v-else class="w-7 h-7 rounded bg-amber-500/10"></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-900 dark:text-white truncate">{{ r.name }}</div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-widest">{{ r.chronicle }} · {{ r.materials_count }} {{ $t('recipes.search.mats') }}</div>
                                    </div>
                                    <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-300">+</span>
                                </button>
                                <div v-if="searchResults.length === 0" class="px-3 py-3 text-xs italic text-gray-500">
                                    {{ $t('party.craft_bulk.left.no_results') }}
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div v-if="orders.length === 0" class="text-xs italic text-gray-400 py-4 text-center">{{ $t('party.craft_bulk.left.empty') }}</div>

                        <div v-for="(o, idx) in orders" :key="o.recipe.id"
                             class="flex items-center gap-3 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                            <img v-if="o.recipe.output_item?.image_url" :src="o.recipe.output_item.image_url" class="w-9 h-9 rounded" />
                            <div v-else class="w-9 h-9 rounded bg-amber-500/10"></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ o.recipe.name }}</div>
                                <div class="text-[10px] text-gray-500">{{ $t('party.craft_bulk.left.output_qty', { n: o.recipe.output_qty || 1 }) }}</div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="updateQty(idx, o.qty - 1)" class="w-7 h-7 rounded bg-gray-200 dark:bg-gray-700 text-sm font-bold">−</button>
                                <input type="number" min="1" max="999"
                                       :value="o.qty"
                                       @input="updateQty(idx, $event.target.value)"
                                       class="w-14 h-7 text-center rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm font-bold">
                                <button type="button" @click="updateQty(idx, o.qty + 1)" class="w-7 h-7 rounded bg-gray-200 dark:bg-gray-700 text-sm font-bold">+</button>
                            </div>
                            <button type="button" @click="removeOrder(idx)" class="w-7 h-7 rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">×</button>
                        </div>
                    </div>

                    <div class="pt-3 flex gap-2">
                        <button type="button" @click="submitPlan"
                                :disabled="orders.length === 0 || planning"
                                class="flex-1 h-10 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold uppercase tracking-widest text-xs disabled:opacity-40">
                            {{ planning ? $t('party.craft_bulk.left.calculating') : $t('party.craft_bulk.left.calculate') }}
                        </button>
                        <button type="button" @click="orders = []; clearPlan()"
                                :disabled="orders.length === 0"
                                class="px-4 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold uppercase tracking-widest text-xs disabled:opacity-30">
                            {{ $t('common.clear') }}
                        </button>
                    </div>
                </div>

                <!-- RIGHT: plan result -->
                <div class="space-y-4">
                    <div v-if="!planResult && !planError" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-8 text-center text-sm italic text-gray-400">
                        {{ $t('party.craft_bulk.right.idle') }}
                    </div>

                    <div v-if="planError" class="bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-2xl p-4 text-sm text-red-700 dark:text-red-300">
                        {{ planError }}
                    </div>

                    <template v-if="planResult">
                        <div v-if="planResult.warnings?.length" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-2xl p-3 text-xs text-amber-700 dark:text-amber-300 space-y-1">
                            <div v-for="(w, i) in planResult.warnings" :key="i">⚠ {{ w }}</div>
                        </div>

                        <!-- Totals -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <h2 class="text-sm font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('party.craft_bulk.right.totals_title') }}</h2>
                                <span class="text-[10px] text-gray-500">{{ planResult.totals?.length || 0 }} {{ $t('party.craft_bulk.right.materials') }}</span>
                            </div>
                            <table v-if="planResult.totals?.length" class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900 text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">{{ $t('party.craft_bulk.right.col.material') }}</th>
                                        <th class="px-3 py-2 text-right">{{ $t('party.craft_bulk.right.col.need') }}</th>
                                        <th class="px-3 py-2 text-right">{{ $t('party.craft_bulk.right.col.have') }}</th>
                                        <th class="px-3 py-2 text-right">{{ $t('party.craft_bulk.right.col.missing') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <template v-for="t in planResult.totals" :key="t.item_id">
                                        <tr :class="[t.missing > 0 ? 'bg-red-50/50 dark:bg-red-900/10' : '', (t.usages?.length ?? 0) > 0 ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/40' : '']"
                                            @click="(t.usages?.length ?? 0) > 0 && toggleRow(t.item_id)">
                                            <td class="px-3 py-2 flex items-center gap-2">
                                                <svg v-if="(t.usages?.length ?? 0) > 0" class="w-3 h-3 flex-shrink-0 text-gray-400 transition-transform"
                                                     :class="expandedRows[t.item_id] ? 'rotate-90' : ''"
                                                     fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span v-else class="w-3 h-3 flex-shrink-0"></span>
                                                <img v-if="t.image_url" :src="t.image_url" class="w-6 h-6 rounded" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-gray-900 dark:text-gray-200 truncate">{{ t.name }}</div>
                                                    <div v-if="(t.usages?.length ?? 0) > 0 && !expandedRows[t.item_id]" class="text-[10px] text-gray-500 truncate">
                                                        {{ $t('party.craft_bulk.right.usage_for') }} {{ usageHint(t.usages) }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono text-gray-700 dark:text-gray-300">{{ fmt(t.need) }}</td>
                                            <td class="px-3 py-2 text-right font-mono text-emerald-600 dark:text-emerald-300">{{ fmt(t.have) }}</td>
                                            <td class="px-3 py-2 text-right font-mono font-bold" :class="t.missing > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400'">{{ fmt(t.missing) }}</td>
                                        </tr>
                                        <tr v-if="expandedRows[t.item_id] && (t.usages?.length ?? 0) > 0" :key="'exp-'+t.item_id" class="bg-gray-50 dark:bg-gray-900/40">
                                            <td colspan="4" class="px-3 py-2">
                                                <div class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">{{ $t('party.craft_bulk.right.used_for') }}</div>
                                                <ul class="space-y-0.5 text-xs">
                                                    <li v-for="(u, i) in t.usages" :key="i" class="flex items-center gap-2">
                                                        <span class="font-mono text-amber-600 dark:text-amber-300 w-12 text-right tabular-nums">{{ fmt(u.qty) }}</span>
                                                        <span class="text-gray-400">→</span>
                                                        <span class="text-gray-700 dark:text-gray-300">{{ u.for }}</span>
                                                    </li>
                                                </ul>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <div v-else class="p-6 text-center text-xs italic text-emerald-600 dark:text-emerald-300">{{ $t('party.craft_bulk.right.all_covered') }}</div>
                        </div>

                        <!-- Sub-crafts -->
                        <div v-if="planResult.sub_crafts?.length" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-sm font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('party.craft_bulk.right.sub_crafts_title') }}</h2>
                                <p class="text-[10px] text-gray-500 mt-0.5">{{ $t('party.craft_bulk.right.sub_crafts_hint') }}</p>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                <div v-for="(sc, i) in planResult.sub_crafts" :key="i" class="px-4 py-2.5 flex items-center gap-3">
                                    <img v-if="sc.recipe?.output_item?.image_url" :src="sc.recipe.output_item.image_url" class="w-7 h-7 rounded" />
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-900 dark:text-gray-200 truncate">{{ sc.recipe?.output_item?.name || sc.recipe?.name }}</div>
                                        <div class="text-[10px] text-gray-500">
                                            {{ $t('party.craft_bulk.right.sub_craft_line', { n: sc.crafts, produces: sc.produces, item: sc.covers_item_name, missing: sc.covers_missing }) }}
                                        </div>
                                    </div>
                                    <span class="text-xs font-mono font-bold text-purple-600 dark:text-purple-300">× {{ sc.crafts }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
