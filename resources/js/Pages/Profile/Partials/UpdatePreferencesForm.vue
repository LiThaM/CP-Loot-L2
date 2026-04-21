<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const page = usePage();
const user = page.props.auth.user;

const form = useForm({
    theme_preference:    user.theme_preference    ?? 'system',
    language_preference: user.language_preference ?? 'system',
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ $t('profile.preferences.title') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $t('profile.preferences.description') }}
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <!-- Theme -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">
                    {{ $t('profile.preferences.theme') }}
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <button
                        v-for="opt in [
                            { value: 'system', label: $t('profile.preferences.theme_system'), icon: '🖥️' },
                            { value: 'light',  label: $t('profile.preferences.theme_light'),  icon: '☀️' },
                            { value: 'dark',   label: $t('profile.preferences.theme_dark'),   icon: '🌙' },
                        ]"
                        :key="opt.value"
                        type="button"
                        @click="form.theme_preference = opt.value"
                        :class="[
                            'flex flex-col items-center gap-2 py-4 px-3 rounded-2xl border-2 transition-all font-bold text-xs uppercase tracking-widest',
                            form.theme_preference === opt.value
                                ? 'border-purple-600 bg-purple-600/10 text-purple-700 dark:text-purple-300 shadow-lg shadow-purple-500/20'
                                : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-500 hover:border-gray-300 dark:hover:border-gray-600'
                        ]"
                    >
                        <span class="text-2xl">{{ opt.icon }}</span>
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Language -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">
                    {{ $t('profile.preferences.language') }}
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <button
                        v-for="opt in [
                            { value: 'system', label: $t('profile.preferences.lang_system'), icon: '🌐' },
                            { value: 'es',     label: 'Español',                             icon: '🇪🇸' },
                            { value: 'en',     label: 'English',                             icon: '🇬🇧' },
                        ]"
                        :key="opt.value"
                        type="button"
                        @click="form.language_preference = opt.value"
                        :class="[
                            'flex flex-col items-center gap-2 py-4 px-3 rounded-2xl border-2 transition-all font-bold text-xs uppercase tracking-widest',
                            form.language_preference === opt.value
                                ? 'border-purple-600 bg-purple-600/10 text-purple-700 dark:text-purple-300 shadow-lg shadow-purple-500/20'
                                : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-500 hover:border-gray-300 dark:hover:border-gray-600'
                        ]"
                    >
                        <span class="text-2xl">{{ opt.icon }}</span>
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-8 py-3 bg-gradient-to-tr from-purple-700 to-indigo-600 hover:from-purple-600 hover:to-indigo-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg disabled:opacity-40"
                >
                    {{ $t('common.save') }}
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400 font-bold">
                        {{ $t('common.saved') }}
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
