<script setup>
defineProps({
    height: { type: String, default: 'h-32' },
});
</script>

<template>
    <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800 animate-pulse">
        <div class="h-2 w-24 bg-gray-200 dark:bg-gray-800 rounded mb-3"></div>
        <div class="h-8 w-16 bg-gray-200 dark:bg-gray-800 rounded mb-2"></div>
        <div class="h-2 w-20 bg-gray-200 dark:bg-gray-800 rounded"></div>
    </div>
</template>
