<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import MarketPriceCell from '@/Components/MarketPriceCell.vue';
import { computed, ref, watch, reactive } from 'vue';
import axios from 'axios';
import { formatAdenaShort, formatAdenaFull } from '@/utils/adena.js';

const page = usePage();
const translations = computed(() => page.props.translations || {});
const t = (key, params = {}) => {
    const raw = translations.value?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p) => (Object.prototype.hasOwnProperty.call(params, p) ? String(params[p]) : m));
};
const appName = computed(() => page.props.app?.name || t('app.name'));

// Search state
const query = ref('');
const selectedChronicle = ref('');
const results = ref([]);
const loading = ref(false);
const dropdownOpen = ref(false);
const chronicles = ['C1','C2','C3','C4','C5','IL','CT1','GF','HB','Classic','LU4'];
let searchTimeout = null;

// Selected recipe
const selected = ref(null);
const tree = ref(null);
const treeLoading = ref(false);

// Calculator state
const haveAmounts = reactive({});   // how many the user already has per item_id
const craftAmounts = reactive({});  // how many to craft per craftable item_id

const doSearch = () => {
    clearTimeout(searchTimeout);
    const val = query.value.trim();
    if (val.length < 2) { results.value = []; dropdownOpen.value = false; return; }
    loading.value = true;
    const params = { q: val };
    if (selectedChronicle.value) params.chronicle = selectedChronicle.value;
    searchTimeout = setTimeout(async () => {
        try {
            const { data } = await axios.get('/api/public/recipes/search', { params });
            results.value = data;
            dropdownOpen.value = data.length > 0;
        } catch { results.value = []; }
        loading.value = false;
    }, 300);
};

watch(query, doSearch);
watch(selectedChronicle, doSearch);

const selectRecipe = async (recipe) => {
    selected.value = recipe;
    dropdownOpen.value = false;
    query.value = '';
    tree.value = null;
    Object.keys(haveAmounts).forEach(k => delete haveAmounts[k]);
    Object.keys(craftAmounts).forEach(k => delete craftAmounts[k]);
    treeLoading.value = true;
    try {
        const { data } = await axios.get(`/api/public/recipes/${recipe.id}/tree`, { params: { depth: 4 } });
        tree.value = data;
    } catch { tree.value = null; }
    treeLoading.value = false;
};

const clearRecipe = () => {
    selected.value = null;
    tree.value = null;
    Object.keys(haveAmounts).forEach(k => delete haveAmounts[k]);
};

const flattenTree = (nodes, depth = 0) => {
    const out = [];
    for (const n of (nodes || [])) {
        out.push({ ...n, depth });
        if (n.children?.length) out.push(...flattenTree(n.children, depth + 1));
    }
    return out;
};

// Build craftable recipes map from tree nodes: { itemId: { ratios: [{item_id, qty, name, image_url}] } }
// `qty` is the per-unit-of-parent ratio. Prefer the value from the backend
// (per_parent_qty) — falls back to dividing need/parentNeed for older trees.
const buildCraftableMap = (nodes) => {
    const map = {};
    const walk = (list) => {
        for (const n of list) {
            if (n.children?.length && n.craft_recipe_id && !n.is_recipe && !map[n.item_id]) {
                const parentNeed = n.need || 1;
                map[n.item_id] = n.children.filter(c => !c.is_recipe).map(c => ({
                    item_id: c.item_id,
                    qty: (c.per_parent_qty ?? Math.round(c.need / parentNeed)) || 1,
                    name: c.name, image_url: c.image_url,
                    market_price: c.market_price ?? null,
                }));
            }
            if (n.children?.length) walk(n.children);
        }
    };
    walk(nodes || []);
    return map;
};

