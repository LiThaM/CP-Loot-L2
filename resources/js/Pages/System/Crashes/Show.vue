<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineProps({
    fingerprint: String,
    reports: Array,
});
</script>

<template>
    <Head :title="`Crash ${fingerprint.slice(0,12)}…`" />
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
            <div>
                <Link :href="route('system.crashes.index')" class="text-blue-600 dark:text-blue-400 hover:underline">← Back to crashes</Link>
                <h1 class="text-2xl font-bold dark:text-white mt-2 font-mono break-all">{{ fingerprint }}</h1>
            </div>

            <div v-for="r in reports" :key="r.id" class="bg-white dark:bg-gray-800 shadow rounded p-4 space-y-2">
                <div class="text-xs text-gray-500 dark:text-gray-400 flex flex-wrap gap-3">
                    <span>#{{ r.id }}</span>
                    <span>{{ r.reported_at }}</span>
                    <span>{{ r.bot_version }}</span>
                    <span>{{ r.os_version }}</span>
                    <span>{{ r.python_version }}</span>
                </div>
                <div v-if="r.message" class="font-semibold text-red-700 dark:text-red-400">{{ r.message }}</div>
                <pre class="bg-gray-900 text-green-300 rounded p-3 text-xs overflow-x-auto whitespace-pre-wrap">{{ r.stack_trace }}</pre>
                <details v-if="r.context_json" class="text-xs">
                    <summary class="cursor-pointer text-gray-600 dark:text-gray-400">context</summary>
                    <pre class="bg-gray-100 dark:bg-gray-900 rounded p-2 mt-2">{{ JSON.stringify(r.context_json, null, 2) }}</pre>
                </details>
            </div>
        </div>
    </MainLayout>
</template>
