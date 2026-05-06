<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed, ref, watch, reactive } from 'vue';
import axios from 'axios';

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

// Calculator: user-inputted "have" amounts keyed by item_id
const haveAmounts = reactive({});

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

const getLeaves = (nodes) => {
    const leaves = [];
    const walk = (list) => { for (const n of list) { if (!n.children?.length) leaves.push(n); else walk(n.children); } };
    walk(nodes || []);
    // Aggregate by item_id
    const map = {};
    for (const l of leaves) {
        if (map[l.item_id]) map[l.item_id].need += l.need;
        else map[l.item_id] = { ...l };
    }
    return Object.values(map);
};

const getMissing = (itemId, need) => {
    const have = parseInt(haveAmounts[itemId]) || 0;
    return Math.max(0, need - have);
};

const fmt = (n) => n?.toLocaleString() ?? '0';
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
                        Recipe Explorer
                    </span>
                    <Link v-if="$page.props.auth?.user" :href="route('dashboard')" class="px-4 py-2 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-300 text-xs font-bold tracking-widest uppercase hover:bg-purple-600/30 transition-all">
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link :href="route('login')" class="px-4 py-2 rounded-lg border border-white/10 text-gray-300 text-xs font-bold tracking-widest uppercase hover:bg-white/5 transition-all">
                            Login
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
            <!-- Search Section -->
            <div class="text-center mb-8">
                <h1 class="text-2xl sm:text-4xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-amber-200 to-amber-500 font-cinzel">Crafting Calculator</h1>
                <p class="mt-2 text-sm text-gray-400 max-w-md mx-auto">Search a recipe, see the full crafting tree, and input your materials to calculate what you need</p>
            </div>

            <div class="max-w-2xl mx-auto relative mb-10">
                <div class="flex gap-2">
                    <select v-model="selectedChronicle" class="bg-black/40 border border-white/10 text-gray-300 rounded-xl focus:ring-1 focus:ring-amber-500 focus:border-amber-500 text-sm py-4 px-3 w-32 shrink-0">
                        <option value="">All</option>
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
                                    <span>{{ r.materials_count }} mats</span>
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
                                    <span class="font-mono font-bold" :class="selected.success_rate >= 100 ? 'text-green-400' : 'text-yellow-400'">{{ selected.success_rate }}% success</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" @click="clearRecipe" class="text-gray-500 hover:text-white transition px-3 py-2 rounded-lg hover:bg-white/5 text-xs font-bold tracking-widest uppercase">
                            Clear
                        </button>
                    </div>

                    <!-- Loading -->
                    <div v-if="treeLoading" class="text-center py-16">
                        <svg class="w-10 h-10 animate-spin mx-auto mb-4 text-amber-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <div class="text-sm text-gray-500">Loading crafting tree...</div>
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
                                        Materials Tree
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
                                                <span v-if="node.craft_recipe_id" class="flex-shrink-0 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/20">craftable</span>
                                            </div>
                                            <span class="text-xs font-mono font-bold text-yellow-400 flex-shrink-0">x{{ node.need }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT: Calculator -->
                            <div class="space-y-5">
                                <div class="rounded-xl border border-amber-500/20 overflow-hidden bg-amber-500/[0.03]">
                                    <div class="px-4 py-3 bg-amber-500/10 border-b border-amber-500/20 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        <span class="text-[10px] font-black uppercase tracking-widest text-amber-400">Material Calculator</span>
                                    </div>
                                    <div class="p-3 space-y-1.5 max-h-[60vh] overflow-y-auto">
                                        <div v-for="leaf in getLeaves(tree.nodes)" :key="leaf.item_id" class="flex items-center gap-2 p-2.5 rounded-lg transition-colors" :class="getMissing(leaf.item_id, leaf.need) === 0 ? 'bg-green-500/5' : 'bg-black/20'">
                                            <img v-if="leaf.image_url" :src="leaf.image_url" class="w-7 h-7 rounded flex-shrink-0" />
                                            <div v-else class="w-7 h-7 rounded bg-white/5 flex-shrink-0"></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-bold text-gray-200 truncate">{{ leaf.name }}</div>
                                                <div class="text-[10px] text-gray-500">
                                                    Need: <span class="text-yellow-400 font-mono">{{ leaf.need }}</span>
                                                    <span class="mx-1">|</span>
                                                    Missing: <span class="font-mono" :class="getMissing(leaf.item_id, leaf.need) > 0 ? 'text-red-400' : 'text-green-400'">{{ getMissing(leaf.item_id, leaf.need) }}</span>
                                                </div>
                                            </div>
                                            <input
                                                v-model="haveAmounts[leaf.item_id]"
                                                type="number"
                                                min="0"
                                                :placeholder="'0'"
                                                class="w-16 bg-black/40 border rounded-lg text-center text-xs py-1.5 font-mono font-bold focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all"
                                                :class="getMissing(leaf.item_id, leaf.need) === 0 ? 'border-green-500/30 text-green-400' : 'border-white/10 text-white'"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary -->
                                <div class="rounded-xl border border-white/5 p-4 bg-black/20">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">Summary</div>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Total base materials</span>
                                            <span class="font-mono font-bold text-white">{{ getLeaves(tree.nodes).length }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Completed</span>
                                            <span class="font-mono font-bold text-green-400">{{ getLeaves(tree.nodes).filter(l => getMissing(l.item_id, l.need) === 0).length }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Still missing</span>
                                            <span class="font-mono font-bold text-red-400">{{ getLeaves(tree.nodes).filter(l => getMissing(l.item_id, l.need) > 0).length }}</span>
                                        </div>
                                        <div class="h-px bg-white/5 my-2"></div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-400">Progress</span>
                                            <span class="font-mono font-bold text-amber-400">
                                                {{ Math.round(getLeaves(tree.nodes).filter(l => getMissing(l.item_id, l.need) === 0).length / Math.max(1, getLeaves(tree.nodes).length) * 100) }}%
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Progress bar -->
                                    <div class="mt-3 h-2 rounded-full bg-white/5 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-green-500 transition-all duration-500" :style="{ width: Math.round(getLeaves(tree.nodes).filter(l => getMissing(l.item_id, l.need) === 0).length / Math.max(1, getLeaves(tree.nodes).length) * 100) + '%' }"></div>
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
                <p class="text-gray-500 text-sm max-w-sm mx-auto">Start typing a recipe name above to explore crafting trees and calculate materials</p>
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
