<script setup>
import RulesView from '@/Components/CpRules/RulesView.vue';

defineProps({
    isLeader: { type: Boolean, default: false },
    cpRules: { type: Object, default: () => ({ hasRules: false, current: null, acceptedVersion: 0 }) },
});

const emit = defineEmits(['open-editor']);
</script>

<template>
    <div class="space-y-6">
        <div class="l2-panel p-8 rounded-3xl border-gray-800">
            <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h3 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('cp.rules.title') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">{{ $t('cp.rules.subtitle') }}</p>
                </div>
                <button v-if="isLeader" @click="emit('open-editor')"
                        class="shrink-0 px-4 py-2 rounded-xl bg-gradient-to-tr from-amber-600 to-red-600 hover:from-amber-500 hover:to-red-500 text-white text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-amber-900/20">
                    {{ cpRules.hasRules ? $t('cp.rules.edit_button') : $t('cp.rules.empty_leader_cta') }}
                </button>
            </div>

            <RulesView
                v-if="cpRules.hasRules"
                :rule="cpRules.current"
                :accepted-version="cpRules.acceptedVersion"
            />
            <div v-else class="py-12 text-center text-gray-600 dark:text-gray-500 italic">
                {{ isLeader ? '' : $t('cp.rules.empty_member') }}
            </div>
        </div>
    </div>
</template>
