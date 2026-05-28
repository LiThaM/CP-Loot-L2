<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { renderInlineMarkdown } from '@/utils/inlineMarkdown';

const props = defineProps({
    rule: { type: Object, default: null },
    acceptedVersion: { type: Number, default: null },
});

const page = usePage();
const localeTag = computed(() => (page.props.app?.locale === 'es' ? 'es-ES' : 'en-US'));

const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};

const formattedDate = computed(() => {
    if (!props.rule?.updated_at) return '';
    try {
        return new Intl.DateTimeFormat(localeTag.value, { dateStyle: 'medium' }).format(new Date(props.rule.updated_at));
    } catch (_) {
        return String(props.rule.updated_at).slice(0, 10);
    }
});

const isAccepted = computed(() => {
    if (!props.rule) return false;
    return Number(props.acceptedVersion ?? 0) >= Number(props.rule.version ?? 0);
});
</script>

<template>
    <div v-if="rule" class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <span class="px-2.5 py-1 rounded-full bg-amber-600/15 text-amber-700 dark:text-amber-300 border border-amber-500/40 text-[10px] font-black uppercase tracking-widest">
                v{{ rule.version }}
            </span>
            <span
                class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border"
                :class="isAccepted
                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/40'
                    : 'bg-red-500/15 text-red-600 dark:text-red-300 border-red-500/40'"
            >
                {{ isAccepted ? t('cp.rules.accepted_badge') : t('cp.rules.pending_badge') }}
            </span>
            <span class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                {{ t('cp.rules.version_meta', { version: rule.version, date: formattedDate, author: rule.updated_by || '—' }) }}
            </span>
        </div>
        <div
            class="text-sm leading-relaxed text-gray-800 dark:text-gray-200 changelog-body"
            v-html="renderInlineMarkdown(rule.body || '')"
        ></div>
    </div>
</template>
