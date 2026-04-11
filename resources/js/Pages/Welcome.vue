<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showSupportModal = ref(false);
const showCpRequestModal = ref(false);
const showDonationModal = ref(false);
const cryptoWallet = ref('0x0D5cf74c1487a0B3867930E884daa44f5019a40E');

const copyWallet = () => {
    navigator.clipboard.writeText(cryptoWallet.value);
    Swal.fire({
        title: '¡Copiada!',
        text: 'La dirección de la cartera se ha copiado al portapapeles.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        background: '#1f2937',
        color: '#f3f4f6',
    });
};

const supportForm = useForm({
    subject: '',
    message: '',
    email: '',
    name: '',
});

const submitSupport = () => {
    supportForm.post(route('support.contact'), {
        preserveScroll: true,
        onSuccess: () => {
            showSupportModal.value = false;
            supportForm.reset();
            Swal.fire({
                title: 'Enviado',
                text: 'Tu mensaje se ha enviado a soporte.',
                icon: 'success',
                background: '#1f2937',
                color: '#f3f4f6',
            });
        },
    });
};

const cpRequestForm = useForm({
    cp_name: '',
    server: '',
    chronicle: 'IL',
    leader_name: '',
    contact_email: '',
    message: '',
});

const submitCpRequest = () => {
    cpRequestForm.post(route('cp.requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCpRequestModal.value = false;
            cpRequestForm.reset();
            Swal.fire({
                title: 'Solicitud Enviada',
                text: 'Tu solicitud de CP ha sido enviada. Pronto nos pondremos en contacto.',
                icon: 'success',
                background: '#1f2937',
                color: '#f3f4f6',
            });
        },
    });
};
</script>

