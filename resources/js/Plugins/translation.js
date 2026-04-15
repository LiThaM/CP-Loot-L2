export default {
    install: (app) => {
        app.config.globalProperties.$t = (key, params = {}) => {
            const translations = app.config.globalProperties.$page?.props?.translations || {};
            const raw = translations[key] || key;
            if (!raw || typeof raw !== 'string') return raw;
            return raw.replace(/\{(\w+)\}/g, (match, p1) => {
                if (Object.prototype.hasOwnProperty.call(params, p1)) {
                    return String(params[p1]);
                }
                return match;
            });
        };
    }
};
