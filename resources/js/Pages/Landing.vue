<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    latest: Object,
    changelog: Array,
});

const humanSize = computed(() => {
    if (!props.latest?.size_bytes) return null;
    let b = props.latest.size_bytes;
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
    return `${b.toFixed(1)} ${units[i]}`;
});
</script>

<template>
    <Head title="Download — AdenaLedgerStats for Lu4" />

    <div class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-black text-white">
        <!-- Top nav -->
        <header class="border-b border-slate-800/60 backdrop-blur sticky top-0 z-10 bg-slate-950/80">
            <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded bg-gradient-to-br from-amber-500 to-red-600 flex items-center justify-center font-bold">A</div>
                    <span class="font-semibold tracking-tight">AdenaLedger</span>
                </Link>
                <nav class="text-sm flex items-center gap-6 text-slate-300">
                    <a href="#features" class="hover:text-white">Features</a>
                    <a href="#changelog" class="hover:text-white">Changelog</a>
                    <Link :href="route('login')" class="hover:text-white">Login</Link>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <section class="max-w-6xl mx-auto px-6 pt-20 pb-16 grid md:grid-cols-2 gap-12 items-center">
            <div>
                <p class="inline-flex items-center gap-2 text-xs uppercase tracking-widest text-amber-400 mb-4">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    Only for the Lu4 private server
                </p>
                <h1 class="text-4xl md:text-5xl font-extrabold leading-tight tracking-tight">
                    Overlay &amp; tracker for
                    <span class="bg-gradient-to-r from-amber-300 to-red-500 bg-clip-text text-transparent">Lineage 2</span>
                    on Lu4
                </h1>
                <p class="mt-5 text-slate-300 text-lg leading-relaxed">
                    HP/MP/CP overlay, OCR-based chat parser, session stats, deaths, XP/h, adena/h, soulshot tracking and
                    a clean dark HUD that stays out of your way. Single binary, no installer, no game-file modification.
                </p>

                <div v-if="latest" class="mt-8 space-y-3">
                    <a :href="latest.download_url"
                       class="inline-flex items-center gap-3 px-6 py-4 rounded-xl bg-gradient-to-r from-amber-500 to-red-600 hover:from-amber-400 hover:to-red-500 text-black font-bold text-lg shadow-lg shadow-red-900/30 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/>
                        </svg>
                        Download {{ latest.version }}
                    </a>
                    <div class="text-xs text-slate-500 space-x-3 font-mono">
                        <span v-if="humanSize">{{ humanSize }}</span>
                        <span v-if="latest.sha256">sha256: {{ latest.sha256.slice(0,16) }}…</span>
                        <span v-if="latest.released_at">{{ latest.released_at.slice(0,10) }}</span>
                    </div>
                    <p v-if="latest.critical_update" class="text-xs text-red-400">
                        ⚠ Critical update — previous versions are no longer supported.
                    </p>
                </div>
                <div v-else class="mt-8 text-slate-400 italic">No published release yet.</div>
            </div>

            <div class="relative">
                <div class="absolute inset-0 bg-gradient-to-tr from-amber-500/20 to-red-600/20 blur-3xl rounded-full"></div>
                <div class="relative bg-slate-900/80 border border-slate-700 rounded-2xl shadow-2xl p-6 font-mono text-sm space-y-2">
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-3 pb-2 border-b border-slate-800">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                        <span class="ml-2">Lu4Bot overlay — Bishop / Lvl 55</span>
                    </div>
                    <div class="flex justify-between"><span class="text-rose-400">HP</span><span>9 421 / 9 421</span></div>
                    <div class="flex justify-between"><span class="text-sky-400">MP</span><span>4 920 / 5 200</span></div>
                    <div class="flex justify-between"><span class="text-amber-400">CP</span><span>3 200 / 3 200</span></div>
                    <div class="h-px bg-slate-800 my-2"></div>
                    <div class="flex justify-between"><span class="text-slate-400">XP/h</span><span class="text-emerald-400">+ 248 392</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">Adena/h</span><span class="text-emerald-400">+ 49 800</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">SS used</span><span>1 184</span></div>
                    <div class="flex justify-between"><span class="text-slate-400">Deaths</span><span>0</span></div>
                </div>
            </div>
        </section>

        <!-- Disclaimer -->
        <section class="max-w-4xl mx-auto px-6 py-8">
            <div class="border border-amber-700/40 bg-amber-950/30 rounded-xl p-5 text-sm text-amber-100">
                <strong class="text-amber-300">Important.</strong> This software only works on the
                <span class="font-mono">Lu4</span> private server. It is not affiliated with or endorsed by NCsoft.
                Use of this tool is at your own responsibility — running third-party software alongside Lineage 2
                may be against your server's terms of service. The OCR runs locally on screen pixels only; no game memory
                is read or modified.
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="max-w-6xl mx-auto px-6 py-16">
            <h2 class="text-3xl font-bold mb-10 text-center">What's in the box</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">Always-on overlay</h3>
                    <p class="text-sm text-slate-300">Compact HUD with HP / MP / CP, soulshot status, XP/h, adena/h and last hit tracker. Transparent, repositionable.</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">OCR-based parsing</h3>
                    <p class="text-sm text-slate-300">Reads bars and chat from pixels using RapidOCR (ONNX) with Tesseract fallback. Multi-process pool keeps the game responsive.</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">Session stats</h3>
                    <p class="text-sm text-slate-300">Local SQLite history of every session: kills, deaths, items obtained, damage in/out. Resumable on game restart.</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">Auto-updates</h3>
                    <p class="text-sm text-slate-300">The bot checks for new versions on launch and updates itself with one click. No reinstall, no Steam, no fuss.</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">In-app support tickets</h3>
                    <p class="text-sm text-slate-300">Hit a bug? Open a ticket from the overlay; logs and settings are attached automatically (scrubbed for privacy).</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-6">
                    <h3 class="font-bold text-amber-400 mb-2">Privacy first</h3>
                    <p class="text-sm text-slate-300">Character names and chat with nicks are stripped before any optional telemetry leaves your machine. Opt-in, opt-out, or fully offline.</p>
                </div>
            </div>
        </section>

        <!-- Changelog -->
        <section id="changelog" v-if="changelog && changelog.length" class="max-w-4xl mx-auto px-6 py-16">
            <h2 class="text-3xl font-bold mb-8">Recent changes</h2>
            <div class="space-y-6">
                <article v-for="entry in changelog" :key="entry.id"
                         class="border-l-2 border-amber-600 pl-5 py-2">
                    <div class="flex items-baseline gap-3 mb-1">
                        <span class="text-amber-400 font-mono text-sm">{{ entry.version || entry.type }}</span>
                        <span class="text-xs text-slate-500">{{ entry.published_at?.slice(0,10) }}</span>
                    </div>
                    <h3 class="font-semibold">{{ entry.title_en }}</h3>
                    <p v-if="entry.body_en" class="text-sm text-slate-300 mt-1 whitespace-pre-wrap">{{ entry.body_en }}</p>
                </article>
            </div>
            <div class="mt-8 text-center">
                <Link :href="route('changelog.index')" class="text-amber-400 hover:underline text-sm">View full changelog →</Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-slate-800 mt-16 py-8 text-center text-sm text-slate-500">
            <p>AdenaLedgerStats — for the Lu4 community. Not affiliated with NCsoft.</p>
            <p class="mt-2 space-x-4">
                <Link :href="route('legal.terms')" class="hover:text-white">Terms</Link>
                <Link :href="route('legal.privacy')" class="hover:text-white">Privacy</Link>
            </p>
        </footer>
    </div>
</template>
