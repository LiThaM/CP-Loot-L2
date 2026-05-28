<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { confirmAction } from '@/utils/swal';
import { computed } from 'vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import EmptyState from '@/Components/Admin/EmptyState.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import { BugAntIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    groups: Array,
});

const totalCrashes = computed(() => (props.groups || []).reduce((s, g) => s + Number(g.count || 0), 0));
const distinctVersions = computed(() => new Set((props.groups || []).map((g) => g.last_bot_version).filter(Boolean)).size);

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
            <AdminPageHeader title="Crash reports" subtitle="Desktop bot crash fingerprints" />

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <StatCard label="Fingerprints" :value="groups.length" emoji="🪲" accent="red" prominent />
                <StatCard label="Crashes totales" :value="totalCrashes" emoji="📉" accent="amber" />
                <StatCard label="Versiones afectadas" :value="distinctVersions" emoji="🛠️" accent="neutral" />
            </div>

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
                            <td colspan="7" class="px-0 py-0">
                                <EmptyState
                                    :icon="ShieldCheckIcon"
                                    title="No hay crashes"
                                    description="El bot no ha reportado ningún crash todavía. ¡Buena señal!"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>
