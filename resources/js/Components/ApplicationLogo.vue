<template>
    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" v-bind="$attrs">
        <rect width="40" height="40" rx="10" :fill="light ? '#f5f3ff' : '#16111f'"/>
        <path d="M9 12h10v18H9a1 1 0 01-1-1V13a1 1 0 011-1z" :fill="light ? '#7c3aed' : '#c084fc'" :opacity="light ? 0.12 : 0.15"/>
        <path d="M19 12h10a1 1 0 011 1v16a1 1 0 01-1 1H19V12z" :fill="light ? '#7c3aed' : '#c084fc'" :opacity="light ? 0.06 : 0.08"/>
        <line x1="19" y1="12" x2="19" y2="30" :stroke="light ? '#7c3aed' : '#c084fc'" stroke-width="1.5" :opacity="light ? 0.4 : 0.5"/>
        <line x1="12" y1="17" x2="17" y2="17" :stroke="light ? '#7c3aed' : '#c084fc'" stroke-width="1" :opacity="light ? 0.25 : 0.3"/>
        <line x1="12" y1="20" x2="16" y2="20" :stroke="light ? '#7c3aed' : '#c084fc'" stroke-width="1" :opacity="light ? 0.25 : 0.3"/>
        <line x1="12" y1="23" x2="17" y2="23" :stroke="light ? '#7c3aed' : '#c084fc'" stroke-width="1" :opacity="light ? 0.25 : 0.3"/>
        <circle cx="27" cy="16" r="6" :fill="light ? '#f5f3ff' : '#16111f'" :stroke="light ? '#d97706' : '#fbbf24'" stroke-width="1.5"/>
        <circle cx="27" cy="16" r="3.5" :stroke="light ? '#d97706' : '#fbbf24'" stroke-width="0.8" opacity="0.4"/>
        <path d="M26 14v4m-1-1h2" :stroke="light ? '#d97706' : '#fbbf24'" stroke-width="1.2" stroke-linecap="round"/>
    </svg>
</template>

<script setup>
defineProps({
    light: { type: Boolean, default: false },
});
</script>
