<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePreferencesForm from './Partials/UpdatePreferencesForm.vue';
import { useSwal } from '@/utils/swal';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const cp = computed(() => user.value?.cp || null);
const isAdmin = computed(() => user.value?.role?.name === 'admin');
const isLeader = computed(() => user.value?.role?.name === 'cp_leader' || user.value?.id === cp.value?.leader_id);
const swal = useSwal();

const translations = computed(() => page.props.translations || {});
const t = (key, fallback) => {
    const raw = translations.value?.[key];
    return raw && typeof raw === 'string' ? raw : (fallback || key);
};

const roleLabel = computed(() => {
    const r = user.value?.role?.name;
    if (!r) return '';
    if (r === 'admin') return t('role.admin', 'Admin');
    if (r === 'cp_leader') return t('role.cp_leader', 'CP Leader');
    if (r === 'accountant') return t('role.accountant', 'Accountant');
    return t('role.member', 'Member');
});

// Avatar upload — lives in the hero, not inside the profile information
// form, so the user sees the change immediately and the form below stays
// focused on text fields. Sends a multipart POST with _method=patch to
// hit the same profile.update endpoint that the form sections use.
const avatarForm = useForm({ avatar: null, _method: 'patch' });
const onAvatarChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    avatarForm.avatar = file;
    avatarForm.post(route('profile.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            avatarForm.reset();
            swal.fire({ icon: 'success', title: t('profile.avatar.saved', 'Avatar actualizado'), timer: 1600, showConfirmButton: false });
        },
        onError: (errs) => {
            const msg = errs?.avatar || t('profile.avatar.error', 'No se pudo subir');
            swal.fire({ icon: 'error', title: t('profile.avatar.error', 'No se pudo subir'), text: msg });
        },
    });
};
</script>

<template>
    <Head :title="t('profile.page.title', 'Mi perfil')" />
    <MainLayout>
        <div class="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <!-- HERO: identity + avatar + cross-links to the rest of the Me section -->
            <section class="l2-panel rounded-3xl p-6 sm:p-8 bg-gradient-to-br from-purple-600/10 via-indigo-600/5 to-transparent border border-purple-500/20">
                <div class="flex flex-wrap items-center gap-6">
                    <!-- Avatar with hover overlay for upload -->
                    <div class="relative group shrink-0">
                        <UserAvatar :user="user" size="xl" :square="true" />
                        <label class="absolute inset-0 flex items-center justify-center bg-black/60 rounded-2xl opacity-0 group-hover:opacity-100 cursor-pointer transition" :title="t('profile.avatar.upload_hint', 'Subir foto')">
                            <input type="file" class="hidden" accept="image/png,image/jpeg,image/jpg" @change="onAvatarChange">
                            <svg v-if="!avatarForm.processing" class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg v-else class="w-6 h-6 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" />
                            </svg>
                        </label>
                    </div>

                    <div class="flex-1 min-w-[200px]">
                        <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300 mb-1">{{ t('profile.hero.kicker', 'Tu perfil') }}</div>
                        <h1 class="text-2xl sm:text-3xl font-cinzel font-bold text-gray-900 dark:text-white">{{ user.name }}</h1>
                        <div class="flex items-center gap-2 mt-2 flex-wrap">
                            <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full bg-purple-200/60 dark:bg-purple-900/40 text-purple-900 dark:text-purple-200">{{ roleLabel }}</span>
                            <span v-if="cp" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full bg-amber-200/60 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200">{{ cp.name }}</span>
                            <span v-if="user.email_verified_at" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full bg-emerald-200/60 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 inline-flex items-center gap-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293l-4.5 4.5a1 1 0 01-1.414 0L6 11.414l1.414-1.414L8.5 11.086l3.793-3.793 1.414 1.414z"/></svg>
                                {{ t('profile.hero.verified', 'Email verificado') }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">{{ t('profile.hero.subtitle', 'Gestiona tu cuenta, contraseña, preferencias y avatar desde aquí.') }}</p>
                    </div>
                </div>

                <!-- Quick links to the rest of the Me section -->
                <div v-if="!isAdmin" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-6 border-t border-purple-500/15">
                    <Link :href="route('profile.stats')" class="rounded-xl p-3 bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800 hover:border-purple-500/50 transition flex items-center gap-3 group">
                        <span class="text-2xl">📊</span>
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">{{ t('nav.me.stats', 'Mis estadísticas') }}</div>
                            <div class="text-[10px] text-gray-500 mt-0.5 truncate group-hover:text-purple-600 dark:group-hover:text-purple-400">{{ t('nav.me.stats_hint', 'Tu ranking y actividad') }}</div>
                        </div>
                    </Link>
                    <Link :href="route('characters.index')" class="rounded-xl p-3 bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800 hover:border-purple-500/50 transition flex items-center gap-3 group">
                        <span class="text-2xl">🛡️</span>
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">{{ t('nav.me.characters', 'Mis personajes') }}</div>
                            <div class="text-[10px] text-gray-500 mt-0.5 truncate">{{ t('nav.me.characters_hint', 'Alta y edición') }}</div>
                        </div>
                    </Link>
                    <Link :href="route('warehouse.index')" class="rounded-xl p-3 bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800 hover:border-purple-500/50 transition flex items-center gap-3 group">
                        <span class="text-2xl">💰</span>
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">{{ t('nav.warehouse', 'Mi almacén') }}</div>
                            <div class="text-[10px] text-gray-500 mt-0.5 truncate">{{ t('nav.me.warehouse_hint', 'Adena e items') }}</div>
                        </div>
                    </Link>
                    <Link :href="route('tickets.index')" class="rounded-xl p-3 bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800 hover:border-purple-500/50 transition flex items-center gap-3 group">
                        <span class="text-2xl">🎫</span>
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">{{ t('nav.me.tickets', 'Mis tickets') }}</div>
                            <div class="text-[10px] text-gray-500 mt-0.5 truncate">{{ t('nav.me.tickets_hint', 'Soporte') }}</div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Settings grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Account: name + email -->
                <section class="l2-panel rounded-2xl p-6 bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/15 text-purple-700 dark:text-purple-300 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('profile.info.title', 'Información personal') }}</div>
                    </div>
                    <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
                </section>

                <!-- Password -->
                <section class="l2-panel rounded-2xl p-6 bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/15 text-blue-700 dark:text-blue-300 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('profile.password.title', 'Contraseña') }}</div>
                    </div>
                    <UpdatePasswordForm />
                </section>

                <!-- Preferences -->
                <section class="lg:col-span-2 l2-panel rounded-2xl p-6 bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('profile.preferences.title', 'Preferencias') }}</div>
                    </div>
                    <UpdatePreferencesForm />
                </section>

                <!-- Danger zone -->
                <section class="lg:col-span-2 l2-panel rounded-2xl p-6 bg-red-500/5 border border-red-500/20">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-red-500/20 text-red-700 dark:text-red-300 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-red-700 dark:text-red-300">{{ t('profile.delete.title', 'Zona peligrosa') }}</div>
                    </div>
                    <DeleteUserForm />
                </section>
            </div>
        </div>
    </MainLayout>
</template>
