<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const page = usePage();
const flash = computed(() => page.props.flash || {});

const props = defineProps({
    tickets: { type: Array, default: () => [] },
    userRole: { type: String, default: 'member' },
});

const isAdmin  = computed(() => props.userRole === 'admin');
const isLeader = computed(() => props.userRole === 'cp_leader');

// ─── Create modal ─────────────────────────────────────────────────────────────
const showCreate = ref(false);

const allowedTypes = computed(() => {
    if (isLeader.value) return [{ value: 'bug', labelKey: 'tickets.type.bug_full' }];
    return [
        { value: 'bug',              labelKey: 'tickets.type.bug_full' },
        { value: 'data_discrepancy', labelKey: 'tickets.type.data_discrepancy_full' },
    ];
});

const form = useForm({ subject: '', message: '', type: 'bug', attachments: [] });

const attachmentPreviews = ref([]);

const onFilePick = (e) => {
    const files = Array.from(e.target.files ?? []);
    const combined = [...(form.attachments ?? []), ...files].slice(0, 5);
    form.attachments = combined;
    attachmentPreviews.value = combined.map(f => ({
        name: f.name,
        url: URL.createObjectURL(f),
        isVideo: f.type.startsWith('video/'),
    }));
};

const removeAttachment = (idx) => {
    form.attachments = form.attachments.filter((_, i) => i !== idx);
    attachmentPreviews.value = attachmentPreviews.value.filter((_, i) => i !== idx);
};

const submit = () => {
    form.post(route('tickets.store'), {
        forceFormData: true,
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
            attachmentPreviews.value = [];
        },
    });
};

const openCreate = () => {
    form.reset();
    attachmentPreviews.value = [];
    showCreate.value = true;
};

