<script setup>
defineProps({
    showRemaining: {
        type: Boolean,
        default: false,
    },
    remainingCount: {
        type: Number,
        default: 0,
    },
    remainingLabel: {
        type: String,
        default: '',
    },
    canLoadMore: {
        type: Boolean,
        default: false,
    },
    loadMoreLabel: {
        type: String,
        default: '',
    },
});

defineEmits(['load-more']);
</script>

<template>
    <div class="flex items-center justify-between gap-3 pt-2">
        <div v-if="showRemaining && remainingCount > 0" class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">
            +{{ remainingCount }} {{ remainingLabel }}
        </div>
        <button
            v-if="canLoadMore"
            type="button"
            class="ml-auto h-9 px-4 rounded-xl bg-gray-900/80 text-white border border-gray-700 text-[10px] font-black uppercase tracking-widest hover:bg-gray-900 dark:bg-gray-700/70 dark:border-gray-600"
            @click.stop="$emit('load-more')"
        >
            {{ loadMoreLabel }}
        </button>
    </div>
</template>