// Build the interactive material list: items with sliders for craftables, sub-items appear when slider > 0
const getCalcItems = (nodes) => {
    if (!nodes) return [];
    const craftMap = buildCraftableMap(nodes);
    const items = [];

    const addItems = (list, depth) => {
        for (const n of list) {
            if (n.is_recipe) continue;
            const isCraftable = !!craftMap[n.item_id];
            const toCraft = parseInt(craftAmounts[n.item_id]) || 0;
            const needBuy = isCraftable ? Math.max(0, n.need - toCraft) : n.need;

            items.push({
                item_id: n.item_id, name: n.name, image_url: n.image_url,
                market_price: n.market_price ?? null,
                need: n.need, needBuy, craftable: isCraftable, toCraft,
                per_parent_qty: n.per_parent_qty ?? null,
                depth,
            });

            // If user chose to craft some, show sub-materials indented.
            // We pass `per_parent_qty: s.qty` so the calculator shows the
            // recipe ratio next to the total ("25 (5/unit)" rather than just
            // "25" — makes it obvious that 5 VoP × 5 Stones each = 25).
            if (isCraftable && toCraft > 0) {
                const subs = craftMap[n.item_id];
                const subNodes = subs.map(s => ({
                    ...s, item_id: s.item_id, need: s.qty * toCraft,
                    children: [], craft_recipe_id: craftMap[s.item_id] ? 1 : null,
                    per_parent_qty: s.qty,
                }));
                addItems(subNodes, depth + 1);
            }
        }
    };

    addItems(nodes.filter(n => !n.is_recipe), 0);
    return items;
};

// Aggregate final needed resources (leaves after all craft resolutions)
const getNeededResources = (nodes) => {
    if (!nodes) return [];
    const items = getCalcItems(nodes);
    const map = {};
    for (const it of items) {
        // Only count items that aren't fully crafted away (needBuy > 0) and have no sub-expansion
        // Actually we need leaves: items where the user is NOT crafting them (toCraft === 0 or not craftable)
        if (!it.craftable || it.toCraft === 0) {
            if (!map[it.item_id]) map[it.item_id] = { ...it, need: 0 };
            map[it.item_id].need += it.needBuy;
        } else if (it.needBuy > 0) {
            // Partially crafted: the "buy" portion
            if (!map[it.item_id]) map[it.item_id] = { ...it, need: 0 };
            map[it.item_id].need += it.needBuy;
        }
    }
    return Object.values(map).filter(r => r.need > 0);
};

const getMissing = (itemId, need) => {
    const have = parseInt(haveAmounts[itemId]) || 0;
    return Math.max(0, need - have);
};

const fmt = (n) => n?.toLocaleString() ?? '0';

const isAuthenticated = computed(() => !!page.props.auth?.user);
const localeTag = computed(() => (page.props.app?.locale === 'es' ? 'es-ES' : 'en-US'));

const propagatePriceOnTree = (itemId, price) => {
    if (!tree.value?.nodes) return;
    const walk = (list) => {
        for (const n of list) {
            if (Number(n.item_id) === Number(itemId)) n.market_price = price;
            if (n.children?.length) walk(n.children);
        }
    };
    walk(tree.value.nodes);
};

const onRecipePriceUpdate = (payload) => {
    propagatePriceOnTree(payload.itemId, payload.price);
};

const totalRecipeCost = computed(() => {
    if (!tree.value?.nodes) return { mats: 0, fee: 0, total: 0, anyPriced: false };
    const leaves = getNeededResources(tree.value.nodes);
    let mats = 0;
    let anyPriced = false;
    for (const r of leaves) {
        if (r.market_price != null) {
            mats += Number(r.market_price) * Number(r.need);
            anyPriced = true;
        }
    }
    const fee = Number(tree.value.recipe?.adena_fee || 0);
    return { mats, fee, total: mats + fee, anyPriced };
});
</script>

