<script setup>
defineProps({
    cp: { type: Object, required: true },
    form: { type: Object, required: true },
    logoPreview: { type: String, default: null },
    inviteCode: { type: String, default: null },
    canRegenerateInvite: { type: Boolean, default: false },
});

const emit = defineEmits(['logo-change', 'submit', 'copy-invite', 'regenerate-invite', 'recompute']);
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="l2-panel p-6 rounded-3xl border-gray-800 bg-white/60 dark:bg-black/40 shadow-xl">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ $t('cp.settings.logo_section') }}</div>
                    <div class="flex flex-col items-center">
                        <div class="w-32 h-32 rounded-3xl border-2 border-dashed border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-black/60 flex items-center justify-center overflow-hidden mb-4 relative group">
                            <img v-if="logoPreview || cp.logo_url" :src="logoPreview || cp.logo_url" class="w-full h-full object-cover">
                            <div v-else class="text-4xl text-gray-300">⚔️</div>
                            <label class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center cursor-pointer transition-opacity">
                                <input type="file" class="hidden" accept="image/*" @change="emit('logo-change', $event)">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </label>
                        </div>
                        <p class="text-[10px] text-gray-500 font-bold text-center uppercase tracking-widest leading-relaxed">{{ $t('cp.settings.logo_tip') }}</p>
                    </div>
                </div>

                <div class="l2-panel p-6 rounded-3xl border-gray-800 bg-white/60 dark:bg-black/40 shadow-xl text-center">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('party.invite.title') }}</div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-4">{{ $t('party.invite.description') }}</p>
                    <div v-if="inviteCode" class="mb-4 py-3 px-4 rounded-2xl bg-gray-100 border border-gray-200 dark:bg-black/60 dark:border-gray-700">
                        <div class="font-mono font-black text-purple-700 dark:text-purple-300 tracking-widest text-sm truncate">{{ inviteCode }}</div>
                    </div>
                    <button @click="emit('copy-invite')" class="w-full py-4 rounded-2xl bg-gray-900 border border-gray-700 hover:bg-black text-white text-[10px] font-black uppercase tracking-widest transition flex items-center justify-center gap-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                        {{ $t('party.invite.copy_btn') }}
                    </button>
                    <button v-if="canRegenerateInvite" @click="emit('regenerate-invite')" class="mt-3 w-full py-3 rounded-2xl bg-red-950/10 border border-red-500/30 hover:bg-red-600 hover:text-white text-red-600 dark:text-red-400 dark:hover:text-white text-[10px] font-black uppercase tracking-widest transition">
                        {{ $t('party.invite.regenerate_btn') }}
                    </button>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="l2-panel p-8 rounded-[2rem] border-gray-800 bg-white/60 dark:bg-black/40 shadow-2xl">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-8 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                        {{ $t('cp.settings.general_title') }}
                    </div>

                    <div class="space-y-8">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">{{ $t('form.cp_name') }}</label>
                            <input v-model="form.name" type="text" class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-2xl focus:ring-purple-600 h-14 px-6 font-bold shadow-inner dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                            <div v-if="form.errors.name" class="mt-2 text-[10px] text-red-500 font-bold uppercase tracking-widest">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">{{ $t('form.server') }}</label>
                            <input v-model="form.server" type="text" class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-2xl focus:ring-purple-600 h-14 px-6 font-bold shadow-inner dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                            <div v-if="form.errors.server" class="mt-2 text-[10px] text-red-500 font-bold uppercase tracking-widest">{{ form.errors.server }}</div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">{{ $t('form.chronicle') }}</label>
                            <div class="w-full bg-gray-100/80 border border-gray-200 text-gray-400 dark:text-gray-600 rounded-2xl px-6 h-14 flex items-center font-bold dark:bg-gray-800/20 dark:border-gray-700">
                                {{ cp.chronicle }}
                            </div>
                            <p class="mt-3 text-[9px] text-gray-500 font-bold uppercase tracking-widest leading-loose">{{ $t('cp.settings.chronicle_locked_tip') }}</p>
                        </div>

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.image_proof_required"
                                       class="mt-1 w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-600">
                                <div>
                                    <div class="text-xs font-black uppercase tracking-widest text-gray-800 dark:text-gray-200">{{ $t('cp.settings.image_proof_required') }}</div>
                                    <div class="text-[10px] text-gray-500 mt-1 leading-relaxed">{{ $t('cp.settings.image_proof_required_hint') }}</div>
                                </div>
                            </label>
                        </div>

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.staff_can_manage_members"
                                       class="mt-1 w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-600">
                                <div>
                                    <div class="text-xs font-black uppercase tracking-widest text-gray-800 dark:text-gray-200">{{ $t('cp.settings.staff_manage_members') }}</div>
                                    <div class="text-[10px] text-gray-500 mt-1 leading-relaxed">{{ $t('cp.settings.staff_manage_members_hint') }}</div>
                                </div>
                            </label>
                        </div>

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" v-model="form.tracker_enabled"
                                       class="mt-1 w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-600">
                                <div class="flex-1">
                                    <div class="text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">{{ $t('cp.settings.tracker_enabled') }}</div>
                                    <div class="text-[10px] text-gray-500 mt-1 leading-relaxed">{{ $t('cp.settings.tracker_enabled_hint') }}</div>
                                </div>
                            </label>

                            <div v-if="form.tracker_enabled" class="mt-4 ml-8">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('cp.settings.tracker_divisor') }}</label>
                                <input v-model.number="form.tracker_divisor" type="number" min="50" max="2000" step="50"
                                       class="w-40 bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-600 h-11 px-4 font-bold shadow-inner dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                                <p class="mt-2 text-[10px] text-gray-500 leading-relaxed">{{ $t('cp.settings.tracker_divisor_hint') }}</p>
                                <div v-if="form.errors.tracker_divisor" class="mt-2 text-[10px] text-red-500 font-bold uppercase tracking-widest">{{ form.errors.tracker_divisor }}</div>

                                <!-- Valuation basis: market price vs NPC sell price -->
                                <label class="flex items-start gap-3 cursor-pointer mt-5">
                                    <input type="checkbox" v-model="form.tracker_value_by_market"
                                           class="mt-1 w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-600">
                                    <div class="flex-1">
                                        <div class="text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">
                                            {{ $page.props.app?.locale === 'es' ? 'Valuar por precio de mercado' : 'Value by market price' }}
                                        </div>
                                        <div class="text-[10px] text-gray-500 mt-1 leading-relaxed">
                                            {{ $page.props.app?.locale === 'es'
                                                ? 'Marcado: el loot se valora por precio de mercado (precio de venta NPC como respaldo). Desmarcado: por precio de venta NPC (mercado como respaldo).'
                                                : 'Checked: loot is valued by market price (NPC sell as fallback). Unchecked: by NPC sell price (market as fallback).' }}
                                        </div>
                                    </div>
                                </label>

                                <!-- Round up small drops (< 1000 adena) -->
                                <label class="flex items-start gap-3 cursor-pointer mt-4">
                                    <input type="checkbox" v-model="form.tracker_round_up_below_1000"
                                           class="mt-1 w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-600">
                                    <div class="flex-1">
                                        <div class="text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">
                                            {{ $page.props.app?.locale === 'es' ? 'Redondear arriba drops < 1000 adena' : 'Round up drops under 1000 adena' }}
                                        </div>
                                        <div class="text-[10px] text-gray-500 mt-1 leading-relaxed">
                                            {{ $page.props.app?.locale === 'es'
                                                ? 'Si una entrada de loot vale menos de 1000 adena, se cuenta como 1000 al calcular puntos (los drops baratos no valen fracciones).'
                                                : 'A loot entry worth under 1000 adena counts as 1000 when computing points (cheap drops are not worth fractions).' }}
                                        </div>
                                    </div>
                                </label>

                                <!-- Whole points (round up, no decimals) -->
                                <label class="flex items-start gap-3 cursor-pointer mt-4">
                                    <input type="checkbox" v-model="form.tracker_round_points_up"
                                           class="mt-1 w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-600">
                                    <div class="flex-1">
                                        <div class="text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">
                                            {{ $page.props.app?.locale === 'es' ? 'Puntos enteros (sin decimales)' : 'Whole points (no decimals)' }}
                                        </div>
                                        <div class="text-[10px] text-gray-500 mt-1 leading-relaxed">
                                            {{ $page.props.app?.locale === 'es'
                                                ? 'Los puntos se redondean siempre al alza a un número entero.'
                                                : 'Points are always rounded up to a whole number.' }}
                                        </div>
                                    </div>
                                </label>

                                <!-- Manual recompute trigger -->
                                <div class="mt-5 pt-4 border-t border-gray-200/70 dark:border-gray-800">
                                    <button type="button" @click="emit('recompute')" :disabled="form.processing"
                                            class="px-5 py-3 bg-gradient-to-tr from-amber-700 to-orange-600 hover:from-amber-600 hover:to-orange-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg shadow-amber-950/30 disabled:opacity-40">
                                        {{ $page.props.app?.locale === 'es' ? 'Recalcular tracker' : 'Recompute tracker' }}
                                    </button>
                                    <p class="mt-2 text-[10px] text-gray-500 leading-relaxed">
                                        {{ $page.props.app?.locale === 'es'
                                            ? 'Recalcula los puntos de loot con el divisor, la base de precio y los precios actuales. Las contribuciones manuales se conservan. Guarda los cambios antes de recalcular.'
                                            : 'Recomputes loot points using the current divisor, price basis and prices. Manual contributions are kept. Save changes before recomputing.' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                            <button
                                @click="emit('submit')"
                                :disabled="form.processing"
                                class="px-10 py-4 bg-gradient-to-tr from-purple-700 to-indigo-600 hover:from-purple-600 hover:to-indigo-500 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition shadow-xl shadow-purple-950/50 disabled:opacity-30 active:scale-95 translate-y-0 hover:-translate-y-1"
                            >
                                {{ $t('common.save_changes') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
