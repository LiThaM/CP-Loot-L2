<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Line } from 'vue-chartjs';
import { confirmAction, showToast as swalToast } from '../utils/swal';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  CategoryScale,
  Filler
} from 'chart.js';

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  CategoryScale,
  Filler
);

const props = defineProps({
    stats: Object,
    cps: Array,
    chartData: Object,
    cpRequests: Array,
    supportTickets: Array,
});

const page = usePage();
const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    let out = raw;
    for (const [k, v] of Object.entries(params)) {
        const token = `{${k}}`;
        out = out.split(token).join(String(v));
    }
    return out;
};

const themeIsDark = ref(false);

const onThemeChanged = (e) => {
    themeIsDark.value = Boolean(e?.detail?.dark);
};

onMounted(() => {
    themeIsDark.value = document.documentElement.classList.contains('dark');
    window.addEventListener('theme-changed', onThemeChanged);
});

onBeforeUnmount(() => {
    window.removeEventListener('theme-changed', onThemeChanged);
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: themeIsDark.value ? 'rgba(0,0,0,0.8)' : 'rgba(255,255,255,0.92)',
            titleFont: { family: 'Cinzel', size: 14 },
            bodyFont: { family: 'Inter', size: 12 },
            padding: 12,
            borderColor: themeIsDark.value ? 'rgba(168, 85, 247, 0.45)' : 'rgba(168, 85, 247, 0.25)',
            borderWidth: 1,
            titleColor: themeIsDark.value ? '#e5e7eb' : '#111827',
            bodyColor: themeIsDark.value ? '#e5e7eb' : '#111827',
        }
    },
    scales: {
        y: {
            grid: { color: themeIsDark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)' },
            ticks: { color: themeIsDark.value ? '#6b7280' : '#4b5563', font: { size: 10 } }
        },
        x: {
            grid: { display: false },
            ticks: { color: themeIsDark.value ? '#6b7280' : '#4b5563', font: { size: 10 } }
        }
    }
}));

const getChronicleColor = (chronicle) => {
    const map = {
        'C1': 'text-sky-700 dark:text-sky-400',
        'C2': 'text-sky-700 dark:text-sky-400',
        'C3': 'text-sky-700 dark:text-sky-400',
        'IL': 'text-purple-700 dark:text-purple-400',
        'C4': 'text-blue-700 dark:text-blue-400',
        'C5': 'text-orange-700 dark:text-orange-400',
        'HB': 'text-emerald-700 dark:text-emerald-400',
        'LU4': 'text-fuchsia-700 dark:text-fuchsia-400',
        'Classic': 'text-gray-700 dark:text-gray-400',
    };
    return map[chronicle] || 'text-gray-700 dark:text-gray-500';
};

const showCreateModal = ref(false);
const cpForm = useForm({
    name: '',
    server: '',
    chronicle: 'IL',
});

const submitCp = () => {
    cpForm.post(route('admin.cp.store'), {
        onSuccess: () => {
            swalToast(t('admin.toast.cp_created'), 'success');
        },
        onError: () => {
            swalToast(t('admin.toast.cp_create_error'), 'error');
        }
    });
};

const approveRequest = async (req) => {
    const ok = await confirmAction(
        t('admin.confirm.approve_title'),
        t('admin.confirm.approve_text', { cpName: req.cp_name }),
        t('admin.confirm.approve_ok'),
        t('common.cancel'),
    );
    if (!ok) return;
    router.post(route('admin.cp-requests.approve', req.id), {}, {
        onSuccess: () => swalToast(t('admin.toast.cp_created_from_request'), 'success'),
    });
};

const rejectRequest = async (req) => {
    const ok = await confirmAction(
        t('admin.confirm.reject_title'),
        t('admin.confirm.reject_text', { cpName: req.cp_name }),
        t('admin.confirm.reject_ok'),
        t('common.cancel'),
    );
    if (!ok) return;
    router.post(route('admin.cp-requests.reject', req.id), {}, {
        onSuccess: () => swalToast(t('admin.toast.request_rejected'), 'success'),
    });
};
</script>

