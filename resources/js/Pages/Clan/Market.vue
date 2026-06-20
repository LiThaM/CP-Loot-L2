<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';
import emitter from '@/event-bus';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;
const currentUser = computed(() => page.props.auth?.user);

const props = defineProps({
    listings: Array,
    userMembership: Object,
});

const isAdmin = computed(() => ['owner', 'admin'].includes(props.userMembership?.role));
const filterType = ref('');
const filterListing = ref('');
const showCreateModal = ref(false);

const createForm = useForm({
    listing_type: 'wts',
    item_type: 'item',
    item_name: '',
    quantity: 1,
    price: '',
    is_negotiable: false,
    contact_info: '',
    notes: '',
});

const submitCreate = () => {
    createForm.post(route('clan.market.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreateModal.value = false; createForm.reset(); createForm.listing_type = 'wts'; createForm.item_type = 'item'; createForm.quantity = 1; createForm.is_negotiable = false; },
    });
};

const markSold = (listing) => {
    if (!confirm('¿Marcar como vendido?')) return;
    router.patch(route('clan.market.update', listing.id), { status: 'sold' }, { preserveScroll: true });
};

const cancelListing = (listing) => {
    if (!confirm('¿Cancelar el anuncio?')) return;
    router.patch(route('clan.market.update', listing.id), { status: 'cancelled' }, { preserveScroll: true });
};

const deleteListing = (listing) => {
    if (!confirm('¿Eliminar el anuncio?')) return;
    router.delete(route('clan.market.destroy', listing.id), { preserveScroll: true });
};

const filtered = computed(() => {
    let list = props.listings || [];
    if (filterType.value) list = list.filter(l => l.listing_type === filterType.value);
    if (filterListing.value) list = list.filter(l => l.item_type === filterListing.value);
    return list;
});

const listingBadge = (type) => ({
    wts: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    wtb: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
}[type] || '');

const statusBadge = (s) => ({
    active: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    sold: 'bg-gray-500/15 text-gray-400 border-gray-500/30',
    cancelled: 'bg-red-500/15 text-red-700 dark:text-red-400 border-red-500/30',
}[s] || '');

const formatPrice = (price) => {
    if (!price) return '';
    const n = Number(price);
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'kk';
    if (n >= 1_000) return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n);
};

const fmtDate = (iso) => iso ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'short' }).format(new Date(iso)) : '';

const isOwner = (listing) => listing.user_id === currentUser.value?.id;
</script>

<template>
    <Head title="Mercado del Clan" />
    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">Mercado Interno</h2>
                <button @click="showCreateModal = true" class="px-4 py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:from-amber-500 hover:to-orange-500 transition">
                    + Publicar Anuncio
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <select v-model="filterType" class="bg-white/70 border border-gray-200 text-gray-900 rounded-xl text-sm focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    <option value="">Todos los tipos</option>
                    <option value="wts">WTS (Vendo)</option>
                    <option value="wtb">WTB (Compro)</option>
                </select>
                <select v-model="filterListing" class="bg-white/70 border border-gray-200 text-gray-900 rounded-xl text-sm focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    <option value="">Todo</option>
                    <option value="item">Ítems</option>
                    <option value="account">Cuentas</option>
                </select>
                <span class="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-500 self-center">{{ filtered.length }} anuncios</span>
            </div>

            <!-- Listings grid -->
            <div v-if="!filtered.length" class="text-center py-12 text-gray-400 text-sm">Sin anuncios activos.</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="l in filtered" :key="l.id" class="l2-panel rounded-2xl p-4 flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="listingBadge(l.listing_type)">{{ l.listing_type.toUpperCase() }}</span>
                            <span v-if="l.item_type === 'account'" class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/30">Cuenta</span>
                            <span v-if="l.status !== 'active'" class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="statusBadge(l.status)">{{ l.status === 'sold' ? 'Vendido' : 'Cancelado' }}</span>
                        </div>
                        <span class="text-[10px] text-gray-400 shrink-0">{{ fmtDate(l.created_at) }}</span>
                    </div>

                    <div class="font-bold text-gray-900 dark:text-white">{{ l.item_name }}</div>
                    <div v-if="l.quantity > 1" class="text-xs text-gray-500">x{{ l.quantity }}</div>

                    <div class="flex items-center justify-between">
                        <div class="font-black text-amber-700 dark:text-amber-300">
                            <template v-if="l.is_negotiable || !l.price">A negociar</template>
                            <template v-else>{{ formatPrice(l.price) }} Adena</template>
                        </div>
                        <div class="text-xs text-gray-500">{{ l.user_name }}</div>
                    </div>

                    <div v-if="l.contact_info" class="text-xs text-gray-500 border-t border-gray-100 dark:border-gray-800 pt-2">
                        <span class="font-black uppercase tracking-widest text-[10px]">Contacto:</span> {{ l.contact_info }}
                    </div>
                    <div v-if="l.notes" class="text-xs text-gray-500 italic">{{ l.notes }}</div>

                    <div v-if="l.status === 'active' && (isOwner(l) || isAdmin)" class="flex gap-2 pt-1 border-t border-gray-100 dark:border-gray-800">
                        <button v-if="l.listing_type === 'wts' && isOwner(l)" @click="markSold(l)" class="flex-1 py-1.5 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500/25 transition">Vendido</button>
                        <button @click="cancelListing(l)" class="flex-1 py-1.5 bg-red-500/15 text-red-700 dark:text-red-400 border border-red-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-red-500/25 transition">Cancelar</button>
                        <button v-if="isAdmin" @click="deleteListing(l)" class="py-1.5 px-2 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-red-500 transition">Del</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl max-h-[90vh] flex flex-col">
                <div class="bg-gradient-to-r from-amber-900 to-orange-900 p-4 flex justify-between items-center border-b border-amber-500/20">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">Publicar Anuncio</h3>
                    <button @click="showCreateModal = false" class="text-white/50 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4 overflow-y-auto custom-scrollbar">
                    <div v-if="Object.keys(createForm.errors).length" class="p-3 bg-red-950/20 border border-red-800/30 rounded-xl text-xs text-red-400">
                        <div v-for="(e, f) in createForm.errors" :key="f">{{ e }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Tipo</label>
                            <select v-model="createForm.listing_type" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                                <option value="wts">WTS (Vendo)</option>
                                <option value="wtb">WTB (Compro)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Categoría</label>
                            <select v-model="createForm.item_type" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                                <option value="item">Ítem</option>
                                <option value="account">Cuenta</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre *</label>
                        <input v-model="createForm.item_name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Ej: Sword of Damascus +4">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Cantidad</label>
                            <input v-model.number="createForm.quantity" type="number" min="1" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Precio (Adena)</label>
                            <input v-model.number="createForm.price" type="number" min="0" :disabled="createForm.is_negotiable" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100 disabled:opacity-50">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="createForm.is_negotiable" type="checkbox" class="rounded border-gray-300">
                        <span class="text-xs font-black uppercase tracking-widest text-gray-500">Precio negociable</span>
                    </label>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Contacto (nick / Discord)</label>
                        <input v-model="createForm.contact_info" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Ej: NickInGame o Discord#1234">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Notas</label>
                        <textarea v-model="createForm.notes" rows="2" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showCreateModal = false" class="flex-1 py-2 bg-gray-800 text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-700 transition">Cancelar</button>
                        <button @click="submitCreate" :disabled="createForm.processing" class="flex-[2] py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-40 transition">Publicar</button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
