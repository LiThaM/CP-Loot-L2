<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ status: { type: String } });

const form = useForm({ email: '' });
const submit = () => { form.post(route('password.email')); };
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.forgot.title')" />

        <div class="px-7 py-8">
            <div class="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-purple-900 via-indigo-500 to-purple-900 rounded-t-2xl"></div>

            <div class="mb-6 text-center">
                <h1 class="font-cinzel text-2xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-indigo-300 to-purple-400">
                    {{ $t('auth.forgot.title') }}
                </h1>
                <p class="mt-2 text-sm text-gray-400 leading-relaxed max-w-xs mx-auto">
                    {{ $t('auth.forgot.description') }}
                </p>
            </div>

            <div v-if="status" class="mb-4 text-sm font-medium text-green-400 text-center">{{ status }}</div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel for="email" :value="$t('form.email')" class="text-xs font-black uppercase tracking-widest text-purple-400 mb-1.5" />
                    <TextInput id="email" type="email" class="block w-full auth-input" v-model="form.email" required autofocus autocomplete="username" />
                    <InputError class="mt-1.5 text-red-400 text-xs" :message="form.errors.email" />
                </div>

                <button type="submit" :disabled="form.processing"
                        class="w-full py-3 rounded-xl btn-gaming text-white font-black tracking-widest uppercase text-sm disabled:opacity-50 disabled:grayscale transition-all">
                    {{ $t('auth.forgot.submit') }}
                </button>

                <p class="text-center text-xs text-gray-500">
                    <Link :href="route('login')" class="text-purple-400 hover:text-purple-300 font-bold transition">
                        ← {{ $t('auth.login.title') }}
                    </Link>
                </p>
            </form>
        </div>
    </GuestLayout>
</template>

<style scoped>
.font-cinzel { font-family: 'Cinzel', serif; }
.auth-input {
    background: rgba(0,0,0,0.3) !important; border: 1px solid rgba(255,255,255,0.1) !important;
    color: #e5e7eb !important; border-radius: 0.5rem; padding: 0.625rem 0.875rem;
    font-size: 0.875rem; transition: border-color .2s, box-shadow .2s; outline: none; width: 100%;
}
.auth-input:focus { border-color: rgba(168,85,247,.6) !important; box-shadow: 0 0 0 2px rgba(168,85,247,.15) !important; }
html:not(.dark) .auth-input { background: rgba(255,255,255,.7) !important; border-color: rgba(209,213,219,.9) !important; color: #111827 !important; }
.btn-gaming { position: relative; overflow: hidden; background: linear-gradient(to right, #6d28d9, #4f46e5); border: 1px solid rgba(168,85,247,.3); }
.btn-gaming:hover:not(:disabled) { background: linear-gradient(to right, #7c3aed, #4338ca); transform: translateY(-1px); }
</style>
