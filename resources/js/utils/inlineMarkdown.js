// Tiny renderer for changelog/body fields. Supports the subset we
// actually use: [label](href), **bold**, *italic*, `code`, line breaks.
// Escapes HTML up-front so any literal angle brackets in the source
// stay literal — the only way to inject HTML is via the limited
// markdown syntax we whitelist, and even there `href` is validated.

const escapeHtml = (s) => String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

// Allow same-origin relative paths and explicit https links. Anything
// else (javascript:, data:, vbscript:, etc.) is treated as plain text,
// so a malicious entry like [x](javascript:alert(1)) renders as text.
const isSafeHref = (href) => {
    if (!href || typeof href !== 'string') return false;
    if (href.startsWith('/')) return true;
    if (/^https:\/\//i.test(href)) return true;
    return false;
};

export const renderInlineMarkdown = (input) => {
    if (input == null) return '';
    let out = escapeHtml(input);

    // `code` (before bold/italic so the asterisks inside are kept literal).
    out = out.replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 rounded bg-gray-200/60 dark:bg-gray-800/60 text-[0.9em]">$1</code>');

    // [label](href)
    out = out.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (match, label, href) => {
        if (!isSafeHref(href)) return match; // leave literal
        const safeHref = escapeHtml(href);
        return `<a href="${safeHref}" class="font-bold text-purple-700 dark:text-purple-300 underline-offset-2 hover:underline">${label}</a>`;
    });

    // **bold**
    out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    // *italic* — single-star, must not touch already-converted strong.
    out = out.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');

    // Line breaks → <br>. Double newlines stay as two breaks so paragraphs
    // visually separate without needing a real <p> wrapper.
    out = out.replace(/\n/g, '<br>');

    return out;
};
