export default {
    install: (app) => {
        app.config.globalProperties.$t = (key) => {
            // Obtener prop translations inyectado por Inertia
            const translations = app.config.globalProperties.$page?.props?.translations || {};
            // Retornar valor o la misma key si no existe
            return translations[key] || key;
        };
    }
};
