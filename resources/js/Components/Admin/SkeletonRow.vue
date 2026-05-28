<script setup>
defineProps({
    cols: { type: Number, default: 4 },
    rows: { type: Number, default: 3 },
});
</script>

<template>
    <template v-for="r in rows" :key="`sk-${r}`">
        <tr class="animate-pulse">
            <td v-for="c in cols" :key="`sk-${r}-${c}`" class="px-4 py-3">
                <div class="h-3 bg-gray-200 dark:bg-gray-800 rounded"></div>
            </td>
        </tr>
    </template>
</template>
