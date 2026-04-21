<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    inviteCode: { type: String, default: '' },
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
    <GuestLayout>
        <Head :title="$t('auth.register.title')" />

        <div class="px-7 py-8">
            <!-- Top accent bar -->
            <div class="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-purple-900 via-indigo-500 to-purple-900 rounded-t-2xl"></div>

            <!-- Title -->
            <div class="mb-7 text-center">
                <h1 class="font-cinzel text-2xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-indigo-300 to-purple-400">
                    {{ $t('auth.register.title') }}
                </h1>
                <p class="mt-1.5 text-xs uppercase tracking-widest text-gray-500">
                    {{ $t('auth.register.subtitle') }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Character name -->
                <div>
                    <InputLabel for="name" :value="$t('auth.register.character_name')" class="text-xs font-black uppercase tracking-widest text-purple-400 mb-1.5" />
                    <TextInput
                        id="name"
                        type="text"
                        class="block w-full auth-input"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                    />
                    <InputError class="mt-1.5 text-red-400 text-xs" :message="form.errors.name" />
                </div>

                <!-- Email -->
                <div>
                    <InputLabel for="email" :value="$t('form.email')" class="text-xs font-black uppercase tracking-widest text-purple-400 mb-1.5" />
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full auth-input"
                        v-model="form.email"
                        required
                        autocomplete="username"
                    />
                    <InputError class="mt-1.5 text-red-400 text-xs" :message="form.errors.email" />
                </div>

                <!-- Invite code -->
                <div>
                    <InputLabel for="invite_code" :value="$t('auth.register.invite_code')" class="text-xs font-black uppercase tracking-widest text-yellow-500 mb-1.5" />
                    <TextInput
                        id="invite_code"
                        type="text"
                        class="block w-full auth-input invite-input"
                        v-model="form.invite_code"
                        required
                        :readonly="!!props.inviteCode"
                    />
                    <InputError class="mt-1.5 text-red-400 text-xs" :message="form.errors.invite_code" />
                </div>

                <!-- Password -->
                <div>
                    <InputLabel for="password" :value="$t('form.password')" class="text-xs font-black uppercase tracking-widest text-purple-400 mb-1.5" />
                    <TextInput
                        id="password"
                        type="password"
                        class="block w-full auth-input"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-1.5 text-red-400 text-xs" :message="form.errors.password" />
                </div>

                <!-- Confirm password -->
                <div>
                    <InputLabel for="password_confirmation" :value="$t('form.password_confirm')" class="text-xs font-black uppercase tracking-widest text-purple-400 mb-1.5" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="block w-full auth-input"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-1.5 text-red-400 text-xs" :message="form.errors.password_confirmation" />
                </div>

                <div class="pt-1">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 rounded-xl btn-gaming text-white font-black tracking-widest uppercase text-sm shadow-[0_0_20px_rgba(168,85,247,0.3)] disabled:opacity-50 disabled:grayscale transition-all"
                    >
                        {{ $t('auth.register.submit') }}
                    </button>
                </div>

                <p class="text-center text-xs text-gray-500">
                    {{ $t('auth.register.have_account') }}
                    <Link :href="route('login')" class="text-purple-400 hover:text-purple-300 font-bold transition">
                        {{ $t('auth.login.title') }}
                    </Link>
                </p>
            </form>
        </div>
    </GuestLayout>
</template>

<style scoped>
.font-cinzel { font-family: 'Cinzel', serif; }

.auth-input {
    background: rgba(0, 0, 0, 0.3) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #e5e7eb !important;
    border-radius: 0.5rem;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
    width: 100%;
}
.auth-input:focus {
    border-color: rgba(168, 85, 247, 0.6) !important;
    box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.15) !important;
}
.invite-input {
    border-color: rgba(234, 179, 8, 0.4) !important;
    text-align: center;
    letter-spacing: 0.2em;
    font-weight: 700;
}
.invite-input:focus {
    border-color: rgba(234, 179, 8, 0.7) !important;
    box-shadow: 0 0 0 2px rgba(234, 179, 8, 0.1) !important;
}

html:not(.dark) .auth-input {
    background: rgba(255, 255, 255, 0.7) !important;
    border-color: rgba(209, 213, 219, 0.9) !important;
    color: #111827 !important;
}
html:not(.dark) .auth-input:focus {
    border-color: rgba(147, 51, 234, 0.5) !important;
    box-shadow: 0 0 0 2px rgba(147, 51, 234, 0.1) !important;
}
html:not(.dark) .invite-input {
    border-color: rgba(202, 138, 4, 0.5) !important;
}

.btn-gaming {
    position: relative;
    overflow: hidden;
    background: linear-gradient(to right, #6d28d9, #4f46e5);
    border: 1px solid rgba(168, 85, 247, 0.3);
}
.btn-gaming:hover:not(:disabled) {
    background: linear-gradient(to right, #7c3aed, #4338ca);
    transform: translateY(-1px);
    box-shadow: 0 0 25px rgba(168, 85, 247, 0.4);
}
.btn-gaming::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 50%; height: 100%;
    background: linear-gradient(to right, transparent, rgba(255,255,255,0.15), transparent);
    transform: skewX(-45deg);
    transition: left 0.6s;
}
.btn-gaming:hover::before { left: 150%; }
</style>
