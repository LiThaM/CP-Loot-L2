<script setup>
import { computed } from 'vue';
import Sparkline from './Sparkline.vue';

// Reusable admin KPI card. Visual extracted from AdminStats.vue.
// Accent colour controls the prominent value text, the small uppercase
// subtitle and the sparkline tint. Optional `trend` shows a ▲/▼ chip
// next to the value; optional `sparkline` paints a 14-point line behind.
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
    size: { type: String, default: 'lg' },
    // { value: number (percent, signed), label?: string }
    trend: { type: Object, default: null },
    sparkline: { type: Array, default: null },
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

const sparklineColor = computed(() => ({
    neutral: '#6b7280',
    purple: '#8b5cf6',
    blue: '#3b82f6',
    emerald: '#10b981',
    indigo: '#6366f1',
    amber: '#f59e0b',
    red: '#ef4444',
}[props.accent]));

const trendValue = computed(() => {
    if (!props.trend || props.trend.value === null || props.trend.value === undefined) return null;
    const n = Number(props.trend.value);
    if (!Number.isFinite(n)) return null;
    return n;
});

const trendIsUp = computed(() => (trendValue.value ?? 0) > 0);
const trendIsDown = computed(() => (trendValue.value ?? 0) < 0);
const trendChipClass = computed(() => {
    if (trendIsUp.value) return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
    if (trendIsDown.value) return 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30';
    return 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/30';
});
const trendArrow = computed(() => (trendIsUp.value ? '▲' : trendIsDown.value ? '▼' : '·'));
const trendText = computed(() => {
    if (trendValue.value === null) return '';
    const abs = Math.abs(trendValue.value);
    const rounded = abs >= 100 ? Math.round(abs) : Number(abs.toFixed(1));
    return `${trendArrow.value} ${rounded}%`;
});
</script>

<template>
    <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800 shadow-xl relative overflow-hidden group transition-all">
        <div v-if="emoji" class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">{{ emoji }}</div>

        <div class="flex items-start justify-between gap-2 relative">
            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">{{ label }}</div>
            <div
                v-if="trend && trendText"
                class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border whitespace-nowrap"
                :class="trendChipClass"
                :title="trend.label || ''"
            >
                {{ trendText }}
            </div>
        </div>

        <div class="relative">
            <!-- Sparkline sits behind the value with low opacity so the
                 number stays the focal point. -->
            <div v-if="sparkline && sparkline.length" class="absolute inset-0 opacity-30 dark:opacity-40">
                <Sparkline :data="sparkline" :color="sparklineColor" />
            </div>
            <div
                :class="['font-cinzel font-black relative', valueSizeClass, valueAccentClass]"
                :title="valueTooltip || undefined"
            >
                <slot name="value">{{ value }}</slot>
            </div>
        </div>

        <div v-if="subtitle || $slots.subtitle" class="mt-2 text-[10px] font-bold uppercase tracking-widest relative" :class="subtitleAccentClass">
            <slot name="subtitle">{{ subtitle }}</slot>
        </div>
        <div v-if="trend && trend.label" class="mt-1 text-[9px] text-gray-400 font-bold uppercase tracking-widest relative">
            {{ trend.label }}
        </div>
    </div>
</template>
