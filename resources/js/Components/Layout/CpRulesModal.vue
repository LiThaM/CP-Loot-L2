<script setup>
import { renderInlineMarkdown } from '@/utils/inlineMarkdown';

defineProps({
    open: { type: Boolean, default: false },
    rules: { type: Object, default: () => ({ current: null }) },
    submitting: { type: Boolean, default: false },
    updatedAtFormatted: { type: String, default: '' },
});

const emit = defineEmits(['accept']);
</script>

<template>
    <!-- Blocking CP-rules modal. No close affordance: the only way out is
         to press "I accept". Sits above every other overlay (z-[130] vs
         changelog's z-[120]). -->
    <div v-if="open" class="fixed inset-0 z-[130] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-full max-w-2xl max-h-[88vh] rounded-2xl border-amber-500/40 overflow-hidden shadow-2xl flex flex-col">
            <div class="bg-gradient-to-r from-amber-900 to-red-900 p-4 flex items-center justify-between border-b border-amber-500/30">
                <div>
                    <div class="text-[10px] text-amber-200 font-black uppercase tracking-widest">{{ $t('cp.rules.modal.kicker') }}</div>
                    <div class="text-lg font-cinzel text-white tracking-widest mt-0.5">{{ $t('cp.rules.modal.title') }}</div>
                </div>
                <span v-if="rules?.current?.version" class="px-2.5 py-1 rounded-full bg-amber-600 text-white text-[10px] font-black uppercase tracking-widest">v{{ rules.current.version }}</span>
            </div>
            <div class="px-6 pt-4 text-[11px] text-amber-700 dark:text-amber-300 font-bold uppercase tracking-widest">
                {{ $t('cp.rules.modal.subtitle') }}
            </div>
            <div class="px-6 pt-2 pb-1 text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                {{ $t('cp.rules.version_meta', { version: rules?.current?.version, date: updatedAtFormatted, author: rules?.current?.updated_by || '—' }) }}
            </div>
            <div class="p-6 overflow-y-auto custom-scrollbar text-sm leading-relaxed text-gray-800 dark:text-gray-200 changelog-body" v-html="renderInlineMarkdown(rules?.current?.body || '')"></div>
            <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-white/40 dark:bg-black/30">
                <p class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest mb-3">
                    {{ $t('cp.rules.modal.no_dismiss_hint') }}
                </p>
                <button @click="emit('accept')" type="button" :disabled="submitting"
                        class="w-full py-3 bg-gradient-to-tr from-amber-600 to-red-600 hover:from-amber-500 hover:to-red-500 text-white rounded-xl text-[11px] font-black uppercase tracking-widest disabled:opacity-30">
                    {{ $t('cp.rules.modal.accept') }}
                </button>
            </div>
        </div>
    </div>
</template>
