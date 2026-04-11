<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const props = defineProps({
    cpName: {
        type: String,
        default: null,
    },
    leader: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="Cuenta Excluida" />

    <GuestLayout>
        <div class="text-center">
            <div class="mx-auto w-24 h-24 rounded-2xl border border-red-200 bg-red-50 text-red-700 flex items-center justify-center dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
                <svg class="w-12 h-12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>

            <h1 class="mt-6 text-2xl font-black font-cinzel text-gray-900 dark:text-white">
                Cuenta Excluida
            </h1>

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {{ user?.name }} no tiene acceso al contenido de la CP.
            </p>

            <div v-if="cpName || leader" class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-left dark:border-gray-700 dark:bg-gray-900/40">
                <div v-if="cpName" class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    CP
                </div>
                <div v-if="cpName" class="text-sm font-bold text-gray-900 dark:text-white mt-1">
                    {{ cpName }}
                </div>

                <div v-if="leader" class="mt-4">
                    <div class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                        Leader
                    </div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white mt-1">
                        {{ leader?.name || '—' }}
                    </div>
                    <a
                        v-if="leader?.email"
                        class="text-sm font-bold text-purple-700 hover:text-purple-800 dark:text-purple-300 dark:hover:text-purple-200 transition"
                        :href="`mailto:${leader.email}`"
                    >
                        {{ leader.email }}
                    </a>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-center gap-3">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="px-5 py-2.5 rounded-xl bg-gray-900 hover:bg-black text-white text-sm font-black uppercase tracking-widest transition-all"
                >
                    Cerrar sesión
                </Link>
            </div>
        </div>
    </GuestLayout>
</template>

