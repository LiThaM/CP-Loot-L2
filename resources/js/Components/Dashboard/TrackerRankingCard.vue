<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ id, name, points, entries }]
    ranking: { type: Array, default: () => [] },
    localeTag: { type: String, default: 'en-US' },
});

const isEs = computed(() => String(props.localeTag || '').toLowerCase().startsWith('es'));
const tr = (es, en) => (isEs.value ? es : en);

const fmtPoints = (p) => {
    const n = Number(p) || 0;
    return Number.isInteger(n) ? String(n) : String(Number(n.toFixed(2)));
};
</script>

<template>
    <div class="l2-panel p-5 rounded-lg border border-emerald-500/15 bg-gradient-to-b from-emerald-500/5 to-transparent backdrop-blur">
        <div class="flex items-center justify-between mb-3">
            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300/90">
                ⭐ {{ tr('Ranking del tracker', 'Tracker ranking') }}
                <span class="text-gray-500">· DKP</span>
            </div>
        </div>

        <div v-if="ranking.length === 0" class="text-sm text-gray-600 dark:text-gray-500 italic py-4 text-center">
            {{ tr('Aún no hay puntos del tracker', 'No tracker points yet') }}
        </div>

        <div v-else class="space-y-2">
            <div v-for="(m, idx) in ranking.slice(0, 5)" :key="m.id"
                 class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="h-8 w-8 rounded-full flex items-center justify-center text-[10px] font-black text-white shrink-0"
                         :class="idx === 0 ? 'bg-gradient-to-tr from-emerald-500 to-green-400' : 'bg-gradient-to-tr from-emerald-600/35 to-blue-600/35 border border-emerald-500/20'">
                        {{ idx + 1 }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-[11px] font-black text-gray-900 dark:text-white truncate">{{ m.name }}</div>
                        <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                            {{ Number(m.entries || 0) }} {{ tr('entradas', 'entries') }}
                        </div>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-sm font-black font-cinzel text-emerald-700 dark:text-emerald-300">{{ fmtPoints(m.points) }}</div>
                    <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ tr('puntos', 'points') }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
