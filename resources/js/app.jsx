import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import ThemeEffects from './components/ThemeEffects';

createInertiaApp({
    title: (title) => (title ? `${title} · Kidu Baby Tracker` : 'Kidu Baby Tracker'),
    // Cada pantalla se descarga solo cuando se visita (carga inicial más ligera).
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.jsx');
        return pages[`./pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <ThemeEffects />
            </>,
        );
    },
});

// Con la pantalla ya pintada y el navegador libre, se bajan en segundo plano las pantallas más usadas: al tocar
// el menú el código ya está aquí y solo falta la respuesta del servidor. No se hace con "ahorro de datos".
const warm = ['Inicio', 'Registrar/Index', 'Bitacora', 'Calendario', 'Mas', 'Registrar/Panal', 'Registrar/Biberon', 'Registrar/Sueno', 'Registrar/Pecho'];
window.addEventListener('load', () => {
    if (navigator.connection?.saveData) return;
    const pages = import.meta.glob('./pages/**/*.jsx');
    const run = () => warm.forEach((name) => pages[`./pages/${name}.jsx`]?.().catch(() => {}));
    'requestIdleCallback' in window ? window.requestIdleCallback(run, { timeout: 4000 }) : setTimeout(run, 2000);
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
