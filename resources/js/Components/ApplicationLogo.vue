<template>
    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" v-bind="$attrs">
        <rect width="40" height="40" rx="8" fill="url(#logo-bg)"/>
        <!-- Shield/ledger shape -->
        <path d="M20 6L10 11v9c0 7.18 4.28 13.9 10 16 5.72-2.1 10-8.82 10-16v-9L20 6z" fill="url(#logo-shield)" opacity="0.15"/>
        <path d="M20 8L12 12v8c0 6.15 3.56 11.88 8 13.72V8z" fill="url(#logo-left)" opacity="0.25"/>
        <!-- Adena coin symbol -->
        <circle cx="20" cy="19" r="7" stroke="url(#logo-ring)" stroke-width="1.5" fill="none"/>
        <circle cx="20" cy="19" r="4.5" stroke="url(#logo-ring)" stroke-width="1" fill="none" opacity="0.5"/>
        <!-- A lettermark -->
        <path d="M17.5 23L20 14l2.5 9M18.3 21h3.4" stroke="url(#logo-letter)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <defs>
            <linearGradient id="logo-bg" x1="0" y1="0" x2="40" y2="40">
                <stop stop-color="#1a1025"/>
                <stop offset="1" stop-color="#0f0a18"/>
            </linearGradient>
            <linearGradient id="logo-shield" x1="10" y1="6" x2="30" y2="36">
                <stop stop-color="#a855f7"/>
                <stop offset="1" stop-color="#7c3aed"/>
            </linearGradient>
            <linearGradient id="logo-left" x1="12" y1="8" x2="20" y2="34">
                <stop stop-color="#a855f7"/>
                <stop offset="1" stop-color="#6d28d9"/>
            </linearGradient>
            <linearGradient id="logo-ring" x1="13" y1="12" x2="27" y2="26">
                <stop stop-color="#c084fc"/>
                <stop offset="1" stop-color="#f59e0b"/>
            </linearGradient>
            <linearGradient id="logo-letter" x1="17" y1="14" x2="23" y2="23">
                <stop stop-color="#e9d5ff"/>
                <stop offset="1" stop-color="#fbbf24"/>
            </linearGradient>
        </defs>
    </svg>
</template>
