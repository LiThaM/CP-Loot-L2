<script setup>
import { InboxIcon } from '@heroicons/vue/24/outline';

// Reusable empty-state for admin lists. Replaces the bare italic
// "no data found" text. Caller passes a Heroicon component as `icon`
// or relies on the default InboxIcon. `actions` slot is for an optional
// CTA (e.g. "Create the first CP").
defineProps({
    icon: { type: [Object, Function], default: () => InboxIcon },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="py-16 px-6 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 mb-5">
            <component :is="icon" class="w-8 h-8 text-gray-400 dark:text-gray-500" aria-hidden="true" />
        </div>
        <h3 class="font-cinzel text-lg text-gray-900 dark:text-white tracking-widest uppercase">{{ title }}</h3>
        <p v-if="description" class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest max-w-md mx-auto">
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-6 flex items-center justify-center gap-3">
            <slot name="actions" />
        </div>
    </div>
</template>
