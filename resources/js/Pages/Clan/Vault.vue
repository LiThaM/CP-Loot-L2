<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import emitter from '@/event-bus';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    items: Array,
    auctions: Array,
    clanCps: Array,
    userMembership: Object,
    myDkp: Number,
});

const isAdmin = computed(() => ['owner', 'admin'].includes(props.userMembership?.role));
const activeTab = ref('vault');

const showAddModal = ref(false);
const addForm = useForm({ item_name: '', quantity: 1, item_image_url: '' });

const showAuctionModal = ref(false);
const auctionTarget = ref(null);
const auctionForm = useForm({ min_bid: 0, ends_at: '' });

const showAssignModal = ref(false);
const assignTarget = ref(null);
const assignCpId = ref('');
const submitAssign = () => {
    if (!assignCpId.value) return;
    router.post(route('clan.vault.assign', assignTarget.value.id), { cp_id: assignCpId.value }, {
        preserveScroll: true,
        onSuccess: () => { showAssignModal.value = false; },
    });
};

const bidForms = {};
const getBidForm = (auctionId) => {
    if (!bidForms[auctionId]) bidForms[auctionId] = useForm({ bid_amount: 0 });
    return bidForms[auctionId];
};

const submitAdd = () => {
    addForm.post(route('clan.vault.store'), {
        preserveScroll: true,
        onSuccess: () => { showAddModal.value = false; addForm.reset(); addForm.quantity = 1; },
    });
};

const openAuctionModal = (item) => {
    auctionTarget.value = item;
    auctionForm.reset();
    showAuctionModal.value = true;
};

const submitAuction = () => {
    auctionForm.post(route('clan.vault.auction', auctionTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => { showAuctionModal.value = false; activeTab.value = 'auctions'; },
    });
};

const assignToCp = (item) => {
    assignTarget.value = item;
    assignCpId.value = '';
    showAssignModal.value = true;
};

const raffleItem = (item) => {
    if (!confirm('¿Sortear entre todas las CPs del clan?')) return;
    router.post(route('clan.vault.raffle', item.id), {}, { preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Sorteo', message: 'Ítem asignado por sorteo.' }),
    });
};

const removeItem = (item) => {
    if (!confirm(`¿Eliminar "${item.item_name}" del vault?`)) return;
    router.delete(route('clan.vault.destroy', item.id), { preserveScroll: true });
};

const placeBid = (auction) => {
    const form = getBidForm(auction.id);
    form.post(route('clan.vault.auctions.bid', auction.id), { preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Puja', message: 'Puja registrada.' }),
    });
};

const closeAuction = (auction) => {
    if (!confirm('¿Cerrar la subasta y asignar al mejor postor?')) return;
    router.post(route('clan.vault.auctions.close', auction.id), {}, { preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Subasta', message: 'Subasta cerrada.' }),
    });
};

const cancelAuction = (auction) => {
    if (!confirm('¿Cancelar la subasta?')) return;
    router.delete(route('clan.vault.auctions.cancel', auction.id), { preserveScroll: true });
};

const statusBadge = (s) => ({
    in_vault: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
    auctioning: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
    assigned: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    raffled: 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/30',
    removed: 'bg-gray-500/15 text-gray-400 border-gray-500/30',
}[s] || '');
const statusLabel = (s) => ({ in_vault: 'En Vault', auctioning: 'Subasta', assigned: 'Asignado', raffled: 'Sorteado', removed: 'Eliminado' }[s] || s);

// Countdown
const now = ref(Date.now());
let tick = null;
onMounted(() => { tick = setInterval(() => { now.value = Date.now(); }, 1000); });
onUnmounted(() => clearInterval(tick));

const countdown = (endsAt) => {
    if (!endsAt) return '—';
    const diff = new Date(endsAt).getTime() - now.value;
    if (diff <= 0) return 'Finalizado';
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    return `${h}h ${m.toString().padStart(2,'0')}m ${s.toString().padStart(2,'0')}s`;
};
</script>