<template>
    <div class="space-y-8 animate-in fade-in duration-700">
        <!-- Global KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">🛡️</div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">{{ $t('admin.kpis.total_cps') }}</div>
                <div class="text-4xl font-cinzel text-gray-900 dark:text-white">{{ stats.total_cps }}</div>
                <div class="mt-2 text-[10px] text-green-500 font-bold uppercase tracking-widest">{{ $t('admin.kpis.total_cps_hint') }}</div>
            </div>

            <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">👥</div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">{{ $t('admin.kpis.total_members') }}</div>
                <div class="text-4xl font-cinzel text-gray-900 dark:text-white">{{ stats.total_members }}</div>
                <div class="mt-2 text-[10px] text-orange-500 font-bold uppercase tracking-widest">{{ $t('admin.kpis.total_members_hint') }}</div>
            </div>

            <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">⚔️</div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">{{ $t('admin.kpis.total_reports') }}</div>
                <div class="text-4xl font-cinzel text-gray-900 dark:text-white">{{ stats.total_reports }}</div>
                <div class="mt-2 text-[10px] text-purple-700 dark:text-purple-300 font-bold uppercase tracking-widest">{{ $t('admin.kpis.total_reports_hint') }}</div>
            </div>

            <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">💎</div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">{{ $t('admin.kpis.total_points') }}</div>
                <div class="text-4xl font-cinzel text-gray-900 dark:text-white">{{ stats.total_points_global }}</div>
                <div class="mt-2 text-[10px] text-blue-500 font-bold uppercase tracking-widest">{{ $t('admin.kpis.total_points_hint') }}</div>
            </div>
        </div>

        <!-- CP List (Audit View) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Activity Chart -->
            <div class="lg:col-span-2 l2-panel p-8 rounded-3xl border-gray-800 shadow-2xl flex flex-col">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest">{{ $t('admin.activity.title') }}</h3>
                        <p class="text-xs text-gray-500 uppercase font-bold tracking-tighter">{{ $t('admin.activity.subtitle') }}</p>
                    </div>
                </div>
                <div class="flex-1 min-h-[300px]">
                    <Line v-if="chartData" :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <div v-if="$page.props.flash.success && $page.props.flash.success.link" class="p-4 bg-green-500/10 border border-green-500/30 rounded-2xl text-center space-y-2">
                    <div class="text-[11px] text-green-500 font-black uppercase tracking-widest">{{ $t('admin.invite_link.title') }}</div>
                    <input @click="$event.target.select()" :value="$page.props.flash.success.link" readonly class="w-full bg-white border-gray-200 text-[10px] text-gray-900 rounded-lg p-2 text-center dark:bg-black/50 dark:border-gray-700 dark:text-gray-300" />
                </div>

                <!-- Create CP Trigger -->
                <button @click="showCreateModal = true" class="w-full py-4 bg-gradient-to-r from-purple-600 to-blue-600 rounded-2xl font-black uppercase tracking-widest text-xs text-white shadow-lg shadow-purple-950/20 hover:scale-[1.02] active:scale-95 transition-all">
                    {{ $t('admin.actions.create_cp') }}
                </button>

                <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-2xl flex-1 overflow-hidden flex flex-col">
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <div>
                            <h3 class="font-cinzel text-lg text-gray-900 dark:text-white tracking-widest">{{ $t('admin.cp_requests.title') }}</h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">{{ $t('admin.cp_requests.subtitle') }}</p>
                        </div>
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ (cpRequests || []).length }}</div>
                    </div>
                    <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-3">
                        <div v-if="!(cpRequests || []).length" class="text-xs text-gray-500">{{ $t('admin.cp_requests.none') }}</div>
                        <div v-for="req in (cpRequests || [])" :key="req.id" class="p-4 bg-white/70 border border-gray-200 rounded-2xl dark:bg-gray-900/50 dark:border-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[11px] font-black uppercase text-gray-900 dark:text-white truncate">{{ req.cp_name }}</div>
                                    <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                                        <span v-if="req.server">{{ req.server }}</span>
                                        <span v-if="req.server && req.chronicle"> · </span>
                                        <span v-if="req.chronicle" :class="getChronicleColor(req.chronicle)">{{ req.chronicle }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="rejectRequest(req)" class="px-3 py-2 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition dark:bg-gray-800 dark:hover:bg-gray-700">
                                        {{ $t('common.reject') }}
                                    </button>
                                    <button @click="approveRequest(req)" class="px-3 py-2 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:from-purple-500 hover:to-blue-500 transition shadow-lg shadow-purple-950/20">
                                        {{ $t('admin.actions.create_cp_short') }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="req.leader_name || req.contact_email" class="mt-3 text-[10px] text-gray-500">
                                <span v-if="req.leader_name" class="font-bold">{{ req.leader_name }}</span>
                                <span v-if="req.leader_name && req.contact_email"> · </span>
                                <a v-if="req.contact_email" class="underline hover:text-purple-700 dark:hover:text-purple-300 transition" :href="`mailto:${req.contact_email}`">{{ req.contact_email }}</a>
                            </div>
                            <div v-if="req.message" class="mt-3 text-xs text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ req.message }}</div>
                        </div>
                    </div>
                </div>

                <!-- Support Tickets -->
                <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-2xl flex-1 overflow-hidden flex flex-col">
                    <div class="mb-4 flex items-end justify-between gap-4">
                        <div>
                            <h3 class="font-cinzel text-lg text-gray-900 dark:text-white tracking-widest">Soporte</h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Tickets Abiertos</p>
                        </div>
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ (supportTickets || []).length }}</div>
                    </div>
                    <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-3">
                        <div v-if="!(supportTickets || []).length" class="text-xs text-gray-500">Ningún ticket nuevo.</div>
                        <div v-for="tck in (supportTickets || [])" :key="tck.id" class="p-4 bg-white/70 border border-red-500/30 rounded-2xl dark:bg-gray-900/50 dark:border-red-500/20 shadow-[0_0_15px_rgba(239,68,68,0.05)]">
                            <div class="min-w-0">
                                <div class="text-[11px] font-black uppercase text-gray-900 dark:text-white truncate">{{ tck.subject }}</div>
                                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                                    <span v-if="tck.name">{{ tck.name }}</span>
                                    <span v-if="tck.name && tck.email"> · </span>
                                    <a v-if="tck.email" class="underline hover:text-purple-700 dark:hover:text-purple-300 transition" :href="`mailto:${tck.email}`">{{ tck.email }}</a>
                                </div>
                            </div>
                            <div v-if="tck.message" class="mt-3 text-xs text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ tck.message }}</div>
                            <div class="mt-2 text-[9px] text-gray-400 uppercase tracking-widest text-right">{{ new Date(tck.created_at).toLocaleDateString() }}</div>
                        </div>
                    </div>
                </div>

                <!-- CP List -->
                <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-2xl flex-1 overflow-hidden flex flex-col">
                    <div class="mb-4">
                        <h3 class="font-cinzel text-lg text-gray-900 dark:text-white tracking-widest">{{ $t('admin.cps.title') }}</h3>
                    </div>
                    <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-3">
                        <Link 
                            v-for="cp in cps" 
                            :key="cp.id"
                            :href="route('admin.cp.view', cp.id)"
                            class="flex items-center p-3 bg-white/70 border border-gray-200 rounded-xl hover:border-purple-500/50 transition group dark:bg-gray-900/50 dark:border-gray-800"
                        >
                            <div class="flex-1">
                                <div class="text-[11px] font-black uppercase text-gray-900 dark:text-white group-hover:text-purple-700 dark:group-hover:text-purple-300 transition">{{ cp.name }}</div>
                                <div class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">{{ cp.members_count }} {{ $t('common.members') }}</div>
                            </div>
                            <div :class="getChronicleColor(cp.chronicle)" class="text-[8px] font-black border border-current px-2 py-0.5 rounded uppercase">
                                {{ cp.chronicle }}
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create CP Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('admin.create_modal.title') }}</h3>
                    <button @click="showCreateModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                    <div v-if="$page.props.flash.success && $page.props.flash.success.link" class="p-4 bg-green-500/10 border border-green-500/30 rounded-2xl text-center space-y-2">
                         <div class="text-sm text-green-500 font-bold">{{ $t('admin.create_modal.success') }}</div>
                         <input @click="$event.target.select()" :value="$page.props.flash.success.link" readonly class="w-full bg-white border-gray-200 text-[10px] text-gray-900 rounded-lg p-2 text-center dark:bg-black/50 dark:border-gray-700 dark:text-gray-300" />
                    </div>
                    <div v-if="Object.keys(cpForm.errors || {}).length" class="p-4 bg-red-500/10 border border-red-500/30 rounded-2xl">
                        <div class="text-[10px] text-red-500 font-black uppercase tracking-widest">{{ $t('common.errors') }}</div>
                        <div class="mt-2 space-y-1 text-xs text-red-700 dark:text-red-200">
                            <div v-for="(err, key) in cpForm.errors" :key="key">{{ err }}</div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.cp_name') }}</label>
                            <input v-model="cpForm.name" type="text" :placeholder="$t('admin.create_modal.cp_name_placeholder')" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.server_optional') }}</label>
                            <input v-model="cpForm.server" type="text" :placeholder="$t('admin.create_modal.server_placeholder')" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.chronicle') }}</label>
                            <select v-model="cpForm.chronicle" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-400">
                                <option value="C1">{{ $t('chronicles.c1') }}</option>
                                <option value="C2">{{ $t('chronicles.c2') }}</option>
                                <option value="C3">{{ $t('chronicles.c3') }}</option>
                                <option value="C4">{{ $t('chronicles.c4') }}</option>
                                <option value="C5">{{ $t('chronicles.c5') }}</option>
                                <option value="IL">{{ $t('chronicles.il') }}</option>
                                <option value="HB">{{ $t('chronicles.hb') }}</option>
                                <option value="LU4">{{ $t('chronicles.lu4') }}</option>
                                <option value="Classic">{{ $t('chronicles.classic') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-6 flex space-x-4">
                        <button @click="showCreateModal = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ $t('common.close') }}</button>
                        <button @click="submitCp" :disabled="cpForm.processing" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('admin.actions.register_cp') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Inventory Preview -->
        <div class="l2-panel p-8 rounded-3xl border-gray-800 shadow-2xl">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest">{{ $t('admin.items.title') }}</h3>
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-tighter">{{ $t('admin.items.total', { count: stats.total_items }) }}</p>
                </div>
                <Link :href="route('system.items.index')" class="bg-white hover:bg-purple-50 text-gray-900 px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition border border-gray-200 dark:bg-gray-800 dark:hover:bg-purple-800 dark:text-white dark:border-gray-700">
                    {{ $t('admin.actions.manage_items') }}
                </Link>
                <Link :href="route('system.users.index')" class="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-purple-950/20">
                    {{ $t('admin.actions.manage_users') }}
                </Link>
            </div>
            
            <div class="flex flex-wrap gap-4 opacity-30 blur-[2px] pointer-events-none select-none">
                <div v-for="n in 14" :key="n" class="h-10 w-10 bg-gray-100 border border-gray-200 rounded shadow-inner dark:bg-gray-900 dark:border-gray-800"></div>
            </div>
        </div>
    </div>
</template>
