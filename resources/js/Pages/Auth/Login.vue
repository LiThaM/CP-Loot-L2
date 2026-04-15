<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="$t('auth.login.title')" />

    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 text-gray-900 dark:bg-gray-950 dark:text-gray-200" style="font-family: 'Inter', sans-serif;">
        <div class="mb-8 text-center">
            <h1 class="text-4xl tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-purple-500 to-blue-400" style="font-family: 'Cinzel', serif;">{{ $page.props.app?.name }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm uppercase tracking-wider">{{ $t('auth.login.subtitle') }}</p>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-8 l2-panel rounded-xl relative overflow-hidden">
            <!-- Decorative accent -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-900 via-blue-600 to-purple-900"></div>
            
            <div v-if="status" class="mb-4 text-sm font-medium text-green-400">
                {{ status }}
            </div>

            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="email" :value="$t('form.email')" class="text-gray-700 dark:text-gray-300" />

                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full bg-white/70 border border-gray-200 text-gray-900 focus:border-purple-500 focus:ring-purple-500 shadow-inner dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                    />

                    <InputError class="mt-2 text-red-500" :message="form.errors.email" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password" :value="$t('form.password')" class="text-gray-700 dark:text-gray-300" />

                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full bg-white/70 border border-gray-200 text-gray-900 focus:border-purple-500 focus:ring-purple-500 shadow-inner dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                    />

                    <InputError class="mt-2 text-red-500" :message="form.errors.password" />
                </div>

                <div class="mt-4 block">
                    <label class="flex items-center">
                        <Checkbox name="remember" v-model:checked="form.remember" class="border-gray-300 bg-white text-purple-600 focus:ring-purple-600 dark:border-gray-700 dark:bg-gray-900" />
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ $t('auth.login.remember') }}</span>
                    </label>
                </div>

                <div class="mt-8 flex items-center justify-between">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="rounded-md text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-white transition"
                    >
                        {{ $t('auth.login.forgot') }}
                    </Link>
                    <span v-else></span>

                    <button
                        type="submit"
                        class="px-6 py-2 bg-gradient-to-r from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white font-bold rounded shadow-lg transition-transform transform active:scale-95"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ $t('auth.login.submit') }}
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

html:not(.dark) .l2-panel {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(243, 244, 246, 0.95) 100%);
    border: 1px solid rgba(229, 231, 235, 0.9);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.12);
}
</style>