<template>
    <Head title="Vault del Clan" />
    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">Vault del Clan</h2>
                <div class="flex gap-2">
                    <span class="text-xs font-black text-amber-700 dark:text-amber-300 self-center">Mis DKP: {{ myDkp }}</span>
                    <button v-if="isAdmin" @click="showAddModal = true" class="px-4 py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:from-amber-500 hover:to-orange-500 transition">
                        + Añadir Ítem
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <div class="flex gap-1 border-b border-gray-200 dark:border-gray-800">
                <button v-for="tab in ['vault','auctions']" :key="tab" @click="activeTab = tab"
                    class="px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 transition -mb-px"
                    :class="activeTab === tab ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">
                    {{ tab === 'vault' ? 'Vault' : 'Subastas' }}
                    <span v-if="tab === 'auctions' && auctions?.length" class="ml-1 bg-amber-500/15 text-amber-700 dark:text-amber-300 px-1.5 py-0.5 rounded-full text-[10px]">{{ auctions.length }}</span>
                </button>
            </div>

            <!-- Vault items -->
            <div v-if="activeTab === 'vault'" class="space-y-3">
                <div v-if="!items?.length" class="text-center py-12 text-gray-400 text-sm">El vault está vacío.</div>
                <div v-for="item in items" :key="item.id" class="l2-panel rounded-2xl p-4 flex items-center gap-4">
                    <img v-if="item.item_image_url" :src="item.item_image_url" class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-700 object-cover shrink-0">
                    <div v-else class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-xl shrink-0">📦</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-gray-900 dark:text-white">{{ item.item_name }}</span>
                            <span v-if="item.quantity > 1" class="text-xs text-gray-500">x{{ item.quantity }}</span>
                            <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="statusBadge(item.status)">{{ statusLabel(item.status) }}</span>
                        </div>
                        <div v-if="item.assigned_to_cp_name" class="text-xs text-gray-500 mt-0.5">→ {{ item.assigned_to_cp_name }}</div>
                        <div v-if="item.deposited_by_name" class="text-[10px] text-gray-400">Depositado por {{ item.deposited_by_name }}</div>
                    </div>
                    <div v-if="isAdmin && item.status === 'in_vault'" class="flex gap-2 shrink-0 flex-wrap justify-end">
                        <button @click="openAuctionModal(item)" class="px-2 py-1 bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-amber-500/25 transition">Subastar</button>
                        <button @click="assignToCp(item)" class="px-2 py-1 bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-blue-500/25 transition">Asignar</button>
                        <button @click="raffleItem(item)" class="px-2 py-1 bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-purple-500/25 transition">Sortear</button>
                        <button @click="removeItem(item)" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-red-500 transition">Del</button>
                    </div>
                </div>
            </div>

            <!-- Auctions -->
            <div v-if="activeTab === 'auctions'" class="space-y-3">
                <div v-if="!auctions?.length" class="text-center py-12 text-gray-400 text-sm">No hay subastas activas.</div>
                <div v-for="auc in auctions" :key="auc.id" class="l2-panel rounded-2xl p-4">
                    <div class="flex items-start gap-4">
                        <img v-if="auc.item_image_url" :src="auc.item_image_url" class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-700 object-cover shrink-0">
                        <div v-else class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-xl shrink-0">📦</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-gray-900 dark:text-white">{{ auc.item_name }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Mínimo: <span class="font-black text-amber-700 dark:text-amber-300">{{ auc.min_bid }} DKP</span>
                                <span v-if="auc.highest_bid" class="ml-2">· Mayor puja: <span class="font-black text-amber-700 dark:text-amber-300">{{ auc.highest_bid }} DKP</span> ({{ auc.highest_bidder }})</span>
                            </div>
                            <div class="text-xs font-black mt-1" :class="new Date(auc.ends_at).getTime() > now ? 'text-amber-700 dark:text-amber-300' : 'text-red-600 dark:text-red-400'">
                                ⏱ {{ countdown(auc.ends_at) }}
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 shrink-0 min-w-[120px]">
                            <div class="flex gap-1">
                                <input :value="getBidForm(auc.id).bid_amount" @input="getBidForm(auc.id).bid_amount = +$event.target.value"
                                    type="number" :min="auc.min_bid" class="w-20 bg-white/70 border border-gray-200 text-gray-900 rounded-lg text-sm text-center dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                                <button @click="placeBid(auc)" :disabled="getBidForm(auc.id).processing || getBidForm(auc.id).bid_amount < auc.min_bid"
                                    class="px-3 py-1 bg-amber-600 text-white rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-amber-500 transition disabled:opacity-40">
                                    Pujar
                                </button>
                            </div>
                            <div v-if="isAdmin" class="flex gap-1">
                                <button @click="closeAuction(auc)" class="flex-1 px-2 py-1 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500/25 transition">Cerrar</button>
                                <button @click="cancelAuction(auc)" class="flex-1 px-2 py-1 bg-red-500/15 text-red-700 dark:text-red-400 border border-red-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-red-500/25 transition">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add item modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
                <div class="bg-gradient-to-r from-amber-900 to-orange-900 p-4 flex justify-between items-center border-b border-amber-500/20">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">Depositar Ítem</h3>
                    <button @click="showAddModal = false" class="text-white/50 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre del ítem *</label>
                        <input v-model="addForm.item_name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Ej: Sword of Valhalla">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Cantidad</label>
                            <input v-model.number="addForm.quantity" type="number" min="1" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">URL Imagen (opcional)</label>
                            <input v-model="addForm.item_image_url" type="url" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                    </div>
                    <div v-if="Object.keys(addForm.errors).length" class="p-3 bg-red-950/20 border border-red-800/30 rounded-xl text-xs text-red-400">
                        <div v-for="(e, f) in addForm.errors" :key="f">{{ e }}</div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showAddModal = false" class="flex-1 py-2 bg-gray-800 text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-700 transition">Cancelar</button>
                        <button @click="submitAdd" :disabled="addForm.processing" class="flex-[2] py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-40 transition">Depositar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign to CP modal -->
        <div v-if="showAssignModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
                <div class="bg-gradient-to-r from-blue-900 to-blue-800 p-4 flex justify-between items-center border-b border-blue-700">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">Asignar: {{ assignTarget?.item_name }}</h3>
                    <button @click="showAssignModal = false" class="text-white/50 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">CP destinataria</label>
                        <select v-model="assignCpId" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-blue-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                            <option value="">Selecciona una CP</option>
                            <option v-for="cp in clanCps" :key="cp.cp_id" :value="cp.cp_id">{{ cp.cp_name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showAssignModal = false" class="flex-1 py-2 bg-gray-800 text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-700 transition">Cancelar</button>
                        <button @click="submitAssign" :disabled="!assignCpId" class="flex-[2] py-2 bg-blue-700 hover:bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-40 transition">Asignar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create auction modal -->
        <div v-if="showAuctionModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
                <div class="bg-gradient-to-r from-amber-900 to-orange-900 p-4 flex justify-between items-center border-b border-amber-500/20">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">Subastar: {{ auctionTarget?.item_name }}</h3>
                    <button @click="showAuctionModal = false" class="text-white/50 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Puja mínima (DKP)</label>
                        <input v-model.number="auctionForm.min_bid" type="number" min="0" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Cierre de la subasta</label>
                        <input v-model="auctionForm.ends_at" type="datetime-local" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div class="flex gap-3">
                        <button @click="showAuctionModal = false" class="flex-1 py-2 bg-gray-800 text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-700 transition">Cancelar</button>
                        <button @click="submitAuction" :disabled="auctionForm.processing" class="flex-[2] py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-40 transition">Crear Subasta</button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
