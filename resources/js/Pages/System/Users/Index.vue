<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    users: Array,
    roles: Array,
    cps: Array,
    isAdmin: Boolean,
    isLeader: Boolean,
});

const search = ref('');
const filteredUsers = computed(() => {
    return props.users.filter(user => 
        user.name.toLowerCase().includes(search.value.toLowerCase()) ||
        user.email.toLowerCase().includes(search.value.toLowerCase())
    );
});

// Adena Transaction Form
const showAdenaModal = ref(false);
const selectedUser = ref(null);
const adenaForm = useForm({
    user_id: '',
    amount: '',
    description: '',
});

const openAdenaModal = (user) => {
    selectedUser.value = user;
    adenaForm.user_id = user.id;
    adenaForm.amount = '';
    adenaForm.description = '';
    showAdenaModal.value = true;
};

const submitAdena = () => {
    adenaForm.post(route('adena.transaction.store'), {
        onSuccess: () => showAdenaModal.value = false,
    });
};

// Admin Assignment Form
const showEditModal = ref(false);
const editForm = useForm({
    role_id: '',
    cp_id: '',
});

const openEditModal = (user) => {
    selectedUser.value = user;
    editForm.role_id = user.role_id;
    editForm.cp_id = user.cp_id;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.patch(route('system.users.update', selectedUser.value.id), {
        onSuccess: () => showEditModal.value = false,
    });
};

const deleteUser = (user) => {
    if (confirm(`¿Estás seguro de eliminar a ${user.name}? Se perderán todos sus registros.`)) {
        router.delete(route('system.users.destroy', user.id));
    }
};

const formatAdena = (val) => {
    return new Intl.NumberFormat().format(val || 0);
};

</script>

