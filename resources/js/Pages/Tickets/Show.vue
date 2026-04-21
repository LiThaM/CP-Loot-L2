<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const page = usePage();
const flash = computed(() => page.props.flash || {});

const props = defineProps({
    ticket:    { type: Object,  required: true },
    userRole:  { type: String,  default: 'member' },
    canReply:  { type: Boolean, default: false },
    canClose:  { type: Boolean, default: false },
    isCreator: { type: Boolean, default: false },
});

const isAdmin = computed(() => props.userRole === 'admin');

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

const formatDate = (val) => {
    if (!val) return '—';
    try { return new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(val)); }
    catch { return String(val); }
};

const isVideo = (att) => att.mime?.startsWith('video/') || /\.(mp4|webm|mov)$/i.test(att.path ?? '');
const attUrl  = (att) => `/storage/${att.path}`;

// ─── Lightbox ─────────────────────────────────────────────────────────────────
const lightbox = ref(null); // { url, isVideo }
const openLightbox = (att) => { lightbox.value = { url: attUrl(att), isVideo: isVideo(att) }; };
const closeLightbox = () => { lightbox.value = null; };

// ─── Reply ─────────────────────────────────────────────────────────────────────
const replyForm = useForm({ message: '', attachments: [] });
const replyPreviews = ref([]);

const onReplyFilePick = (e) => {
    const files = Array.from(e.target.files ?? []);
    const combined = [...(replyForm.attachments ?? []), ...files].slice(0, 5);
    replyForm.attachments = combined;
    replyPreviews.value = combined.map(f => ({
        name: f.name,
        url: URL.createObjectURL(f),
        isVideo: f.type.startsWith('video/'),
    }));
};

const removeReplyAttachment = (idx) => {
    replyForm.attachments = replyForm.attachments.filter((_, i) => i !== idx);
    replyPreviews.value = replyPreviews.value.filter((_, i) => i !== idx);
};

const sendReply = () => {
    replyForm.post(route('tickets.reply', props.ticket.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            replyPreviews.value = [];
        },
    });
};

// ─── Close / Reopen ────────────────────────────────────────────────────────────
const closing = ref(false);

const closeTicket = () => {
    closing.value = true;
    router.post(route('tickets.close', props.ticket.id), {}, {
        preserveScroll: true,
        onFinish: () => closing.value = false,
    });
};

