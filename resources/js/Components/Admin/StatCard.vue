<script setup>
import { computed } from 'vue';

// Reusable admin KPI card. Visual extracted from AdminStats.vue.
// Accent colour controls both the value text (when prominent=true) and
// the small uppercase subtitle under the metric. Defaults to neutral
// (gray-900 / white) so multiple cards in a row don't fight each other.
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    valueTooltip: { type: String, default: '' },
    emoji: { type: String, default: '' },
    accent: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'purple', 'blue', 'emerald', 'indigo', 'amber', 'red'].includes(v),
    },
    subtitle: { type: String, default: '' },
    prominent: { type: Boolean, default: false },
    size: { type: String, default: 'lg' }, // 'lg' = text-4xl, 'md' = text-3xl, 'sm' = text-2xl
});

const valueSizeClass = computed(() => ({
    lg: 'text-4xl',
    md: 'text-3xl',
    sm: 'text-2xl',
}[props.size] || 'text-4xl'));

const subtitleAccentClass = computed(() => ({
    neutral: 'text-gray-500',
    purple: 'text-purple-500',
    blue: 'text-blue-500',
    emerald: 'text-emerald-500',
    indigo: 'text-indigo-500',
    amber: 'text-amber-500',
    red: 'text-red-500',
}[props.accent]));

const valueAccentClass = computed(() => {
    if (!props.prominent) return 'text-gray-900 dark:text-white';
    return ({
        neutral: 'text-gray-900 dark:text-white',
        purple: 'text-purple-700 dark:text-purple-300',
        blue: 'text-blue-700 dark:text-blue-300',
        emerald: 'text-emerald-700 dark:text-emerald-300',
        indigo: 'text-indigo-600 dark:text-indigo-400',
        amber: 'text-amber-600 dark:text-amber-400',
        red: 'text-red-600 dark:text-red-400',
    }[props.accent]);
});
</script>

<template>
    <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800 shadow-xl relative overflow-hidden group transition-all">
        <div v-if="emoji" class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">{{ emoji }}</div>
        <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2 relative">{{ label }}</div>
        <div
            :class="['font-cinzel font-black', valueSizeClass, valueAccentClass]"
            :title="valueTooltip || undefined"
        >
            <slot name="value">{{ value }}</slot>
        </div>
        <div v-if="subtitle || $slots.subtitle" class="mt-2 text-[10px] font-bold uppercase tracking-widest" :class="subtitleAccentClass">
            <slot name="subtitle">{{ subtitle }}</slot>
        </div>
    </div>
</template>
