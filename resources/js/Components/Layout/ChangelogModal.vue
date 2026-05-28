<script setup>
import { renderInlineMarkdown } from '@/utils/inlineMarkdown';

const props = defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    unread: { type: Number, default: 0 },
    submitting: { type: Boolean, default: false },
    localeTag: { type: String, default: 'en-US' },
    localizedTitle: { type: Function, required: true },
    localizedBody: { type: Function, required: true },
    typeClass: { type: Function, required: true },
});

const emit = defineEmits(['accept', 'see-all']);
</script>

<template>
    <!-- First-open changelog modal — shows whenever the user has any
         unread web changelog entry, until they acknowledge. -->
    <div v-if="open" class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm">
        <div class="l2-panel w-full max-w-2xl max-h-[88vh] rounded-2xl border-purple-500/30 overflow-hidden shadow-2xl flex flex-col">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/30">
                <div>
                    <div class="text-[10px] text-purple-200 font-black uppercase tracking-widest">{{ $t('changelog.modal.kicker', 'Novedades') }}</div>
                    <div class="text-lg font-cinzel text-white tracking-widest mt-0.5">{{ $t('changelog.modal.title', 'Cambios desde tu última visita') }}</div>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-purple-600 text-white text-[10px] font-black uppercase tracking-widest">{{ unread }}</span>
            </div>
            <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar">
                <article v-for="entry in items" :key="'cl-'+entry.id" class="border-l-2 border-purple-500/40 pl-4 py-1">
                    <div class="flex items-baseline gap-3 mb-1 flex-wrap">
                        <span :class="['text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full border', typeClass(entry.type)]">{{ entry.type }}</span>
                        <span class="text-[10px] text-gray-500">{{ new Date(entry.published_at).toLocaleDateString(localeTag) }}</span>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ localizedTitle(entry) }}</h3>
                    <div v-if="localizedBody(entry)" class="text-sm mt-1 leading-relaxed text-gray-700 dark:text-gray-300 changelog-body" v-html="renderInlineMarkdown(localizedBody(entry))"></div>
                </article>
            </div>
            <div class="p-4 border-t border-gray-200 dark:border-gray-800 flex gap-3 bg-white/40 dark:bg-black/30">
                <button @click="emit('see-all')" type="button"
                        class="flex-1 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl text-[10px] font-black uppercase tracking-widest dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    {{ $t('changelog.modal.see_all', 'Ver historial completo') }}
                </button>
                <button @click="emit('accept')" type="button" :disabled="submitting"
                        class="flex-[2] py-3 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-30">
                    {{ $t('changelog.modal.acknowledge', 'Visto, no me lo muestres más') }}
                </button>
            </div>
        </div>
    </div>
</template>