<template>
    <Head :title="$t('welcome.title')" />
    <div class="min-h-screen bg-gray-100 text-gray-900 font-sans selection:bg-purple-200 selection:text-gray-900 dark:bg-gray-950 dark:text-gray-200 dark:selection:bg-purple-900 dark:selection:text-white relative overflow-hidden">
        <div class="absolute inset-0 z-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-purple-500/18 via-gray-100 to-gray-100 dark:from-purple-900/28 dark:via-gray-950 dark:to-gray-950"></div>
        <div class="absolute -top-24 -left-24 w-[420px] h-[420px] rounded-full blur-3xl bg-purple-500/15 dark:bg-purple-500/10"></div>
        <div class="absolute -bottom-24 -right-24 w-[420px] h-[420px] rounded-full blur-3xl bg-blue-500/15 dark:bg-blue-500/10"></div>

        <div class="relative z-10">
            <header class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
                <div class="flex items-center justify-between">
                    <Link href="/" class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-white/60 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800/70 shadow-sm flex items-center justify-center">
                            <ApplicationLogo class="w-7 h-7" />
                        </div>
                        <div class="leading-tight">
                            <div class="text-lg font-black tracking-widest font-cinzel text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-blue-600">
                                {{ $t('welcome.title') }}
                            </div>
                            <div class="text-[10px] uppercase tracking-widest text-gray-600 dark:text-gray-500">
                                Loot · Adena · Warehouse
                            </div>
                        </div>
                    </Link>

                    <div v-if="canLogin" class="flex items-center gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="px-5 py-2 rounded-xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-sm"
                        >
                            {{ $t('welcome.btn.dashboard') }}
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="px-5 py-2 rounded-xl l2-button-dark text-white font-black tracking-widest uppercase shadow-lg border border-purple-900/35 hover:border-purple-400/60 transition text-sm"
                            >
                                {{ $t('welcome.btn.login') }}
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="hidden sm:inline-flex px-5 py-2 rounded-xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-sm"
                            >
                                {{ $t('welcome.btn.register') }}
                            </Link>
                        </template>
                    </div>
                </div>
            </header>

            <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-16">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                    <div class="lg:col-span-7">
                        <h1 class="text-5xl md:text-6xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-purple-500 via-blue-500 to-cyan-300" style="font-family: 'Cinzel', serif;">
                            {{ $t('welcome.title') }}
                        </h1>
                        <p class="mt-5 text-gray-700 dark:text-gray-300 text-base md:text-lg tracking-wide max-w-2xl">
                            {{ $t('welcome.subtitle') }}
                        </p>

                        <div class="mt-6 flex flex-wrap gap-2">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">Auditoría</span>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">Distribución de Adena</span>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">CP Vault</span>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">Items DB</span>
                        </div>

                        <div v-if="canLogin" class="mt-10 flex flex-col sm:flex-row gap-4">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="px-8 py-3 l2-button text-white font-black tracking-widest uppercase rounded-xl shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-base"
                            >
                                {{ $t('welcome.btn.dashboard') }}
                            </Link>
                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="px-8 py-3 l2-button-dark text-white font-black tracking-widest uppercase rounded-xl shadow-lg border border-purple-900/35 hover:border-purple-400/60 transition text-base"
                                >
                                    {{ $t('welcome.btn.login') }}
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="px-8 py-3 l2-button text-white font-black tracking-widest uppercase rounded-xl shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-base"
                                >
                                    {{ $t('welcome.btn.register') }}
                                </Link>
                            </template>
                        </div>

                        <div class="mt-10 flex items-center gap-10">
                            <div>
                                <div class="text-3xl text-purple-700 dark:text-purple-300 font-bold font-serif" style="font-family: 'Cinzel', serif;">
                                    {{ $t('welcome.stats.items') }}
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-500 uppercase tracking-widest mt-1">
                                    {{ $t('welcome.stats.items_label') }}
                                </div>
                            </div>
                            <div>
                                <div class="text-3xl text-blue-700 dark:text-blue-300 font-bold font-serif" style="font-family: 'Cinzel', serif;">
                                    {{ $t('welcome.stats.transparency') }}
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-500 uppercase tracking-widest mt-1">
                                    {{ $t('welcome.stats.transparency_label') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-5">
                        <div class="relative rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/50 shadow-2xl overflow-hidden">
                            <div class="absolute inset-0 bg-gradient-to-br from-purple-600/10 to-blue-600/10"></div>
                            <div class="relative p-7">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-600 to-blue-600 shadow-lg shadow-purple-950/30 flex items-center justify-center text-white">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-black uppercase tracking-widest text-gray-800 dark:text-white">Trazabilidad</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">Cada acción queda reflejada en auditoría y alertas.</div>
                                    </div>
                                </div>

                                <div class="mt-5 grid grid-cols-1 gap-3">
                                    <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-purple-700 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <div class="min-w-0">
                                                <div class="text-xs font-black uppercase tracking-widest text-gray-800 dark:text-white">Adena</div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">Distribución por miembro o envío al fondo CP.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-blue-700 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0l-3 8H7l-3-8m16 0H4"/></svg>
                                            <div class="min-w-0">
                                                <div class="text-xs font-black uppercase tracking-widest text-gray-800 dark:text-white">Warehouse</div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">Control de entradas, salidas y stock.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-emerald-700 dark:text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                            <div class="min-w-0">
                                                <div class="text-xs font-black uppercase tracking-widest text-gray-800 dark:text-white">Dashboard</div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">KPIs, miembros y estado general de la CP.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="mt-14">
                    <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-8">
                        <div class="flex items-start justify-between gap-6 flex-col lg:flex-row">
                            <div class="min-w-0">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">
                                    ¿Qué es AdenaLedger?
                                </div>
                                <h2 class="mt-2 text-2xl md:text-3xl font-black tracking-widest text-gray-900 dark:text-white font-cinzel">
                                    Un ledger para CPs de Lineage II
                                </h2>
                                <p class="mt-4 text-sm md:text-base text-gray-700 dark:text-gray-300 max-w-3xl">
                                    Diseñado para registrar loot, distribuir Adena, controlar warehouse y mantener auditoría real de todo lo que pasa en la party.
                                    Sin hojas de cálculo y sin discusiones: cada acción queda trazada.
                                </p>
                            </div>
                            <div class="w-full lg:w-[360px] grid grid-cols-2 gap-3">
                                <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                    <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">100%</div>
                                    <div class="mt-1 text-lg font-black tracking-widest text-gray-900 dark:text-white">Gratis</div>
                                </div>
                                <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                    <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Soporte</div>
                                    <div class="mt-1 text-xs font-mono break-all text-gray-900 dark:text-white">support@adenaledger.com</div>
                                </div>
                                <div class="col-span-2 rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                    <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Donaciones (cerveza)</div>
                                    <div class="mt-2 text-xs font-mono break-all text-gray-900 dark:text-white">{{ cryptoWallet }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Loot</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">Registro y pruebas</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">Reportes de sesión, adjuntos, revisión y trazabilidad por usuario.</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Adena</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">Distribución</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">Reparto por miembro o al fondo CP con números claros.</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Warehouse</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">Entradas y salidas</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">Control de stock de CP y movimientos con responsable.</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Crafting</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">Recetas y materiales</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">Sistema de crafteo conectado al warehouse y a la DB de items.</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Roles</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">CP Leader / Member</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">Vistas y permisos distintos según rol y estado de miembro.</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Auditoría</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">Transparencia</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">Historial de acciones y alertas para detectar inconsistencias.</div>
                        </div>
                    </div>
                </section>

                <section class="mt-10">
                    <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-8">
                        <div class="flex items-start justify-between gap-6 flex-col lg:flex-row">
                            <div class="min-w-0">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">
                                    Crónicas soportadas
                                </div>
                                <div class="mt-2 text-xl font-black tracking-widest text-gray-900 dark:text-white">
                                    C1 · C2 · C3 · C4 · C5 · IL · HB · Classic · LU4
                                </div>
                                <div class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                    Puedes crear CP por crónica y mantener el ledger separado por entorno.
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C1</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C2</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C3</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C4</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C5</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">IL</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">HB</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">Classic</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">LU4</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-10">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-8 overflow-hidden relative">
                            <div class="absolute inset-0 bg-gradient-to-br from-purple-600/10 to-blue-600/10"></div>
                            <div class="relative">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Inspiración Lineage II</div>
                                <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">Adena & Ledger</div>
                                <div class="mt-3 text-sm text-gray-700 dark:text-gray-300">Arte vectorial original para ambientar el proyecto.</div>
                                <div class="mt-6 flex items-center justify-center">
                                    <svg class="w-full max-w-sm" viewBox="0 0 520 220" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <defs>
                                            <linearGradient id="w-gold" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0" stop-color="#fbbf24" />
                                                <stop offset="1" stop-color="#f59e0b" />
                                            </linearGradient>
                                            <linearGradient id="w-sky" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0" stop-color="rgba(124,58,237,0.45)" />
                                                <stop offset="1" stop-color="rgba(37,99,235,0.35)" />
                                            </linearGradient>
                                        </defs>
                                        <rect x="0" y="0" width="520" height="220" rx="26" fill="url(#w-sky)" />
                                        <circle cx="170" cy="110" r="64" fill="url(#w-gold)" opacity="0.95" />
                                        <circle cx="170" cy="110" r="50" fill="rgba(0,0,0,0.10)" />
                                        <path d="M170 64l26 74h-16l-5-16h-10l-5 16h-16l26-74z" fill="rgba(17,24,39,0.90)" />
                                        <rect x="260" y="54" width="210" height="132" rx="16" fill="rgba(255,255,255,0.22)" />
                                        <rect x="278" y="74" width="174" height="10" rx="5" fill="rgba(255,255,255,0.65)" />
                                        <rect x="278" y="98" width="154" height="10" rx="5" fill="rgba(255,255,255,0.55)" />
                                        <rect x="278" y="122" width="164" height="10" rx="5" fill="rgba(255,255,255,0.45)" />
                                        <rect x="278" y="146" width="132" height="10" rx="5" fill="rgba(255,255,255,0.35)" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-8 overflow-hidden relative">
                            <div class="absolute inset-0 bg-gradient-to-br from-purple-600/10 to-blue-600/10"></div>
                            <div class="relative">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Inspiración Lineage II</div>
                                <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">Castillos y asedios</div>
                                <div class="mt-3 text-sm text-gray-700 dark:text-gray-300">Eventos, raids y control de recursos en entorno CP.</div>
                                <div class="mt-6 flex items-center justify-center">
                                    <svg class="w-full max-w-sm" viewBox="0 0 520 220" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <defs>
                                            <linearGradient id="w-sky2" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0" stop-color="rgba(37,99,235,0.40)" />
                                                <stop offset="1" stop-color="rgba(34,197,94,0.20)" />
                                            </linearGradient>
                                        </defs>
                                        <rect x="0" y="0" width="520" height="220" rx="26" fill="url(#w-sky2)" />
                                        <path d="M92 170h336v22H92z" fill="rgba(17,24,39,0.55)" />
                                        <path d="M130 170V92h56v22h22V92h56v22h22V92h56v78H130z" fill="rgba(17,24,39,0.70)" />
                                        <path d="M130 92l12-16 12 16v0h-24zm78 0l12-16 12 16v0h-24zm156 0l12-16 12 16v0h-24z" fill="rgba(17,24,39,0.80)" />
                                        <path d="M240 170v-44c0-18 14-32 32-32s32 14 32 32v44h-64z" fill="rgba(255,255,255,0.18)" />
                                        <path d="M264 170v-32c0-5 4-9 9-9h14c5 0 9 4 9 9v32h-32z" fill="rgba(255,255,255,0.28)" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <footer class="mt-16 pt-10 border-t border-gray-200/80 dark:border-gray-800/70 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4">
                        <div class="text-xs text-gray-600 dark:text-gray-500 uppercase tracking-widest">
                            © {{ new Date().getFullYear() }} AdenaLedger
                        </div>
                        <div class="text-[11px] text-gray-600 dark:text-gray-500 tracking-wide">
                            100% gratuito. Donaciones para cerveza: <span class="font-mono">{{ cryptoWallet }}</span> · Soporte: <a class="underline hover:text-purple-700 dark:hover:text-purple-300 transition" href="mailto:support@adenaledger.com">support@adenaledger.com</a>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-white/70 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800/70 text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 transition"
                            @click="showCpRequestModal = true"
                        >
                            Solicitar alta CP
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-white/70 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800/70 text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 transition"
                            @click="showSupportModal = true"
                        >
                            Soporte
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-purple-900/35 hover:border-purple-400/60 transition text-[10px]"
                            @click="showDonationModal = true"
                        >
                            Donaciones (Cripto)
                        </button>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <div v-if="showSupportModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center">
                <h3 class="font-cinzel text-lg text-white tracking-widest">Soporte</h3>
                <button type="button" class="text-white/70 hover:text-white transition" @click="showSupportModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div v-if="Object.keys(supportForm.errors).length" class="p-3 rounded-xl border border-red-500/25 bg-red-950/10 text-red-700 dark:text-red-200 text-sm">
                    <div v-for="(err, key) in supportForm.errors" :key="key">{{ err }}</div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Asunto</label>
                    <input v-model="supportForm.subject" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Mensaje</label>
                    <textarea v-model="supportForm.message" rows="5" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Tu email (opcional)</label>
                        <input v-model="supportForm.email" type="email" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Tu nombre (opcional)</label>
                        <input v-model="supportForm.name" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" class="flex-1 py-3 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition" @click="showSupportModal = false">
                        Cerrar
                    </button>
                    <button type="button" class="flex-[2] py-3 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:from-purple-500 hover:to-blue-500 transition disabled:opacity-40 disabled:grayscale" :disabled="supportForm.processing" @click="submitSupport">
                        Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal CP Request -->
    <div v-if="showCpRequestModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center">
                <h3 class="font-cinzel text-lg text-white tracking-widest">Solicitar CP</h3>
                <button type="button" class="text-white/70 hover:text-white transition" @click="showCpRequestModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div v-if="Object.keys(cpRequestForm.errors).length" class="p-3 rounded-xl border border-red-500/25 bg-red-950/10 text-red-700 dark:text-red-200 text-sm">
                    <div v-for="(err, key) in cpRequestForm.errors" :key="key">{{ err }}</div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre de la CP</label>
                    <input v-model="cpRequestForm.cp_name" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Servidor</label>
                        <input v-model="cpRequestForm.server" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Crónica</label>
                        <select v-model="cpRequestForm.chronicle" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                            <option value="C1">C1</option>
                            <option value="C2">C2</option>
                            <option value="C3">C3</option>
                            <option value="C4">C4</option>
                            <option value="C5">C5</option>
                            <option value="IL">IL</option>
                            <option value="HB">HB</option>
                            <option value="Classic">Classic</option>
                            <option value="LU4">LU4</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Líder</label>
                        <input v-model="cpRequestForm.leader_name" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Email Contacto</label>
                        <input v-model="cpRequestForm.contact_email" type="email" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Mensaje</label>
                    <textarea v-model="cpRequestForm.message" rows="4" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" class="flex-1 py-3 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition" @click="showCpRequestModal = false">
                        Cerrar
                    </button>
                    <button type="button" class="flex-[2] py-3 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:from-purple-500 hover:to-blue-500 transition disabled:opacity-40 disabled:grayscale" :disabled="cpRequestForm.processing" @click="submitCpRequest">
                        Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Donaciones (Cripto) -->
    <div v-if="showDonationModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center">
                <h3 class="font-cinzel text-lg text-white tracking-widest">Apoya el Proyecto</h3>
                <button type="button" class="text-white/70 hover:text-white transition" @click="showDonationModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6 space-y-4 text-center">
                <p class="text-gray-700 dark:text-gray-300 text-sm">
                    AdenaLedger es 100% gratuito. Si te ayuda y quieres apoyar el proyecto, se aceptan donaciones para cerveza en la siguiente cartera:
                </p>
                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col items-center gap-3">
                    <span class="text-sm font-mono text-purple-600 dark:text-purple-400 font-bold break-all">{{ cryptoWallet }}</span>
                    <button 
                        @click="copyWallet"
                        class="px-5 py-2 rounded-xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-xs"
                    >
                        Copiar Cartera
                    </button>
                </div>
                <div class="pt-2">
                    <button type="button" class="w-full py-3 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition" @click="showDonationModal = false">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Inter:wght@400;500;600;700&display=swap');

.font-cinzel {
    font-family: 'Cinzel', serif;
}

.l2-button {
    background: linear-gradient(to right, #6d28d9, #2563eb);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 4px 6px -1px rgba(0, 0, 0, 0.5);
}
.l2-button:hover {
    background: linear-gradient(to right, #7c3aed, #3b82f6);
}

.l2-button-dark {
    background: #111827;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 4px 6px -1px rgba(0, 0, 0, 0.5);
}
.l2-button-dark:hover {
    background: #1f2937;
}
</style>
