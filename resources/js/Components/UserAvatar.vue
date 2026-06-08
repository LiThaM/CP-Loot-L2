<script setup>
import { computed } from 'vue';

/**
 * Reusable user avatar with an initials fallback. Replaces the hardcoded
 * `{{ user.name.charAt(0) }}` boxes that lived in 5+ places. When the
 * user has uploaded a custom image (`user.avatar_url` populated by the
 * `getAvatarUrlAttribute` accessor on the User model), we render the
 * picture; otherwise the first letter of `name` in a coloured square.
 */
const props = defineProps({
    user: { type: Object, required: true },
    size: { type: String, default: 'md' }, // xs | sm | md | lg | xl
    square: { type: Boolean, default: false },
});

const sizeMap = {
    xs: { box: 'w-6 h-6', text: 'text-[10px]' },
    sm: { box: 'w-8 h-8', text: 'text-xs' },
    md: { box: 'w-12 h-12', text: 'text-lg' },
    lg: { box: 'w-14 h-14', text: 'text-2xl' },
    xl: { box: 'w-20 h-20', text: 'text-3xl' },
};

const sz = computed(() => sizeMap[props.size] || sizeMap.md);
const rounded = computed(() => props.square ? 'rounded-2xl' : 'rounded-full');
const initial = computed(() => {
    const n = props.user?.name || '';
    return n ? n.charAt(0).toUpperCase() : '?';
});
const url = computed(() => props.user?.avatar_url || null);
</script>

<template>
    <img
        v-if="url"
        :src="url"
        :alt="user?.name || ''"
        class="object-cover border border-purple-300/40 dark:border-purple-700/40"
        :class="[sz.box, rounded]"
    />
    <div
        v-else
        class="bg-purple-200 dark:bg-purple-900/60 text-purple-900 dark:text-purple-100 flex items-center justify-center font-cinzel font-bold border border-purple-300/40"
        :class="[sz.box, sz.text, rounded]"
    >
        {{ initial }}
    </div>
</template>
