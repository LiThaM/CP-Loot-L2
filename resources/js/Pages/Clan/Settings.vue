<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';
import emitter from '@/event-bus';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    clan: Object,
    clanCps: Array,
    userMembership: Object,
});

const isOwner = computed(() => props.userMembership?.role === 'owner');
const activeTab = ref('general');

const updateForm = useForm({
    name: props.clan.name,
    description: props.clan.description || '',
    logo: null,
});

const submitUpdate = () => {
    updateForm.patch(route('clan.settings.update'), { forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Clan', message: 'Ajustes actualizados.' }),
    });
};

const handleLogoChange = (e) => {
    updateForm.logo = e.target.files[0] || null;
};

const regenerateCode = () => {
    if (!confirm('¿Regenerar el código de invitación? El código actual dejará de funcionar.')) return;
    router.post(route('clan.settings.invite-code'), {}, { preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Clan', message: 'Código regenerado.' }),
    });
};

const toggleApprover = (cp) => {
    router.patch(route('clan.cps.approver', cp.cp_id), {}, { preserveScroll: true });
};

const updateRole = (cp, role) => {
    router.patch(route('clan.cps.role', cp.cp_id), { role }, { preserveScroll: true });
};

const removeCp = (cp) => {
    if (!confirm(`¿Expulsar "${cp.cp_name}" del clan?`)) return;
    router.delete(route('clan.cps.remove', cp.cp_id), { preserveScroll: true });
};

const dissolveConfirm = ref('');
const dissolveForm = useForm({ confirmation: '' });
const submitDissolve = () => {
    dissolveForm.confirmation = dissolveConfirm.value;
    dissolveForm.delete(route('clan.destroy'), {
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Clan', message: 'Clan disuelto.' }),
    });
};

const copied = ref(false);
const copyCode = async () => {
    await navigator.clipboard.writeText(props.clan.invite_code);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
};

const roleLabel = (role) => ({ owner: 'Owner', admin: 'Admin', member: 'Miembro' }[role] || role);
const roleBadge = (role) => ({
    owner: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
    admin: 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/30',
    member: 'bg-gray-500/15 text-gray-600 dark:text-gray-400 border-gray-500/30',
}[role] || '');
</script>

<template>
    <Head title="Ajustes del Clan" />
    <MainLayout>
        <template #header>
            <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">Ajustes del Clan</h2>
        </template>

        <div class="max-w-3xl mx-auto space-y-4">
            <!-- Tabs -->
            <div class="flex gap-1 border-b border-gray-200 dark:border-gray-800">
                <button v-for="tab in ['general','cps','danger']" :key="tab" @click="activeTab = tab"
                    class="px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 transition -mb-px"
                    :class="activeTab === tab
                        ? (tab === 'danger' ? 'border-red-500 text-red-700 dark:text-red-400' : 'border-amber-500 text-amber-700 dark:text-amber-300')
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">
                    {{ tab === 'general' ? 'General' : tab === 'cps' ? 'CPs del Clan' : 'Zona Peligrosa' }}
                </button>
            </div>

            <!-- General -->
            <div v-if="activeTab === 'general'" class="l2-panel rounded-2xl p-6 space-y-4">
                <div v-if="Object.keys(updateForm.errors).length" class="p-3 bg-red-950/20 border border-red-800/30 rounded-xl text-xs text-red-400">
                    <div v-for="(e, f) in updateForm.errors" :key="f">{{ e }}</div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre del Clan *</label>
                    <input v-model="updateForm.name" type="text" maxlength="60" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Descripción</label>
                    <textarea v-model="updateForm.description" rows="3" maxlength="500" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Logo</label>
                    <input type="file" accept="image/*" @change="handleLogoChange" class="text-sm text-gray-600 dark:text-gray-400">
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex-1">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Código de Invitación</div>
                        <div class="font-mono font-black text-amber-700 dark:text-amber-300 tracking-widest">{{ clan.invite_code }}</div>
                    </div>
                    <button @click="copyCode" class="px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                        {{ copied ? '✓ Copiado' : 'Copiar' }}
                    </button>
                    <button @click="regenerateCode" class="px-3 py-2 bg-red-500/10 text-red-700 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-red-500/20 transition">
                        Regenerar
                    </button>
                </div>
                <button @click="submitUpdate" :disabled="updateForm.processing" class="w-full py-3 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl font-black uppercase tracking-widest text-[10px] hover:from-amber-500 hover:to-orange-500 transition disabled:opacity-40">
                    Guardar cambios
                </button>
            </div>

            <!-- CPs management -->
            <div v-if="activeTab === 'cps'" class="l2-panel rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 dark:border-gray-800">
                        <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                            <th class="px-4 py-3 text-left">CP</th>
                            <th class="px-4 py-3 text-center">Rol</th>
                            <th class="px-4 py-3 text-center">Aprueba asistencia</th>
                            <th v-if="isOwner" class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="cp in clanCps" :key="cp.cp_id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-900 dark:text-white">{{ cp.cp_name }}</div>
                                <div class="text-[10px] text-gray-400">{{ cp.server }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="roleBadge(cp.role)">{{ roleLabel(cp.role) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button @click="toggleApprover(cp)" :disabled="cp.role === 'owner'"
                                    class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-lg transition"
                                    :class="cp.can_approve_attendance ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-500'">
                                    {{ cp.can_approve_attendance ? '✓ Sí' : 'No' }}
                                </button>
                            </td>
                            <td v-if="isOwner" class="px-4 py-3 text-right">
                                <div v-if="cp.role !== 'owner'" class="flex items-center justify-end gap-2">
                                    <select @change="updateRole(cp, $event.target.value)" :value="cp.role" class="bg-white/70 border border-gray-200 text-gray-900 rounded-lg text-xs dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                                        <option value="admin">Admin</option>
                                        <option value="member">Miembro</option>
                                    </select>
                                    <button @click="removeCp(cp)" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 hover:bg-red-500/10 rounded-lg transition">Expulsar</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Danger zone -->
            <div v-if="activeTab === 'danger'" class="l2-panel rounded-2xl p-6 space-y-4 border-red-500/20">
                <h3 class="text-sm font-black uppercase tracking-widest text-red-700 dark:text-red-400">Disolver el Clan</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Esta acción es <strong>irreversible</strong>. Se eliminarán todos los datos del clan: eventos, raid bosses, vault, mercado y DKP de clan.</p>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Escribe <strong class="text-red-600">{{ clan.name }}</strong> para confirmar</label>
                    <input v-model="dissolveConfirm" type="text" class="w-full bg-white/70 border border-red-300 text-gray-900 rounded-xl focus:ring-red-500 dark:bg-black/50 dark:border-red-800/50 dark:text-gray-100" :placeholder="clan.name">
                </div>
                <button @click="submitDissolve" :disabled="dissolveConfirm !== clan.name || dissolveForm.processing"
                    class="w-full py-3 bg-red-700 hover:bg-red-600 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition disabled:opacity-30">
                    Disolver Clan Permanentemente
                </button>
            </div>
        </div>
    </MainLayout>
</template>
