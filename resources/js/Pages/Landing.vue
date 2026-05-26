<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { renderInlineMarkdown } from '@/utils/inlineMarkdown';

const props = defineProps({
    latest: Object,
    releases: { type: Array, default: () => [] },
    webChangelog: { type: Array, default: () => [] },
});

const page = usePage();
const appLocale = computed(() => page.props.app?.locale || 'es');
const localeTag = computed(() => (appLocale.value === 'es' ? 'es-ES' : 'en-US'));

const setLocale = (locale) => { router.post(route('locale.set'), { locale }, { preserveScroll: true }); };

const humanSize = computed(() => {
    if (!props.latest?.size_bytes) return null;
    let b = props.latest.size_bytes;
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
    return `${b.toFixed(1)} ${units[i]}`;
});

const localizedNotes = (rel) => (appLocale.value === 'en' ? rel.notes_en : rel.notes_es) || rel.notes_en || rel.notes_es || '';
const localizedTitle = (entry) => (appLocale.value === 'en' ? entry.title_en : entry.title_es) || entry.title_en || entry.title_es || '';
const localizedBody = (entry) => (appLocale.value === 'en' ? entry.body_en : entry.body_es) || entry.body_en || entry.body_es || '';
const entryTypeClass = (type) => ({
    feature: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
    fix:     'bg-blue-500/15 text-blue-300 border-blue-500/30',
    chore:   'bg-slate-500/15 text-slate-300 border-slate-500/30',
}[type] || 'bg-slate-500/15 text-slate-300 border-slate-500/30');
const formatDate = (val) => {
    if (!val) return '';
    try { return new Intl.DateTimeFormat(localeTag.value, { dateStyle: 'medium' }).format(new Date(val)); }
    catch (_) { return String(val).slice(0, 10); }
};
</script>

