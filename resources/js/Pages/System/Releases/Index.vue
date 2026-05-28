<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { ref, computed } from 'vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import EmptyState from '@/Components/Admin/EmptyState.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import { CloudArrowUpIcon, ArchiveBoxIcon } from '@heroicons/vue/24/outline';
import { confirmAction } from '@/utils/swal';

const props = defineProps({
    releases: Array,
});

const showForm = ref(false);

const publishedCount = computed(() => (props.releases || []).filter((r) => r.published_at).length);
const totalDownloads = computed(() => (props.releases || []).reduce((s, r) => s + Number(r.download_count || 0), 0));
const lastReleaseDate = computed(() => {
    const r = (props.releases || []).find((x) => x.released_at);
    return r?.released_at ? String(r.released_at).slice(0, 10) : '—';
});

const form = useForm({
    name: '',
    version: '',
    channel: 'stable',
    critical_update: false,
    min_supported_version: '',
    release_notes_es: '',
    release_notes_en: '',
    binary: null,
    publish_now: false,
});

const submit = () => {
    form.post(route('system.releases.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
};

const togglePublish = (release) => {
    router.post(route('system.releases.toggle_publish', release.id), {}, {
        preserveScroll: true,
    });
};

const destroy = async (release) => {
    if (await confirmAction('Delete release?', `Permanently delete ${release.version}?`, 'Delete', 'Cancel')) {
        router.delete(route('system.releases.destroy', release.id), { preserveScroll: true });
    }
};

const human = (bytes) => {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return `${bytes.toFixed(1)} ${units[i]}`;
};
</script>

<template>
    <Head title="Releases" />
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
            <AdminPageHeader title="Releases" subtitle="AdenaLedgerStats — desktop bot">
                <template #actions>
                    <PrimaryButton @click="showForm = !showForm">
                        <CloudArrowUpIcon class="w-4 h-4 mr-2" aria-hidden="true" />
                        {{ showForm ? 'Cancel' : 'New release' }}
                    </PrimaryButton>
                </template>
            </AdminPageHeader>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard label="Releases" :value="releases.length" emoji="📦" accent="neutral" />
                <StatCard label="Published" :value="publishedCount" emoji="🚀" accent="emerald" />
                <StatCard label="Total downloads" :value="totalDownloads" emoji="⬇️" accent="purple" prominent />
                <StatCard label="Last release" :value="lastReleaseDate" emoji="🕒" accent="blue" />
            </div>

            <div v-if="showForm" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold dark:text-white mb-4">Upload new release</h2>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="version" value="Version *" />
                            <TextInput id="version" v-model="form.version" placeholder="0.5.1-alpha" class="w-full" required />
                            <InputError :message="form.errors.version" />
                        </div>
                        <div>
                            <InputLabel for="name" value="Name (optional)" />
                            <TextInput id="name" v-model="form.name" placeholder="AdenaLedgerStats 0.5.1" class="w-full" />
                        </div>
                        <div>
                            <InputLabel for="channel" value="Channel *" />
                            <select id="channel" v-model="form.channel" class="w-full border-gray-300 dark:bg-gray-900 dark:text-white rounded">
                                <option value="stable">stable</option>
                                <option value="beta">beta</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="min_v" value="Minimum supported version" />
                            <TextInput id="min_v" v-model="form.min_supported_version" placeholder="0.4.0-alpha" class="w-full" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="binary" value="Binary (.exe or .zip) *" />
                        <input id="binary" type="file" accept=".exe,.zip,application/octet-stream"
                               @change="form.binary = $event.target.files[0]"
                               class="block w-full text-sm text-gray-700 dark:text-gray-200" required />
                        <InputError :message="form.errors.binary" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="notes_es" value="Release notes — ES (markdown)" />
                            <textarea id="notes_es" v-model="form.release_notes_es" rows="6"
                                      class="w-full border-gray-300 dark:bg-gray-900 dark:text-white rounded font-mono text-sm"></textarea>
                        </div>
                        <div>
                            <InputLabel for="notes_en" value="Release notes — EN (markdown)" />
                            <textarea id="notes_en" v-model="form.release_notes_en" rows="6"
                                      class="w-full border-gray-300 dark:bg-gray-900 dark:text-white rounded font-mono text-sm"></textarea>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-6 items-center">
                        <label class="inline-flex items-center gap-2 dark:text-gray-200">
                            <Checkbox v-model:checked="form.critical_update" />
                            <span>Critical update</span>
                        </label>
                        <label class="inline-flex items-center gap-2 dark:text-gray-200">
                            <Checkbox v-model:checked="form.publish_now" />
                            <span>Publish immediately</span>
                        </label>
                    </div>

                    <div class="flex gap-3">
                        <PrimaryButton :disabled="form.processing" type="submit">
                            Upload
                        </PrimaryButton>
                        <SecondaryButton @click="showForm = false" type="button">
                            Cancel
                        </SecondaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr class="text-left text-gray-700 dark:text-gray-300">
                            <th class="px-4 py-3">Version</th>
                            <th class="px-4 py-3">Channel</th>
                            <th class="px-4 py-3">Critical</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">SHA-256</th>
                            <th class="px-4 py-3">Downloads</th>
                            <th class="px-4 py-3">Released</th>
                            <th class="px-4 py-3">Published</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="r in releases" :key="r.id" class="dark:text-gray-100">
                            <td class="px-4 py-2 font-mono">{{ r.version }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded text-xs"
                                      :class="r.channel === 'stable' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'">
                                    {{ r.channel }}
                                </span>
                            </td>
                            <td class="px-4 py-2">{{ r.critical_update ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-2">{{ human(r.size_bytes) }}</td>
                            <td class="px-4 py-2 font-mono text-xs truncate max-w-[8rem]" :title="r.sha256">{{ r.sha256?.slice(0, 12) }}…</td>
                            <td class="px-4 py-2">{{ r.download_count }}</td>
                            <td class="px-4 py-2">{{ r.released_at?.slice(0, 10) }}</td>
                            <td class="px-4 py-2">
                                <span v-if="r.published_at" class="text-green-600 dark:text-green-400">{{ r.published_at?.slice(0, 10) }}</span>
                                <span v-else class="text-gray-400">draft</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="inline-flex gap-2">
                                    <SecondaryButton @click="togglePublish(r)">
                                        {{ r.published_at ? 'Unpublish' : 'Publish' }}
                                    </SecondaryButton>
                                    <DangerButton @click="destroy(r)">Delete</DangerButton>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!releases.length">
                            <td colspan="9" class="px-0 py-0">
                                <EmptyState
                                    :icon="ArchiveBoxIcon"
                                    title="No releases yet"
                                    description="Upload your first build using the form above."
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>
