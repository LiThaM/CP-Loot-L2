<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
        <div>
            <h1 class="font-cinzel text-2xl md:text-3xl text-gray-900 dark:text-white tracking-widest uppercase">{{ title }}</h1>
            <p v-if="subtitle" class="mt-2 text-[10px] md:text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                {{ subtitle }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2 items-center">
            <slot name="actions" />
        </div>
    </div>
</template>