<template>
    <Head>
        <title>Recipe Explorer - {{ appName }}</title>
    </Head>

    <div class="min-h-screen bg-gray-950 text-gray-200 font-sans">
        <!-- Header -->
        <header class="border-b border-white/5 bg-black/40 backdrop-blur-xl sticky top-0 z-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-3 group">
                    <div class="w-9 h-9 rounded-lg border border-white/10 overflow-hidden bg-gray-950/80">
                        <ApplicationLogo class="w-full h-full object-cover group-hover:scale-110 transition-transform" />
                    </div>
                    <span class="text-sm font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-indigo-400 font-cinzel hidden sm:block">{{ appName }}</span>
                </Link>
                <div class="flex items-center gap-4">
                    <span class="text-xs font-black tracking-[0.2em] uppercase text-amber-400 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ $t('recipes.header.title') }}
                    </span>
                    <Link v-if="$page.props.auth?.user" :href="route('dashboard')" class="px-4 py-2 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-300 text-xs font-bold tracking-widest uppercase hover:bg-purple-600/30 transition-all">
                        {{ $t('welcome.hero.cta.dashboard') }}
                    </Link>
                    <template v-else>
                        <Link :href="route('login')" class="px-4 py-2 rounded-lg border border-white/10 text-gray-300 text-xs font-bold tracking-widest uppercase hover:bg-white/5 transition-all">
                            {{ $t('welcome.hero.cta.login') }}
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
            <!-- Search Section -->
            <div class="text-center mb-8">
                <h1 class="text-2xl sm:text-4xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-amber-200 to-amber-500 font-cinzel">{{ $t('recipes.search.title') }}</h1>
                <p class="mt-2 text-sm text-gray-400 max-w-md mx-auto">{{ $t('recipes.search.subtitle') }}</p>
            </div>

            <div class="max-w-2xl mx-auto relative mb-10">
                <div class="flex gap-2">
                    <select v-model="selectedChronicle" class="bg-black/40 border border-white/10 text-gray-300 rounded-xl focus:ring-1 focus:ring-amber-500 focus:border-amber-500 text-sm py-4 px-3 w-32 shrink-0">
                        <option value="">{{ $t('recipes.search.all') }}</option>
                        <option v-for="c in chronicles" :key="c" :value="c">{{ c }}</option>
                    </select>
                    <div class="relative flex-1">
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="query" type="text" placeholder="Blue Wolf Helmet, Doom Plate Armor, Enria..." class="w-full bg-black/40 border border-white/10 text-gray-100 rounded-xl focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all pl-12 pr-12 py-4 text-sm placeholder-gray-600" @focus="results.length && (dropdownOpen = true)" @blur="setTimeout(() => dropdownOpen = false, 200)" />
                        <svg v-if="loading" class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 animate-spin text-amber-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </div>
                </div>

                <!-- Dropdown -->
                <transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div v-if="dropdownOpen && results.length" class="absolute z-30 w-full mt-2 rounded-xl bg-gray-900/98 border border-white/10 backdrop-blur-xl overflow-hidden shadow-2xl max-h-80 overflow-y-auto">
                        <button v-for="r in results" :key="r.id" type="button" @mousedown.prevent="selectRecipe(r)" class="w-full px-4 py-3.5 flex items-center gap-3 hover:bg-white/5 border-b border-white/5 last:border-0 transition-colors text-left">
                            <img v-if="r.output_item?.image_url" :src="r.output_item.image_url" class="w-9 h-9 rounded-lg border border-white/10" />
                            <div v-else class="w-9 h-9 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-white truncate">{{ r.name }}</div>
                                <div class="text-[10px] text-gray-500 flex items-center gap-2">
                                    <span class="uppercase font-bold tracking-wider">{{ r.chronicle }}</span>
                                    <span>{{ r.materials_count }} {{ $t('recipes.search.mats') }}</span>
                                </div>
                            </div>
                            <div class="text-xs font-mono font-bold" :class="r.success_rate >= 100 ? 'text-green-400' : 'text-yellow-400'">{{ r.success_rate }}%</div>
                        </button>
                    </div>
                </transition>
            </div>

            <!-- Selected Recipe -->
            <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0">
                <div v-if="selected" class="space-y-6">
                    <!-- Header -->
                    <div class="flex items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-gray-900/60 border border-white/5 backdrop-blur-sm flex-col sm:flex-row">
                        <div class="flex items-center gap-4">
                            <img v-if="selected.output_item?.image_url" :src="selected.output_item.image_url" class="w-14 h-14 rounded-xl border border-white/10 shadow-lg" />
                            <div>
                                <div class="text-xl font-black tracking-widest text-white">{{ selected.name }}</div>
                                <div class="flex items-center gap-3 mt-1 text-xs">
                                    <span class="uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/20">{{ selected.chronicle }}</span>
                                    <span class="font-mono font-bold" :class="selected.success_rate >= 100 ? 'text-green-400' : 'text-yellow-400'">{{ selected.success_rate }}% {{ $t('common.success_rate') }}</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" @click="clearRecipe" class="text-gray-500 hover:text-white transition px-3 py-2 rounded-lg hover:bg-white/5 text-xs font-bold tracking-widest uppercase">
                            {{ $t('common.clear') }}
                        </button>
                    </div>

                    <!-- Loading -->
                    <div v-if="treeLoading" class="text-center py-16">
                        <svg class="w-10 h-10 animate-spin mx-auto mb-4 text-amber-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <div class="text-sm text-gray-500">{{ $t('recipes.tree.loading') }}</div>
                    </div>

                    <template v-else-if="tree">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- LEFT: Tree + Info (2 cols) -->
                            <div class="lg:col-span-2 space-y-5">
                                <!-- Outputs & Info -->
                                <div class="flex flex-wrap gap-3">
                                    <div v-if="tree.outputs?.length" v-for="o in tree.outputs" :key="o.item_id" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-green-500/5 border border-green-500/20">
                                        <img v-if="o.image_url" :src="o.image_url" class="w-6 h-6 rounded" />
                                        <span class="text-sm font-bold text-green-300">{{ o.name }}</span>
                                        <span v-if="o.quantity > 1" class="text-xs text-gray-500">x{{ o.quantity }}</span>
                                        <span v-if="o.chance" class="text-[10px] font-mono text-yellow-400">({{ (o.chance * 100).toFixed(1) }}%)</span>
                                    </div>
                                    <div v-if="tree.recipe.mp_cost" class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-blue-500/10 text-blue-300 border border-blue-500/20 text-xs">
                                        <span class="font-black">MP</span> {{ fmt(tree.recipe.mp_cost) }}
                                    </div>
                                    <div v-if="tree.recipe.adena_fee" class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-yellow-500/10 text-yellow-300 border border-yellow-500/20 text-xs">
                                        <span class="font-black">Fee</span> {{ fmt(tree.recipe.adena_fee) }}
                                    </div>
                                </div>

                                <!-- Materials Tree -->
                                <div class="rounded-xl border border-white/5 overflow-hidden">
                                    <div class="px-4 py-2.5 bg-white/[0.03] border-b border-white/5 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                        {{ $t('recipes.tree.title') }}
                                    </div>
                                    <div class="divide-y divide-white/[0.03]">
                                        <div v-for="(node, i) in flattenTree(tree.nodes)" :key="i" class="flex items-center gap-2 px-4 py-2.5 hover:bg-white/[0.02] transition-colors">
                                            <div :style="{ paddingLeft: node.depth * 20 + 'px' }" class="flex items-center gap-2 flex-1 min-w-0">
                                                <svg v-if="node.depth > 0" class="w-3 h-3 flex-shrink-0 text-white/10" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                                                <img v-if="node.image_url" :src="node.image_url" class="w-6 h-6 rounded flex-shrink-0" />
                                                <div v-else class="w-6 h-6 rounded flex-shrink-0 flex items-center justify-center" :class="node.is_recipe ? 'bg-amber-500/10 text-amber-400' : 'bg-white/5 text-gray-500'">
                                                    <svg v-if="node.is_recipe" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                                </div>
                                                <span class="text-sm truncate" :class="[node.is_recipe ? 'text-amber-400 font-bold italic' : 'text-gray-200', node.depth === 0 ? 'font-bold' : 'font-medium']">{{ node.name || 'Unknown' }}</span>
                                                <span v-if="node.craft_recipe_id && node.children?.length" class="flex-shrink-0 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/20">{{ $t('recipes.tree.craftable') }}</span>
                                            </div>
                                            <div class="flex items-center gap-3 flex-shrink-0">
                                                <MarketPriceCell
                                                    v-if="!node.is_recipe"
                                                    :item-id="node.item_id"
                                                    :value="node.market_price"
                                                    :editable="isAuthenticated"
                                                    :locale-tag="localeTag"
                                                    :label-edit="t('market_price.edit_cta')"
                                                    :label-empty="t('market_price.empty_cta')"
                                                    :label-updated="t('market_price.tooltip_updated', { user: '{user}', ago: '{ago}' })"
                                                    size="sm"
                                                    @update="onRecipePriceUpdate"
                                                />
                                                <div class="flex flex-col items-end">
                                                    <span class="text-xs font-mono font-bold text-yellow-400">x{{ node.need }}</span>
                                                    <span v-if="node.depth > 0 && node.per_parent_qty && node.per_parent_qty !== node.need" class="text-[9px] font-mono text-gray-500 leading-none mt-0.5">{{ node.per_parent_qty }} / unit</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT: Calculator (l2hub style) -->
                            <div class="space-y-5">
                                <!-- Estimated cost -->
                                <div class="rounded-xl border border-amber-500/20 overflow-hidden bg-gradient-to-br from-amber-500/[0.08] to-transparent">
                                    <div class="px-4 py-3 flex items-center justify-between gap-2">
                                        <div>
                                            <div class="text-[10px] font-black uppercase tracking-widest text-amber-400">{{ t('market_price.recipe_total') }}</div>
                                            <div v-if="totalRecipeCost.anyPriced" class="text-2xl font-cinzel text-amber-300 mt-1" :title="formatAdenaFull(totalRecipeCost.total, localeTag)">
                                                {{ formatAdenaShort(totalRecipeCost.total, localeTag) }} a
                                            </div>
                                            <div v-else class="text-sm text-gray-500 italic mt-1">
                                                {{ t('market_price.placeholder') }}
                                            </div>
                                            <div v-if="totalRecipeCost.anyPriced && totalRecipeCost.fee > 0" class="text-[10px] text-gray-500 mt-0.5">
                                                {{ t('market_price.recipe_fee', { fee: formatAdenaShort(totalRecipeCost.fee, localeTag) }) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Items to craft - single list with inline sliders -->
                                <div class="rounded-xl border border-amber-500/20 overflow-hidden bg-amber-500/[0.03]">
                                    <div class="px-4 py-3 bg-amber-500/10 border-b border-amber-500/20 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        <span class="text-[10px] font-black uppercase tracking-widest text-amber-400">{{ $t('recipes.calc.title') }}</span>
                                    </div>
                                    <div class="max-h-[65vh] overflow-y-auto">
                                        <div v-for="(it, i) in getCalcItems(tree.nodes)" :key="i"
                                             class="border-b border-white/[0.03] last:border-0"
                                             :style="{ paddingLeft: (it.depth * 16 + 12) + 'px' }">
                                            <div class="flex items-center gap-2.5 py-2.5 pr-3">
                                                <img v-if="it.image_url" :src="it.image_url" class="w-7 h-7 rounded flex-shrink-0" />
                                                <div v-else class="w-7 h-7 rounded bg-white/5 flex-shrink-0"></div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="text-[13px] font-bold truncate" :class="it.depth > 0 ? 'text-gray-400' : 'text-gray-100'">
                                                            <span class="text-amber-400 font-mono mr-1">{{ it.need }}</span>
                                                            {{ it.name }}
                                                        </span>
                                                        <span v-if="it.depth > 0 && it.per_parent_qty" class="text-[9px] font-mono text-gray-500">({{ it.per_parent_qty }}/unit)</span>
                                                    </div>
                                                    <!-- Craftable: show need + to craft + slider -->
                                                    <div v-if="it.craftable" class="mt-1.5">
                                                        <div class="flex items-center gap-3 text-[10px] text-gray-500 mb-1">
                                                            <span>{{ $t('recipes.calc.need') }}: <span class="text-amber-300 font-mono">{{ it.needBuy }}</span></span>
                                                            <span><span class="text-purple-300 font-mono">{{ it.toCraft }}</span> {{ $t('recipes.calc.to_craft') }}</span>
                                                        </div>
                                                        <input type="range" min="0" :max="it.need"
                                                               :value="craftAmounts[it.item_id] || 0"
                                                               @input="craftAmounts[it.item_id] = parseInt($event.target.value)"
                                                               class="w-full h-1.5 rounded-full appearance-none cursor-pointer bg-gray-700 accent-purple-500"
                                                        />
                                                    </div>
                                                    <!-- Non-craftable: just show need -->
                                                    <div v-else class="text-[10px] text-gray-500 mt-0.5">
                                                        {{ $t('recipes.calc.need') }}: <span class="text-amber-300 font-mono">{{ it.need }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Needed Resources summary -->
                                <div class="rounded-xl border border-white/5 overflow-hidden bg-black/20">
                                    <div class="px-4 py-2.5 bg-white/[0.03] border-b border-white/5 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                        {{ $t('recipes.calc.needed_resources') }}
                                    </div>
                                    <div class="p-3 space-y-1 max-h-60 overflow-y-auto">
                                        <div v-for="res in getNeededResources(tree.nodes)" :key="res.item_id" class="flex items-center gap-2 py-1.5 px-2 rounded text-xs">
                                            <img v-if="res.image_url" :src="res.image_url" class="w-5 h-5 rounded flex-shrink-0" />
                                            <span class="text-gray-300 truncate flex-1">{{ res.name }}</span>
                                            <span class="font-mono font-bold text-amber-400 flex-shrink-0">{{ fmt(res.need) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty state if no tree loaded yet -->
                </div>
            </transition>

            <!-- Empty state when nothing selected -->
            <div v-if="!selected" class="text-center py-16">
                <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                    <svg class="w-10 h-10 text-amber-400/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <p class="text-gray-500 text-sm max-w-sm mx-auto">{{ $t('recipes.empty_state') }}</p>
            </div>
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&display=swap');
.font-cinzel { font-family: 'Cinzel', serif; }

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] { -moz-appearance: textfield; }
</style>
