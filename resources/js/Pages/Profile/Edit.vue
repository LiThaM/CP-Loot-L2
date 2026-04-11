<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Perfil" />
    <MainLayout>
        <template #header>
            <div>
                <h2 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">Perfil</h2>
                <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">Preferencias de cuenta</p>
            </div>
        </template>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="l2-panel p-6 rounded-2xl border-gray-200 dark:border-gray-800">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">Información de Perfil</div>
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </div>
            <div class="l2-panel p-6 rounded-2xl border-gray-200 dark:border-gray-800">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">Cambiar Contraseña</div>
                <UpdatePasswordForm />
            </div>
            <div class="md:col-span-2 l2-panel p-6 rounded-2xl border-gray-200 dark:border-gray-800">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">Eliminar Cuenta</div>
                <DeleteUserForm />
            </div>
        </div>
    </MainLayout>
</template>