<template>
    <Head :title="$t('landing.meta.title')" />

    <div class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-black text-white">
        <!-- Top nav -->
        <header class="border-b border-slate-800/60 backdrop-blur sticky top-0 z-10 bg-slate-950/80">
            <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded bg-gradient-to-br from-amber-500 to-red-600 flex items-center justify-center font-bold">A</div>
                    <span class="font-semibold tracking-tight">AdenaLedger</span>
                </Link>
                <nav class="text-sm flex items-center gap-6 text-slate-300">
                    <a href="#features" class="hover:text-white">{{ $t('landing.nav.features') }}</a>
                    <a href="#web-changelog" class="hover:text-white">{{ $t('landing.nav.web_changelog') }}</a>
                    <a href="#changelog" class="hover:text-white">{{ $t('landing.nav.changelog') }}</a>
                    <button @click="setLocale(appLocale === 'es' ? 'en' : 'es')" class="text-xs font-bold tracking-widest hover:text-white">{{ appLocale === 'es' ? 'EN' : 'ES' }}</button>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <section class="max-w-6xl mx-auto px-6 pt-20 pb-16 grid md:grid-cols-2 gap-12 items-center">
            <div>
                <p class="inline-flex items-center gap-2 text-xs uppercase tracking-widest text-amber-400 mb-4">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    {{ $t('landing.hero.badge') }}
                </p>
                <h1 class="text-4xl md:text-5xl font-extrabold leading-tight tracking-tight">
                    {{ $t('landing.hero.title_line1') }}
                    <span class="bg-gradient-to-r from-amber-300 to-red-500 bg-clip-text text-transparent">Lineage 2</span>
                    {{ $t('landing.hero.title_line2') }}
                </h1>
                <p class="mt-5 text-slate-300 text-lg leading-relaxed">{{ $t('landing.hero.subtitle') }}</p>

                <div v-if="latest" class="mt-8 space-y-3">
                    <a :href="latest.download_url"
                       class="inline-flex items-center gap-3 px-6 py-4 rounded-xl bg-gradient-to-r from-amber-500 to-red-600 hover:from-amber-400 hover:to-red-500 text-black font-bold text-lg shadow-lg shadow-red-900/30 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/>
                        </svg>
                        {{ $t('landing.download.btn', { version: latest.version }) }}
                    </a>
                    <div class="text-xs text-slate-500 space-x-3 font-mono">
                        <span v-if="humanSize">{{ humanSize }}</span>
                        <span v-if="latest.sha256">sha256: {{ latest.sha256.slice(0,16) }}…</span>
                        <span v-if="latest.released_at">{{ formatDate(latest.released_at) }}</span>
                    </div>
                    <p v-if="latest.critical_update" class="text-xs text-red-400">⚠ {{ $t('landing.download.critical_warning') }}</p>
                </div>
                <div v-else class="mt-8 text-slate-400 italic">{{ $t('landing.download.no_release') }}</div>
            </div>

            <div class="relative">
                <div class="absolute inset-0 bg-gradient-to-tr from-amber-500/20 to-red-600/20 blur-3xl rounded-full"></div>
                <div class="relative bg-slate-900/80 border border-slate-700 rounded-2xl shadow-2xl p-6 font-mono text-sm space-y-2">
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-3 pb-2 border-b border-slate-800">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                        <span class="ml-2">AdenaLedgerStats overlay — Bishop / Lvl 55</span>
                    </div>
                    <div class="flex justify-between"><span class="text-rose-400">HP</span><span>9 421 / 9 421</span></div>
                    <div class="flex justify-between"><span class="text-sky-400">MP</span><span>4 920 / 5 200</span></div>
                    <div class="flex justify-between"><span class="text-amber-400">CP</span><span>3 200 / 3 200</span></div>
                    <div class="h-px bg-slate-800 my-2"></div>
                    <div class="flex justify-between"><span class="text-slate-400">XP/h</span><span class="text-emerald-400">+ 248 392</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">Adena/h</span><span class="text-emerald-400">+ 49 800</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">{{ $t('landing.preview.ss_used') }}</span><span>1 184</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">{{ $t('landing.preview.deaths') }}</span><span>0</span></div>
                </div>
            </div>
        </section>

        <!-- Disclaimer -->
        <section class="max-w-4xl mx-auto px-6 py-8">
            <div class="border border-amber-700/40 bg-amber-950/30 rounded-xl p-5 text-sm text-amber-100">
                <strong class="text-amber-300">{{ $t('landing.disclaimer.label') }}</strong>
                {{ $t('landing.disclaimer.text') }}
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="max-w-6xl mx-auto px-6 py-16">
            <h2 class="text-3xl font-bold mb-10 text-center">{{ $t('landing.features.title') }}</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">{{ $t('landing.features.overlay.title') }}</h3>
                    <p class="text-sm text-slate-300">{{ $t('landing.features.overlay.text') }}</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">{{ $t('landing.features.ocr.title') }}</h3>
                    <p class="text-sm text-slate-300">{{ $t('landing.features.ocr.text') }}</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">{{ $t('landing.features.stats.title') }}</h3>
                    <p class="text-sm text-slate-300">{{ $t('landing.features.stats.text') }}</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">{{ $t('landing.features.autoupdate.title') }}</h3>
                    <p class="text-sm text-slate-300">{{ $t('landing.features.autoupdate.text') }}</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">{{ $t('landing.features.tickets.title') }}</h3>
                    <p class="text-sm text-slate-300">{{ $t('landing.features.tickets.text') }}</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">{{ $t('landing.features.privacy.title') }}</h3>
                    <p class="text-sm text-slate-300">{{ $t('landing.features.privacy.text') }}</p>
                </div>
            </div>
        </section>

        <!-- Web app changelog: features and fixes shipped to the SaaS,
             tracked in `changelog_entries`. Lives side-by-side with the
             desktop releases section so visitors see both worlds. -->
        <section id="web-changelog" v-if="webChangelog.length" class="max-w-4xl mx-auto px-6 py-16">
            <h2 class="text-3xl font-bold mb-2">{{ $t('landing.web_changelog.title') }}</h2>
            <p class="text-sm text-slate-500 mb-8">{{ $t('landing.web_changelog.subtitle') }}</p>
            <div class="space-y-6">
                <article v-for="entry in webChangelog" :key="'web-'+entry.id" class="border-l-2 border-purple-600 pl-5 py-2">
                    <div class="flex items-baseline gap-3 mb-1 flex-wrap">
                        <span :class="['text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full border', entryTypeClass(entry.type)]">{{ entry.type }}</span>
                        <span class="text-xs text-slate-500">{{ formatDate(entry.published_at) }}</span>
                    </div>
                    <h3 class="text-lg font-semibold text-white mt-1">{{ localizedTitle(entry) }}</h3>
                    <div v-if="localizedBody(entry)" class="text-sm text-slate-300 mt-2 leading-relaxed changelog-body" v-html="renderInlineMarkdown(localizedBody(entry))"></div>
                </article>
            </div>
        </section>

        <!-- Software changelog: each entry is a published release. Fully
             independent from the AdenaLedger web app changelog. -->
        <section id="changelog" v-if="releases.length" class="max-w-4xl mx-auto px-6 py-16">
            <h2 class="text-3xl font-bold mb-2">{{ $t('landing.changelog.title') }}</h2>
            <p class="text-sm text-slate-500 mb-8">{{ $t('landing.changelog.subtitle') }}</p>
            <div class="space-y-6">
                <article v-for="rel in releases" :key="rel.id" class="border-l-2 border-amber-600 pl-5 py-2">
                    <div class="flex items-baseline gap-3 mb-1 flex-wrap">
                        <span class="text-amber-400 font-mono text-sm">v{{ rel.version }}</span>
                        <span class="text-xs text-slate-500">{{ formatDate(rel.released_at) }}</span>
                        <span v-if="rel.channel === 'beta'" class="text-[10px] px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300 uppercase tracking-widest">beta</span>
                        <span v-if="rel.critical_update" class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 uppercase tracking-widest">{{ $t('welcome.download.critical') }}</span>
                    </div>
                    <p v-if="localizedNotes(rel)" class="text-sm text-slate-300 mt-1 whitespace-pre-wrap">{{ localizedNotes(rel) }}</p>
                    <p v-else class="text-sm text-slate-500 italic mt-1">{{ $t('landing.changelog.no_notes') }}</p>
                </article>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-slate-800 mt-16 py-8 text-center text-sm text-slate-500">
            <p>{{ $t('landing.footer.tagline') }}</p>
            <p class="mt-2 space-x-4">
                <Link :href="route('legal.terms')" class="hover:text-white">{{ $t('legal.terms_link') }}</Link>
                <Link :href="route('legal.privacy')" class="hover:text-white">{{ $t('legal.privacy_link') }}</Link>
            </p>
        </footer>
    </div>
</template>
