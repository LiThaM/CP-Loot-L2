<script setup>
defineProps({
    categories: { type: Array, default: () => [] },
    getDefaultPoints: { type: Function, required: true },
});

const emit = defineEmits(['reset-dkp', 'save-config']);
</script>

<template>
    <div class="space-y-6">
        <div class="l2-panel p-8 rounded-3xl border-gray-800">
            <div class="mb-8 flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('party.points.title') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">{{ $t('party.points.subtitle') }}</p>
                </div>
                <button
                    @click="emit('reset-dkp')"
                    class="shrink-0 px-3 py-2 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest transition hover:bg-red-600 border border-gray-200 dark:border-gray-700 shadow-lg shadow-black/20"
                    :title="$t('party.points.reset_btn_title')"
                >
                    {{ $t('party.points.reset_btn_label') }}
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="cat in categories" :key="cat.id" class="bg-white/70 p-6 rounded-2xl border border-gray-200 flex items-center group dark:bg-gray-900/50 dark:border-gray-800">
                    <div class="text-4xl mr-6">{{ cat.icon }}</div>
                    <div class="flex-1">
                        <div class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ cat.name }}</div>
                        <p class="text-[10px] text-gray-600 dark:text-gray-500 font-bold leading-tight">{{ cat.desc }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <div class="text-[10px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('party.points.current') }}</div>
                        <div class="flex items-center gap-3">
                            <input
                                type="number"
                                :value="getDefaultPoints(cat.id)"
                                @change="emit('save-config', cat.id, $event.target.value)"
                                class="w-16 bg-white border border-gray-200 text-purple-700 font-black text-center py-1 rounded-lg focus:ring-purple-600 transition dark:bg-black/50 dark:border-gray-700 dark:text-purple-300"
                            >
                            <div class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $t('party.points.pts') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 bg-purple-950/10 border border-purple-500/15 rounded-2xl text-xs text-purple-700 dark:text-purple-200 font-bold italic">
            {{ $t('party.points.hint') }}
        </div>
    </div>
</template>
