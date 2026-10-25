import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} · Baby Tracker` : 'Baby Tracker'),
    // Cada pantalla se descarga solo cuando se visita (carga inicial más ligera).
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.jsx');
        return pages[`./pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
