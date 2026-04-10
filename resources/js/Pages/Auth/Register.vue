<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    inviteCode: {
        type: String,
        default: ''
    }
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    invite_code: props.inviteCode,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Registro CP" />

    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-950 text-gray-200" style="font-family: 'Inter', sans-serif;">
        <div class="mb-8 text-center">
            <h1 class="text-4xl tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-orange-500" style="font-family: 'Cinzel', serif;">L2 LOOT MANAGER</h1>
            <p class="text-gray-400 mt-2 text-sm uppercase tracking-wider">Únete a tu Const Party</p>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-8 l2-panel rounded-xl">
            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Nombre del Personaje" class="text-gray-300" />

                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full bg-gray-900 border-gray-700 text-gray-200 focus:border-red-500 focus:ring-red-500 shadow-inner"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                    />

                    <InputError class="mt-2 text-red-500" :message="form.errors.name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="email" value="Email" class="text-gray-300" />

                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full bg-gray-900 border-gray-700 text-gray-200 focus:border-red-500 focus:ring-red-500 shadow-inner"
                        v-model="form.email"
                        required
                        autocomplete="username"
                    />

                    <InputError class="mt-2 text-red-500" :message="form.errors.email" />
                </div>

                <div class="mt-4">
                    <InputLabel for="invite_code" value="Código de Invitación Clave" class="text-red-400 font-bold" />

                    <TextInput
                        id="invite_code"
                        type="text"
                        class="mt-1 block w-full bg-gray-900 border-red-900 text-gray-200 focus:border-red-500 focus:ring-red-500 shadow-inner tracking-widest text-center"
                        v-model="form.invite_code"
                        required
                        :readonly="!!props.inviteCode"
                    />

                    <InputError class="mt-2 text-red-500" :message="form.errors.invite_code" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password" value="Contraseña" class="text-gray-300" />

                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full bg-gray-900 border-gray-700 text-gray-200 focus:border-red-500 focus:ring-red-500 shadow-inner"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                    />

                    <InputError class="mt-2 text-red-500" :message="form.errors.password" />
                </div>

                <div class="mt-4">
                    <InputLabel
                        for="password_confirmation"
                        value="Confirmar Contraseña"
                        class="text-gray-300"
                    />

                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full bg-gray-900 border-gray-700 text-gray-200 focus:border-red-500 focus:ring-red-500 shadow-inner"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                    />

                    <InputError
                        class="mt-2 text-red-500"
                        :message="form.errors.password_confirmation"
                    />
                </div>

                <div class="mt-8 flex items-center justify-between">
                    <Link
                        :href="route('login')"
                        class="rounded-md text-sm text-gray-400 underline hover:text-white transition"
                    >
                        ¿Ya tienes cuenta? Ingresar
                    </Link>

                    <button
                        type="submit"
                        class="px-6 py-2 bg-gradient-to-r from-red-800 to-orange-700 hover:from-red-700 hover:to-orange-600 text-white font-bold rounded shadow-lg transition-transform transform active:scale-95"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                        :disabled="form.processing"
                    >
                        Unirse a CP
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Inter:wght@400;500;600;700&display=swap');

.l2-panel {
    background: linear-gradient(180deg, rgba(31, 41, 55, 0.9) 0%, rgba(17, 24, 39, 0.95) 100%);
    border: 1px solid rgba(75, 85, 99, 0.4);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 10px 15px -3px rgba(0,0,0,0.5);
}
</style>