// ─── Filters ──────────────────────────────────────────────────────────────────
const statusFilter = ref('all');
const filtered = computed(() => {
    if (statusFilter.value === 'all') return props.tickets;
    return props.tickets.filter(t => t.status === statusFilter.value);
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
const typeKey = (type) => ({
    bug:              'tickets.type.bug',
    data_discrepancy: 'tickets.type.data_discrepancy',
    support:          'tickets.type.support',
}[type] ?? type);

const typeClass = (type) => ({
    bug:              'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/40 dark:text-red-300 dark:border-red-700',
    data_discrepancy: 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/40 dark:text-yellow-300 dark:border-yellow-700',
    support:          'bg-blue-100 text-blue-700 border border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-700',
}[type] ?? 'bg-gray-100 text-gray-600 border border-gray-200 dark:bg-gray-700 dark:text-gray-300');

const statusClass = (status) => status === 'open'
    ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/40 dark:text-green-300 dark:border-green-700'
    : 'bg-gray-100 text-gray-500 border border-gray-200 dark:bg-gray-700/60 dark:text-gray-400 dark:border-gray-600';

const formatDate = (val) => {
    if (!val) return '—';
    try { return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(val)); }
    catch { return String(val); }
};
</script>

<template>
    <Head :title="$t('tickets.title')" />

    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">
                        {{ $t('tickets.title') }}
                    </h2>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">
                        {{ isAdmin ? $t('tickets.subtitle.admin') : isLeader ? $t('tickets.subtitle.leader') : $t('tickets.subtitle.member') }}
                    </p>
                </div>
                <button
                    v-if="!isAdmin"
                    @click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-gray-50 text-gray-900 border border-gray-200 dark:bg-gray-900/40 dark:hover:bg-gray-900/60 dark:text-gray-200 dark:border-gray-800 text-sm font-semibold rounded-xl transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ $t('tickets.btn.new') }}
                </button>
            </div>
        </template>

        <!-- Flash -->
        <div v-if="flash.success" class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300 rounded-xl text-sm">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300 rounded-xl text-sm">
            {{ flash.error }}
        </div>

        <!-- Status filter -->
        <div class="mb-4 flex gap-2">
            <button
                v-for="opt in [
                    { value: 'all',    labelKey: 'tickets.filter.all'    },
                    { value: 'open',   labelKey: 'tickets.filter.open'   },
                    { value: 'closed', labelKey: 'tickets.filter.closed' },
                ]"
                :key="opt.value"
                @click="statusFilter = opt.value"
                :class="[
                    'px-3 py-1.5 text-xs font-semibold rounded-lg border transition',
                    statusFilter === opt.value
                        ? 'bg-purple-600 border-purple-600 text-white'
                        : 'bg-white text-gray-600 border-gray-200 hover:border-purple-400 hover:text-gray-900 dark:bg-gray-900/40 dark:text-gray-400 dark:border-gray-700 dark:hover:border-purple-600 dark:hover:text-gray-200'
                ]"
            >
                {{ $t(opt.labelKey) }}
            </button>
        </div>

        <!-- Table -->
        <div class="l2-panel rounded-2xl border-gray-200 dark:border-gray-800 overflow-hidden">
            <div v-if="filtered.length === 0" class="py-12 text-center text-gray-400 dark:text-gray-500 text-sm">
                {{ $t('tickets.table.empty') }}
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="bg-white/70 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-800">
                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tickets.table.number') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tickets.table.subject') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tickets.table.type') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tickets.table.status') }}</th>
                        <th v-if="isAdmin || isLeader" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tickets.table.from') }}</th>
                        <th v-if="isAdmin" class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tickets.table.assigned_to') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tickets.table.replies') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tickets.table.date') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/70 dark:divide-gray-800/50">
                    <tr
                        v-for="ticket in filtered"
                        :key="ticket.id"
                        class="transition hover:bg-purple-500/5 dark:hover:bg-purple-950/10"
                    >
                        <td class="px-4 py-3 text-gray-400 dark:text-gray-500 font-mono text-xs">
                            {{ ticket.ticket_number ?? '#' + ticket.id }}
                        </td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-200 font-medium max-w-xs truncate">
                            {{ ticket.subject }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded text-xs font-semibold', typeClass(ticket.type)]">
                                {{ $t(typeKey(ticket.type)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded text-xs font-semibold', statusClass(ticket.status)]">
                                {{ ticket.status === 'open' ? $t('tickets.status.open') : $t('tickets.status.closed') }}
                            </span>
                        </td>
                        <td v-if="isAdmin || isLeader" class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">
                            {{ ticket.creator?.name ?? '—' }}
                        </td>
                        <td v-if="isAdmin" class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">
                            {{ ticket.assigned_to?.name ?? $t('tickets.show.admin_assignee') }}
                        </td>
                        <td class="px-4 py-3 text-gray-400 dark:text-gray-500 text-xs text-center">
                            {{ ticket.replies_count }}
                        </td>
                        <td class="px-4 py-3 text-gray-400 dark:text-gray-500 text-xs whitespace-nowrap">
                            {{ formatDate(ticket.created_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <a
                                :href="route('tickets.show', ticket.id)"
                                class="text-xs text-purple-600 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300 font-semibold"
                            >
                                {{ $t('tickets.table.view') }} →
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create modal -->
        <div
            v-if="showCreate"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 dark:bg-black/80 backdrop-blur-sm"
            @click.self="showCreate = false"
        >
            <div class="l2-panel border-gray-200 dark:border-gray-700 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden max-h-[90vh] flex flex-col">
                <div class="bg-white/70 dark:bg-gray-900/50 px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between flex-shrink-0">
                    <h3 class="font-cinzel text-lg text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('tickets.modal.title') }}</h3>
                    <button @click="showCreate = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="overflow-y-auto flex-1">
                    <form @submit.prevent="submit" class="p-6 space-y-4">
                        <!-- Type selector -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                                {{ $t('tickets.modal.type_label') }}
                            </label>
                            <div class="flex gap-2">
                                <button
                                    v-for="opt in allowedTypes"
                                    :key="opt.value"
                                    type="button"
                                    @click="form.type = opt.value"
                                    :class="[
                                        'flex-1 px-3 py-2 rounded-xl border text-sm font-semibold transition',
                                        form.type === opt.value
                                            ? (opt.value === 'bug'
                                                ? 'bg-red-600 border-red-600 text-white'
                                                : 'bg-yellow-500 border-yellow-500 text-white')
                                            : 'bg-white/60 border-gray-200 text-gray-700 hover:border-gray-300 dark:bg-black/30 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-500'
                                    ]"
                                >
                                    {{ $t(opt.labelKey) }}
                                </button>
                            </div>
                            <p v-if="form.type === 'data_discrepancy'" class="text-xs text-yellow-600 dark:text-yellow-400 mt-1.5">
                                {{ $t('tickets.modal.hint.discrepancy') }}
                            </p>
                            <p v-else-if="form.type === 'bug'" class="text-xs text-red-600 dark:text-red-400 mt-1.5">
                                {{ $t('tickets.modal.hint.bug') }}
                            </p>
                            <p v-if="form.errors.type" class="text-red-600 text-xs mt-1">{{ form.errors.type }}</p>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                                {{ $t('tickets.modal.subject_label') }}
                            </label>
                            <input
                                v-model="form.subject"
                                type="text"
                                maxlength="140"
                                :placeholder="$t('tickets.modal.subject_placeholder')"
                                class="w-full bg-white border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 focus:border-purple-500 dark:bg-black/40 dark:border-gray-700 dark:text-gray-200 dark:placeholder-gray-600 p-3 text-sm transition"
                            />
                            <p v-if="form.errors.subject" class="text-red-600 text-xs mt-1">{{ form.errors.subject }}</p>
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                                {{ $t('tickets.modal.message_label') }}
                            </label>
                            <textarea
                                v-model="form.message"
                                rows="5"
                                maxlength="5000"
                                :placeholder="$t('tickets.modal.message_placeholder')"
                                class="w-full bg-white border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 focus:border-purple-500 dark:bg-black/40 dark:border-gray-700 dark:text-gray-200 dark:placeholder-gray-600 p-3 text-sm transition resize-none"
                            />
                            <p v-if="form.errors.message" class="text-red-600 text-xs mt-1">{{ form.errors.message }}</p>
                        </div>

                        <!-- Attachments -->
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                                {{ $t('tickets.modal.attachments_label') }}
                                <span class="normal-case font-normal text-gray-400 ml-1">({{ $t('tickets.modal.attachments_hint') }})</span>
                            </label>

                            <!-- File picker -->
                            <label
                                v-if="attachmentPreviews.length < 5"
                                class="flex items-center gap-2 px-4 py-3 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500 hover:border-purple-400 dark:hover:border-purple-600 hover:text-purple-500 dark:hover:text-purple-400 cursor-pointer transition text-sm"
                            >
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                {{ $t('tickets.modal.attachments_pick') }}
                                <input
                                    type="file"
                                    multiple
                                    accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime"
                                    class="hidden"
                                    @change="onFilePick"
                                />
                            </label>

                            <!-- Previews -->
                            <div v-if="attachmentPreviews.length > 0" class="mt-2 grid grid-cols-3 gap-2">
                                <div
                                    v-for="(preview, idx) in attachmentPreviews"
                                    :key="idx"
                                    class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30"
                                >
                                    <video
                                        v-if="preview.isVideo"
                                        :src="preview.url"
                                        class="w-full h-20 object-cover"
                                    />
                                    <img
                                        v-else
                                        :src="preview.url"
                                        :alt="preview.name"
                                        class="w-full h-20 object-cover"
                                    />
                                    <button
                                        type="button"
                                        @click="removeAttachment(idx)"
                                        class="absolute top-1 right-1 w-5 h-5 bg-black/60 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 px-1 py-0.5 truncate">{{ preview.name }}</p>
                                </div>
                            </div>
                            <p v-if="form.errors['attachments.0'] || form.errors.attachments" class="text-red-600 text-xs mt-1">
                                {{ form.errors['attachments.0'] || form.errors.attachments }}
                            </p>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                @click="showCreate = false"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition"
                            >
                                {{ $t('tickets.modal.btn.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-5 py-2 bg-gradient-to-r from-purple-700 to-indigo-600 hover:from-purple-600 hover:to-indigo-500 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition"
                            >
                                {{ $t('tickets.modal.btn.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