const reopenTicket = () => {
    router.post(route('tickets.reopen', props.ticket.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="ticket.ticket_number ?? '#' + ticket.id" />

    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <a :href="route('tickets.index')"
                       class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 uppercase tracking-widest font-bold transition">
                        {{ $t('tickets.show.back') }}
                    </a>
                    <h2 class="font-cinzel text-2xl text-gray-900 dark:text-white tracking-widest uppercase mt-1">
                        {{ ticket.ticket_number ?? '#' + ticket.id }}
                    </h2>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span :class="['px-2.5 py-1 rounded text-xs font-semibold', typeClass(ticket.type)]">
                        {{ $t(typeKey(ticket.type)) }}
                    </span>
                    <span :class="[
                        'px-2.5 py-1 rounded text-xs font-semibold border',
                        ticket.status === 'open'
                            ? 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:text-green-300 dark:border-green-700'
                            : 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-700/60 dark:text-gray-400 dark:border-gray-600'
                    ]">
                        {{ ticket.status === 'open' ? $t('tickets.status.open') : $t('tickets.status.closed') }}
                    </span>
                    <button
                        v-if="canClose && ticket.status === 'open'"
                        @click="closeTicket"
                        :disabled="closing"
                        class="px-3 py-1.5 text-xs bg-white hover:bg-gray-50 text-gray-700 font-semibold rounded-lg border border-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-200 dark:border-gray-700 transition disabled:opacity-50"
                    >
                        {{ $t('tickets.show.btn.close') }}
                    </button>
                    <button
                        v-if="isAdmin && ticket.status === 'closed'"
                        @click="reopenTicket"
                        class="px-3 py-1.5 text-xs bg-green-100 hover:bg-green-200 text-green-700 font-semibold rounded-lg border border-green-200 dark:bg-green-900/30 dark:hover:bg-green-900/50 dark:text-green-300 dark:border-green-800 transition"
                    >
                        {{ $t('tickets.show.btn.reopen') }}
                    </button>
                </div>
            </div>
        </template>

        <!-- Flash -->
        <div v-if="flash.success" class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300 rounded-xl text-sm">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300 rounded-xl text-sm">
            {{ flash.error }}
        </div>

        <div class="space-y-5">
            <!-- Ticket original message -->
            <div class="l2-panel rounded-2xl border-gray-200 dark:border-gray-800 overflow-hidden">
                <!-- Meta row -->
                <div class="px-5 py-4 border-b border-gray-200/70 dark:border-gray-800 flex flex-wrap gap-x-6 gap-y-2 text-xs">
                    <span>
                        <span class="font-black uppercase tracking-widest text-gray-400 dark:text-gray-600">{{ $t('tickets.show.from') }}: </span>
                        <span class="text-gray-700 dark:text-gray-300">{{ ticket.creator?.name ?? '—' }}</span>
                    </span>
                    <span v-if="ticket.assigned_to">
                        <span class="font-black uppercase tracking-widest text-gray-400 dark:text-gray-600">{{ $t('tickets.show.assigned_to') }}: </span>
                        <span class="text-gray-700 dark:text-gray-300">{{ ticket.assigned_to.name }}</span>
                    </span>
                    <span v-else-if="ticket.type === 'bug' || ticket.type === 'support'">
                        <span class="font-black uppercase tracking-widest text-gray-400 dark:text-gray-600">{{ $t('tickets.show.assigned_to') }}: </span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $t('tickets.show.admin_assignee') }}</span>
                    </span>
                    <span>
                        <span class="font-black uppercase tracking-widest text-gray-400 dark:text-gray-600">{{ $t('tickets.show.date') }}: </span>
                        <span class="text-gray-700 dark:text-gray-300">{{ formatDate(ticket.created_at) }}</span>
                    </span>
                    <span v-if="ticket.closed_at">
                        <span class="font-black uppercase tracking-widest text-gray-400 dark:text-gray-600">{{ $t('tickets.show.closed_at') }}: </span>
                        <span class="text-gray-700 dark:text-gray-300">{{ formatDate(ticket.closed_at) }}</span>
                    </span>
                </div>
                <!-- Body -->
                <div class="px-5 py-5">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">{{ ticket.subject }}</h3>
                    <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed whitespace-pre-line">{{ ticket.message }}</p>

                    <!-- Ticket attachments -->
                    <div v-if="ticket.attachments && ticket.attachments.length > 0" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="(att, idx) in ticket.attachments"
                            :key="idx"
                            type="button"
                            @click="openLightbox(att)"
                            class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-purple-400 dark:hover:border-purple-600 transition group"
                        >
                            <video
                                v-if="isVideo(att)"
                                :src="attUrl(att)"
                                class="w-24 h-16 object-cover"
                                muted
                            />
                            <img
                                v-else
                                :src="attUrl(att)"
                                :alt="att.name"
                                class="w-24 h-16 object-cover"
                            />
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition flex items-center justify-center">
                                <svg v-if="isVideo(att)" class="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                <svg v-else class="w-5 h-5 text-white opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Replies -->
            <div v-if="ticket.replies && ticket.replies.length > 0" class="space-y-3">
                <h4 class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">
                    {{ $t('tickets.show.replies_title') }}
                </h4>
                <div
                    v-for="reply in ticket.replies"
                    :key="reply.id"
                    :class="[
                        'rounded-2xl border px-5 py-4',
                        reply.is_mine
                            ? 'bg-purple-50 border-purple-200 dark:bg-purple-950/30 dark:border-purple-800/40 ml-8'
                            : 'bg-white border-gray-200 dark:bg-gray-900/40 dark:border-gray-700/60 mr-8'
                    ]"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span
                            class="text-sm font-semibold"
                            :class="reply.is_mine
                                ? 'text-purple-700 dark:text-purple-300'
                                : 'text-gray-900 dark:text-gray-200'"
                        >
                            {{ reply.user.name }}
                            <span v-if="reply.is_mine" class="text-xs font-normal text-gray-400 ml-1">
                                {{ $t('tickets.show.reply_you') }}
                            </span>
                        </span>
                        <span class="text-xs text-gray-400 dark:text-gray-600">{{ formatDate(reply.created_at) }}</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ reply.message }}</p>

                    <!-- Reply attachments -->
                    <div v-if="reply.attachments && reply.attachments.length > 0" class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="(att, idx) in reply.attachments"
                            :key="idx"
                            type="button"
                            @click="openLightbox(att)"
                            class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-purple-400 dark:hover:border-purple-600 transition group"
                        >
                            <video
                                v-if="isVideo(att)"
                                :src="attUrl(att)"
                                class="w-24 h-16 object-cover"
                                muted
                            />
                            <img
                                v-else
                                :src="attUrl(att)"
                                :alt="att.name"
                                class="w-24 h-16 object-cover"
                            />
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition flex items-center justify-center">
                                <svg v-if="isVideo(att)" class="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                <svg v-else class="w-5 h-5 text-white opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Reply form -->
            <div v-if="canReply" class="l2-panel rounded-2xl border-gray-200 dark:border-gray-800 p-5">
                <h4 class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3">
                    {{ $t('tickets.show.reply_title') }}
                </h4>
                <form @submit.prevent="sendReply" class="space-y-3">
                    <textarea
                        v-model="replyForm.message"
                        rows="4"
                        maxlength="5000"
                        :placeholder="$t('tickets.show.reply_placeholder')"
                        class="w-full bg-white border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 focus:border-purple-500 dark:bg-black/40 dark:border-gray-700 dark:text-gray-200 dark:placeholder-gray-600 p-3 text-sm transition resize-none"
                    />
                    <p v-if="replyForm.errors.message" class="text-red-600 dark:text-red-400 text-xs">{{ replyForm.errors.message }}</p>

                    <!-- Attachments -->
                    <div>
                        <label
                            v-if="replyPreviews.length < 5"
                            class="flex items-center gap-2 px-3 py-2.5 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500 hover:border-purple-400 dark:hover:border-purple-600 hover:text-purple-500 dark:hover:text-purple-400 cursor-pointer transition text-xs"
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
                                @change="onReplyFilePick"
                            />
                        </label>

                        <div v-if="replyPreviews.length > 0" class="mt-2 flex flex-wrap gap-2">
                            <div
                                v-for="(preview, idx) in replyPreviews"
                                :key="idx"
                                class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30"
                            >
                                <video v-if="preview.isVideo" :src="preview.url" class="w-20 h-14 object-cover" muted />
                                <img v-else :src="preview.url" :alt="preview.name" class="w-20 h-14 object-cover" />
                                <button
                                    type="button"
                                    @click="removeReplyAttachment(idx)"
                                    class="absolute top-0.5 right-0.5 w-4 h-4 bg-black/60 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition"
                                >
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <p v-if="replyForm.errors['attachments.0'] || replyForm.errors.attachments" class="text-red-600 dark:text-red-400 text-xs mt-1">
                            {{ replyForm.errors['attachments.0'] || replyForm.errors.attachments }}
                        </p>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="replyForm.processing || !replyForm.message.trim()"
                            class="px-5 py-2 bg-gradient-to-r from-purple-700 to-indigo-600 hover:from-purple-600 hover:to-indigo-500 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition"
                        >
                            {{ $t('tickets.show.btn.reply') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Closed message -->
            <div v-else-if="ticket.status === 'closed'" class="text-center py-6 text-gray-400 dark:text-gray-600 text-sm">
                {{ $t('tickets.show.closed_message') }}
            </div>
        </div>
    </MainLayout>

    <!-- Lightbox -->
    <Teleport to="body">
        <div
            v-if="lightbox"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 backdrop-blur-sm"
            @click.self="closeLightbox"
        >
            <button
                @click="closeLightbox"
                class="absolute top-4 right-4 w-9 h-9 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center transition"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <video
                v-if="lightbox.isVideo"
                :src="lightbox.url"
                controls
                autoplay
                class="max-w-[90vw] max-h-[85vh] rounded-xl"
            />
            <img
                v-else
                :src="lightbox.url"
                class="max-w-[90vw] max-h-[85vh] rounded-xl object-contain"
            />
        </div>
    </Teleport>
</template>
