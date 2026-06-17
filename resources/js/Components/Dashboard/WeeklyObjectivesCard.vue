<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { showToast } from '@/utils/swal';

const props = defineProps({
    // [{ id, item:{id,name,grade,image_url}, target_quantity, multiplier, progress, completed }]
    objectives: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    trackerEnabled: { type: Boolean, default: false },
    localeTag: { type: String, default: 'en-US' },
});

const isEs = computed(() => String(props.localeTag || '').toLowerCase().startsWith('es'));
const tr = (es, en) => (isEs.value ? es : en);

const pct = (o) => {
    const t = Number(o.target_quantity || 0);
    if (t <= 0) return 0;
    return Math.min(100, Math.round((Number(o.progress || 0) / t) * 100));
};

const reload = () => router.reload({ only: ['cpInsights'] });

// ---- add modal ----
const open = ref(false);
const submitting = ref(false);
const query = ref('');
const results = ref([]);
const loading = ref(false);
const selected = ref(null);
const target = ref(10);
const multiplier = ref(1.5);
let timer = null;

const openModal = () => { open.value = true; query.value = ''; results.value = []; selected.value = null; target.value = 10; multiplier.value = 1.5; };
const close = () => { open.value = false; };

const onSearch = () => {
    selected.value = null;
    clearTimeout(timer);
    const q = query.value.trim();
    if (q.length < 3) { results.value = []; loading.value = false; return; }
    loading.value = true;
    timer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('api.items.search'), { params: { q, per_page: 8 } });
            results.value = data.items || [];
        } catch (_) { results.value = []; }
        finally { loading.value = false; }
    }, 300);
};
const pick = (it) => { selected.value = it; results.value = []; query.value = it.name; };

const submit = () => {
    if (!selected.value || submitting.value) return;
    const t = Math.max(1, parseInt(target.value || 1));
    const m = Math.min(9.99, Math.max(1, Number(multiplier.value || 1)));
    submitting.value = true;
    router.post(route('objectives.store'), { item_id: selected.value.id, target_quantity: t, multiplier: m }, {
        preserveScroll: true,
        onSuccess: () => { showToast(tr('Objetivo añadido', 'Objective added')); close(); reload(); },
        onFinish: () => { submitting.value = false; },
    });
};

const remove = (o) => {
    router.delete(route('objectives.destroy', o.id), {
        preserveScroll: true,
        onSuccess: () => { showToast(tr('Objetivo eliminado', 'Objective removed')); reload(); },
    });
};
</script>

<template>
    <div class="l2-panel p-5 rounded-lg border border-amber-500/20 bg-gradient-to-b from-amber-500/5 to-transparent backdrop-blur">
        <div class="flex items-center justify-between mb-3">
            <div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-700 dark:text-amber-300/90">
                    🎯 {{ tr('Objetivos semanales', 'Weekly objectives') }}
                </div>
                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                    {{ trackerEnabled
                        ? tr('Conseguirlos da puntos × multiplicador', 'Getting them grants points × multiplier')
                        : tr('Sin tracker: informativos, no dan puntos', 'No tracker: informational, no points') }}
                </div>
            </div>
            <button v-if="canManage" @click="openModal"
                    class="text-[10px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-600 dark:text-amber-300 dark:hover:text-amber-200 transition">
                + {{ tr('Añadir', 'Add') }}
            </button>
        </div>

        <div v-if="objectives.length === 0" class="text-sm text-gray-600 dark:text-gray-500 italic py-4 text-center">
            {{ tr('Sin objetivos esta semana', 'No objectives this week') }}
        </div>

        <div v-else class="space-y-3">
            <div v-for="o in objectives" :key="o.id"
                 class="p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20"
                 :class="o.completed ? 'opacity-70' : ''">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg border border-gray-200 bg-gray-100 overflow-hidden shrink-0 dark:border-gray-700 dark:bg-black/40">
                        <img v-if="o.item?.image_url" :src="o.item.image_url" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black text-gray-900 dark:text-white truncate">{{ o.item?.name || '—' }}</span>
                            <span class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/25">×{{ o.multiplier }}</span>
                            <span v-if="o.completed" class="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">✓ {{ tr('hecho', 'done') }}</span>
                        </div>
                        <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-0.5">
                            {{ Number(o.progress || 0) }} / {{ o.target_quantity }}
                        </div>
                    </div>
                    <button v-if="canManage" @click="remove(o)" class="text-gray-400 hover:text-red-500 transition shrink-0" :title="tr('Eliminar', 'Remove')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-black/40 overflow-hidden border border-gray-300/40 dark:border-gray-700/60 mt-2">
                    <div class="h-full rounded-full transition-all duration-700"
                         :class="o.completed ? 'bg-gradient-to-r from-emerald-500 to-green-400' : 'bg-gradient-to-r from-amber-500 to-orange-400'"
                         :style="{ width: pct(o) + '%' }"></div>
                </div>
            </div>
        </div>

        <!-- add modal -->
        <div v-if="open" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="close">
            <div class="l2-panel w-full max-w-md rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-amber-700 to-orange-600 p-4 flex justify-between items-center">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">🎯 {{ tr('Nuevo objetivo', 'New objective') }}</h3>
                    <button @click="close" class="text-white/60 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="relative">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ tr('Item objetivo', 'Target item') }}</label>
                        <input v-model="query" @input="onSearch" type="text" :placeholder="tr('Mínimo 3 letras…', 'At least 3 letters…')"
                               class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl h-11 px-4 focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        <div v-if="results.length || loading" class="absolute z-50 mt-2 w-full bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto dark:bg-gray-950 dark:border-gray-800">
                            <div v-if="loading" class="p-3 text-sm text-gray-500 italic">{{ tr('Buscando…', 'Searching…') }}</div>
                            <button v-for="it in results" :key="it.id" type="button" @click="pick(it)"
                                    class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-amber-50 dark:hover:bg-amber-900/20 transition">
                                <img v-if="it.image_url" :src="it.image_url" class="w-7 h-7 rounded border border-gray-200 dark:border-gray-700 shrink-0">
                                <div v-else class="w-7 h-7 rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60 shrink-0"></div>
                                <span class="flex-1 min-w-0 text-xs font-bold text-gray-900 dark:text-gray-100 truncate">{{ it.name }}</span>
                                <span v-if="it.grade" class="text-[9px] font-black uppercase text-purple-600 dark:text-purple-300">{{ it.grade }}</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="selected" class="text-xs font-black text-amber-700 dark:text-amber-300">{{ selected.name }}</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ tr('Cantidad', 'Target qty') }}</label>
                            <input v-model.number="target" type="number" min="1"
                                   class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl h-11 px-4 text-center font-black focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ tr('Multiplicador', 'Multiplier') }}</label>
                            <input v-model.number="multiplier" type="number" min="1" max="9.99" step="0.1"
                                   class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl h-11 px-4 text-center font-black focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                    </div>
                </div>
                <div class="p-5 pt-0 flex gap-3">
                    <button @click="close" class="flex-1 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 rounded-xl font-black uppercase tracking-widest text-[10px] transition">
                        {{ tr('Cancelar', 'Cancel') }}
                    </button>
                    <button @click="submit" :disabled="!selected || submitting"
                            class="flex-[2] py-3 bg-gradient-to-tr from-amber-600 to-orange-500 hover:from-amber-500 hover:to-orange-400 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg disabled:opacity-30">
                        {{ tr('Añadir objetivo', 'Add objective') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
