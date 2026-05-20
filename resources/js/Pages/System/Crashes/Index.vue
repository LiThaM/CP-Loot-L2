<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { confirmAction } from '@/utils/swal';

const props = defineProps({
    groups: Array,
});

const destroy = async (group) => {
    if (await confirmAction('Delete group?', `Delete all ${group.count} crashes for this fingerprint?`, 'Delete', 'Cancel')) {
        router.delete(route('system.crashes.destroy', group.fingerprint), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Crashes" />
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
            <h1 class="text-2xl font-bold dark:text-white">Crash reports</h1>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr class="text-left text-gray-700 dark:text-gray-300">
                            <th class="px-4 py-3">Fingerprint</th>
                            <th class="px-4 py-3">Sample message</th>
                            <th class="px-4 py-3">Bot version</th>
                            <th class="px-4 py-3">Count</th>
                            <th class="px-4 py-3">First seen</th>
                            <th class="px-4 py-3">Last seen</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="g in groups" :key="g.fingerprint" class="dark:text-gray-100">
                            <td class="px-4 py-2 font-mono text-xs">
                                <Link :href="route('system.crashes.show', g.fingerprint)" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ g.fingerprint.slice(0, 12) }}…
                                </Link>
                            </td>
                            <td class="px-4 py-2 truncate max-w-md" :title="g.sample_message">{{ g.sample_message || '—' }}</td>
                            <td class="px-4 py-2 font-mono">{{ g.last_bot_version }}</td>
                            <td class="px-4 py-2 font-semibold">{{ g.count }}</td>
                            <td class="px-4 py-2">{{ g.first_seen?.slice(0, 16) }}</td>
                            <td class="px-4 py-2">{{ g.last_seen?.slice(0, 16) }}</td>
                            <td class="px-4 py-2 text-right">
                                <DangerButton @click="destroy(g)">Delete group</DangerButton>
                            </td>
                        </tr>
                        <tr v-if="!groups.length">
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No crashes reported yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>