<template>
    <Head title="Gestión de Usuarios" />

    <MainLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="font-cinzel text-3xl text-white tracking-widest uppercase">Gestión de Miembros</h2>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">
                        {{ isAdmin ? 'Auditoría Global de Usuarios y Balances' : 'Gestión de Saldo y Auditoría de CP' }}
                    </p>
                </div>
                
                <div class="relative w-full md:w-64">
                    <input 
                        v-model="search"
                        type="text" 
                        placeholder="Buscar por nombre o email..."
                        class="w-full bg-gray-900 border-gray-800 text-gray-300 rounded-xl focus:ring-red-600 text-sm pl-10"
                    >
                    <svg class="w-4 h-4 text-gray-600 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Summary Stats (Common) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="l2-panel p-6 rounded-3xl border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Usuarios Totales</div>
                    <div class="text-3xl font-cinzel text-white">{{ filteredUsers.length }}</div>
                </div>
                <div class="l2-panel p-6 rounded-3xl border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Adena Acumulada (Total)</div>
                    <div class="text-3xl font-cinzel text-red-500">{{ formatAdena(users.reduce((acc, u) => acc + (u.total_adena || 0), 0)) }}</div>
                </div>
                <div class="l2-panel p-6 rounded-3xl border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Puntos Totales</div>
                    <div class="text-3xl font-cinzel text-orange-500">{{ formatAdena(users.reduce((acc, u) => acc + (u.total_points || 0), 0)) }}</div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="l2-panel rounded-[2rem] border-gray-800 overflow-hidden shadow-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-900/50 border-b border-gray-800">
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500">Miembro</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500">Role / CP</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-right">Saldo Adena</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-right">Saldo Puntos</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-red-950/5 transition group">
                            <td class="p-5">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-xs font-black mr-3 border border-gray-700">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-black text-white group-hover:text-red-500 transition">{{ user.name }}</div>
                                        <div class="text-[10px] text-gray-500 font-bold">{{ user.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-5">
                                <div class="flex flex-col gap-1">
                                    <span class="text-[9px] font-black uppercase tracking-tighter px-2 py-0.5 bg-gray-800 rounded-md border border-gray-700 w-max text-gray-400">{{ user.role.name }}</span>
                                    <span class="text-[10px] font-bold text-gray-600">{{ user.cp?.name || 'SIn CP' }}</span>
                                </div>
                            </td>
                            <td class="p-5 text-right">
                                <span class="text-sm font-black text-red-500 font-cinzel">{{ formatAdena(user.total_adena) }}</span>
                            </td>
                            <td class="p-5 text-right">
                                <span class="text-sm font-black text-orange-500 font-cinzel">{{ formatAdena(user.total_points) }}</span>
                            </td>
                            <td class="p-5 text-center">
                                <div class="flex justify-center gap-2" v-if="isAdmin || isLeader">
                                    <button 
                                        @click="openAdenaModal(user)"
                                        class="p-2 bg-gray-800 hover:bg-red-600 rounded-lg text-white transition shadow-lg shadow-black/20"
                                        title="Gestionar Saldo Adena"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>

                                    <template v-if="isAdmin">
                                        <button 
                                            @click="openEditModal(user)"
                                            class="p-2 bg-gray-800 hover:bg-blue-600 rounded-lg text-white transition shadow-lg shadow-black/20"
                                            title="Editar Rol/CP"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button 
                                            @click="deleteUser(user)"
                                            class="p-2 bg-gray-800 hover:bg-black rounded-lg text-white transition shadow-lg shadow-black/20"
                                            title="Eliminar Usuario"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v2m8 4H4"></path></svg>
                                        </button>
                                    </template>
                                </div>
                                <div class="text-[9px] text-gray-700 font-bold uppercase" v-else>Sólo Lectura</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="filteredUsers.length === 0" class="p-10 text-center text-gray-600 italic font-cinzel text-xl opacity-30">
                    No se encontraron usuarios...
                </div>
            </div>
        </div>

        <!-- Adena Modal -->
        <div v-if="showAdenaModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-3xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-red-900 to-orange-800 p-5 flex justify-between items-center border-b border-red-700/50">
                    <div>
                        <h3 class="font-cinzel text-xl text-white tracking-widest">Gestión de Saldo</h3>
                        <p class="text-[10px] text-white/50 font-black uppercase tracking-tighter">{{ selectedUser.name }}</p>
                    </div>
                    <button @click="showAdenaModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-8 space-y-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Monto de Adena</label>
                            <input 
                                v-model="adenaForm.amount" 
                                type="number" 
                                placeholder="Ej: -1000000 para pagar, 500000 para abonar" 
                                class="w-full bg-black/50 border-gray-700 text-gray-100 rounded-xl focus:ring-red-600 font-bold text-center text-xl h-14"
                            >
                            <p class="text-[9px] text-gray-600 font-bold uppercase mt-2 text-center">Usa valores negativos para descontar del fondo (pagos)</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Motivo / Descripción</label>
                            <input 
                                v-model="adenaForm.description" 
                                type="text" 
                                placeholder="Ej: Pago de drop Draconic Bow" 
                                class="w-full bg-black/50 border-gray-700 text-gray-100 rounded-xl focus:ring-red-600 h-10"
                            >
                        </div>
                    </div>

                    <div class="pt-4 flex space-x-3">
                        <button @click="showAdenaModal = false" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition">Cancelar</button>
                        <button @click="submitAdena" :disabled="adenaForm.processing" class="flex-[2] py-3 bg-red-600 hover:bg-red-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-red-950/20">Registrar Transacción</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal (Admin Only) -->
        <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-3xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                 <div class="bg-gradient-to-r from-blue-900 to-indigo-800 p-5 flex justify-between items-center">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">Reasignar Usuario</h3>
                    <button @click="showEditModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-8 space-y-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Rol del Sistema</label>
                            <select v-model="editForm.role_id" class="w-full bg-black/50 border-gray-700 text-gray-300 rounded-xl">
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Asignar a CP</label>
                            <select v-model="editForm.cp_id" class="w-full bg-black/50 border-gray-700 text-gray-300 rounded-xl">
                                <option :value="null">Ninguna / Solo</option>
                                <option v-for="cp in cps" :key="cp.id" :value="cp.id">{{ cp.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="pt-4 flex space-x-3">
                        <button @click="showEditModal = false" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition">Cancelar</button>
                        <button @click="submitEdit" :disabled="editForm.processing" class="flex-[2] py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition">Actualizar Asignación</button>
                    </div>
                </div>
            </div>
        </div>

    </MainLayout>
</template>
