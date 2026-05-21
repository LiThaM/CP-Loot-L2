export const formatAdenaShort = (val, locale = 'en-US') => {
    const n = Number(val ?? 0);
    if (!Number.isFinite(n)) return '0';
    const sign = n < 0 ? '-' : '';
    const abs = Math.abs(n);

    if (abs >= 1_000_000) {
        const m = abs / 1_000_000;
        const str = Number.isInteger(m) ? String(m) : String(Number(m.toFixed(1))).replace(/\.0$/, '');
        return `${sign}${str}kk`;
    }

    if (abs >= 1_000) {
        const k = abs / 1_000;
        const str = Number.isInteger(k) ? String(k) : String(Number(k.toFixed(1))).replace(/\.0$/, '');
        return `${sign}${str}k`;
    }

    return `${sign}${Math.trunc(abs)}`;
};

export const formatAdenaFull = (val, locale = 'en-US') => {
    const n = Number(val ?? 0);
    return new Intl.NumberFormat(locale).format(Number.isFinite(n) ? Math.trunc(n) : 0);
};
