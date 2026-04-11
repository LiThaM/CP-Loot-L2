<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showSupportModal = ref(false);
const showCpRequestModal = ref(false);
const showDonationModal = ref(false);

const donationWallet = '0x0D5cf74c1487a0B3867930E884daa44f5019a40E';

const copyDonationWallet = async () => {
    await navigator.clipboard.writeText(donationWallet);
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

const cpRequestForm = useForm({
    cp_name: '',
    server: '',
    chronicle: 'IL',
    leader_name: '',
    contact_email: '',
    message: '',
});

const submitSupport = () => {
    supportForm.post(route('support.contact'), {
        preserveScroll: true,
        onSuccess: () => {
            showSupportModal.value = false;
            supportForm.reset();
        },
    });
};

const submitCpRequest = () => {
    cpRequestForm.post(route('cp.requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCpRequestModal.value = false;
            cpRequestForm.reset();
        },
    });
};
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0 dark:bg-gray-900"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg dark:bg-gray-800"
        >
            <slot />
        </div>

        <div v-if="flashSuccess || flashError" class="mt-4 w-full sm:max-w-md">
            <div v-if="flashSuccess" class="p-3 rounded-2xl border border-emerald-500/25 bg-emerald-950/10 text-emerald-700 dark:text-emerald-200 text-sm">
                {{ typeof flashSuccess === 'string' ? flashSuccess : (flashSuccess?.message || 'Hecho.') }}
            </div>
            <div v-else class="p-3 rounded-2xl border border-red-500/25 bg-red-950/10 text-red-700 dark:text-red-200 text-sm">
                {{ typeof flashError === 'string' ? flashError : (flashError?.message || 'Ha ocurrido un error.') }}
            </div>
        </div>

        <footer class="mt-10 w-full px-6 pb-8 text-center">
            <div class="text-[11px] text-gray-600 dark:text-gray-400 tracking-wide">
                AdenaLedger es 100% gratuito. Donaciones para cerveza: {{ donationWallet }} · Soporte: <a class="underline hover:text-purple-700 dark:hover:text-purple-300 transition" href="mailto:support@adenaledger.com">support@adenaledger.com</a>
            </div>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl bg-white/70 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-800 transition"
                    @click="showCpRequestModal = true"
                >
                    Solicitar alta CP
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl bg-white/70 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-800 transition"
                    @click="showSupportModal = true"
                >
                    Soporte
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl bg-gray-900 text-white font-black tracking-widest uppercase shadow-lg border border-purple-900/35 hover:border-purple-400/60 transition text-[10px]"
                    @click="showDonationModal = true"
                >
                    Donaciones
                </button>
            </div>
            <div class="mt-4 text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-500">
                © {{ new Date().getFullYear() }} AdenaLedger
            </div>
        </footer>
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

    <div v-if="showCpRequestModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center">
                <h3 class="font-cinzel text-lg text-white tracking-widest">Solicitud de Alta CP</h3>
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
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Servidor (opcional)</label>
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
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Líder (opcional)</label>
                        <input v-model="cpRequestForm.leader_name" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Email de contacto (opcional)</label>
                        <input v-model="cpRequestForm.contact_email" type="email" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Mensaje (opcional)</label>
                    <textarea v-model="cpRequestForm.message" rows="4" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" class="flex-1 py-3 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition" @click="showCpRequestModal = false">
                        Cerrar
                    </button>
                    <button type="button" class="flex-[2] py-3 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:from-purple-500 hover:to-blue-500 transition disabled:opacity-40 disabled:grayscale" :disabled="cpRequestForm.processing" @click="submitCpRequest">
                        Enviar solicitud
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showDonationModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center">
                <h3 class="font-cinzel text-lg text-white tracking-widest">Donaciones</h3>
                <button type="button" class="text-white/70 hover:text-white transition" @click="showDonationModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-4 text-center">
                <p class="text-gray-700 dark:text-gray-300 text-sm">
                    AdenaLedger es 100% gratuito. Si te ayuda, se aceptan donaciones para cerveza.
                </p>
                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col items-center gap-3">
                    <span class="text-sm font-mono text-purple-600 dark:text-purple-400 font-bold break-all">{{ donationWallet }}</span>
                    <button
                        type="button"
                        class="px-5 py-2 rounded-xl bg-gray-900 text-white font-black tracking-widest uppercase shadow-lg border border-purple-900/35 hover:border-purple-400/60 transition text-[10px]"
                        @click="copyDonationWallet"
                    >
                        Copiar cartera
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
