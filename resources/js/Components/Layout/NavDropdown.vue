<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * Reusable hover/click dropdown for the desktop navbar. Replaces the
 * 24-line hardcoded "Craft" markup that lived inline in MainLayout, so
 * we can stack multiple grouped dropdowns (CP, Loot, More, etc.) without
 * duplicating the open/close + 200ms close-delay logic each time.
 *
 * Each item: { label, route, icon? (text emoji/svg string), hint?, condition? (bool) }.
 * Items with `condition === false` are skipped.
 */
const props = defineProps({
    label: { type: String, required: true },
    items: { type: Array, required: true },
    accent: { type: String, default: 'purple' }, // purple | amber | blue
    forceActive: { type: Boolean, default: false },
});

const open = ref(false);
let closeTimer = null;

const openWithDelay = () => {
    if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
    open.value = true;
};
const scheduleClose = () => {
    if (closeTimer) clearTimeout(closeTimer);
    closeTimer = setTimeout(() => { open.value = false; }, 200);
};

const visibleItems = computed(() => props.items.filter((i) => i.condition !== false));

const isActive = computed(() => {
    if (props.forceActive) return true;
    // The route().current(...) calls live in the parent template (which
    // knows Ziggy's `route()` helper). The parent passes `active` per item;
    // we mark the trigger active if any visible item is.
    return visibleItems.value.some((i) => i.active === true);
});

const accentClasses = computed(() => {
    if (props.accent === 'amber') return 'text-amber-700 dark:text-amber-300';
    if (props.accent === 'blue') return 'text-blue-700 dark:text-blue-300';
    return 'text-purple-700 dark:text-purple-300';
});
</script>

<template>
    <div class="relative" @mouseenter="openWithDelay" @mouseleave="scheduleClose">
        <button
            type="button"
            class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition inline-flex items-center gap-1"
            :class="{ [accentClasses]: isActive }"
            @click="open = !open"
        >
            {{ label }}
            <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <!-- pt-2 invisible bridge so the cursor never exits between the
             trigger and the menu while crossing the gap -->
        <div v-if="open" class="absolute left-0 top-full pt-2 w-64 z-[50] max-h-[70vh] overflow-y-auto"
             @mouseenter="openWithDelay" @mouseleave="scheduleClose">
            <div class="bg-white border border-gray-200 dark:bg-gray-900 dark:border-gray-800 rounded-xl shadow-2xl py-2">
                <Link
                    v-for="item in visibleItems"
                    :key="item.label"
                    :href="item.route"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    :class="{ 'text-purple-700 dark:text-purple-300 font-bold': item.active }"
                    @click="open = false"
                >
                    <div class="font-bold flex items-center gap-2">
                        <span v-if="item.icon" class="opacity-80">{{ item.icon }}</span>
                        <span>{{ item.label }}</span>
                    </div>
                    <div v-if="item.hint" class="text-[10px] text-gray-500 dark:text-gray-500 tracking-widest uppercase mt-0.5">
                        {{ item.hint }}
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
