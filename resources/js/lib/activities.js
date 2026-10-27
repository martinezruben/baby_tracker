import {
    Baby, BarChart3, Bath, Bell, Camera, CalendarDays, Droplet, Globe, Heart, Milk, Moon, Pill, Ruler,
    Settings, ShieldAlert, Smile, Star, Stethoscope, StickyNote, Syringe, TrendingUp, Users, Utensils,
} from 'lucide-react';

/**
 * Tipos de actividad. Las clases de color se escriben completas para que Tailwind las encuentre:
 * bg = tarjeta, deco = círculo decorativo, ink = icono, chip = cuadradito de la leyenda.
 */
export const KINDS = {
    food: { bg: 'bg-food', deco: 'bg-food-deco', ink: 'text-food-ink', chip: 'bg-chart-food', border: 'border-chart-food' },
    sleep: { bg: 'bg-sleep', deco: 'bg-sleep-deco', ink: 'text-sleep-ink', chip: 'bg-chart-sleep', border: 'border-chart-sleep' },
    diaper: { bg: 'bg-diaper', deco: 'bg-diaper-deco', ink: 'text-diaper-ink', chip: 'bg-chart-diaper', border: 'border-chart-diaper' },
    medicine: { bg: 'bg-medicine', deco: 'bg-medicine-deco', ink: 'text-medicine-ink', chip: 'bg-chart-medicine', border: 'border-chart-medicine' },
    vaccine: { bg: 'bg-vaccine', deco: 'bg-vaccine-deco', ink: 'text-vaccine-ink', chip: 'bg-vaccine-ink', border: 'border-vaccine-ink' },
    appointment: { bg: 'bg-appointment', deco: 'bg-appointment-deco', ink: 'text-appointment-ink', chip: 'bg-appointment-ink', border: 'border-appointment-ink' },
    growth: { bg: 'bg-growth', deco: 'bg-growth-deco', ink: 'text-growth-ink', chip: 'bg-chart-play', border: 'border-chart-play' },
    moment: { bg: 'bg-moment', deco: 'bg-moment-deco', ink: 'text-moment-ink', chip: 'bg-moment-ink', border: 'border-moment-ink' },
    neutral: { bg: 'bg-neutral-tile', deco: 'bg-track', ink: 'text-ink-mid', chip: 'bg-ink-mid', border: 'border-ink-mid' },
};

/** Registro: slug de la URL -> icono y tipo. `available: false` = módulo aún sin construir. */
export const ACTIVITIES = {
    biberon: { kind: 'food', icon: Milk, available: true },
    pecho: { kind: 'food', icon: Heart, available: true },
    panal: { kind: 'diaper', icon: Baby, available: true },
    sueno: { kind: 'sleep', icon: Moon, available: true },
    solidos: { kind: 'food', icon: Utensils, available: true },
    extraccion: { kind: 'food', icon: Droplet, available: true },
    bano: { kind: 'growth', icon: Bath, available: true },
    juego: { kind: 'moment', icon: Globe, available: true },
    medicina: { kind: 'medicine', icon: Pill, available: true },
    medida: { kind: 'growth', icon: Ruler, available: true },
    vacuna: { kind: 'vaccine', icon: Syringe, available: true, href: '/vacunas/aplicar' },
    cita: { kind: 'appointment', icon: Stethoscope, available: true, href: '/citas/nueva' },
    hito: { kind: 'moment', icon: Star, available: true, href: '/eventos/nuevo' },
    foto: { kind: 'moment', icon: Camera, available: true, href: '/fotos' },
    nota: { kind: 'moment', icon: StickyNote, available: true },
    animo: { kind: 'moment', icon: Smile, available: true },
};

export const GROUPS = {
    rutina: ['biberon', 'pecho', 'panal', 'sueno', 'solidos', 'extraccion', 'bano', 'juego'],
    salud: ['medicina', 'medida', 'vacuna', 'cita'],
    momentos: ['hito', 'foto', 'nota', 'animo'],
};

/** "Todo sobre el bebé": 12 módulos del mockup más Recordatorios. `href` = ya existe. */
export const MODULES = [
    { key: 'calendar', icon: CalendarDays, kind: 'diaper', href: '/calendario' },
    { key: 'reminders', icon: Bell, kind: 'appointment', href: '/recordatorios' },
    { key: 'appointments', icon: Stethoscope, kind: 'appointment', href: '/citas' },
    { key: 'vaccines', icon: Syringe, kind: 'vaccine', href: '/vacunas' },
    { key: 'medicines', icon: Pill, kind: 'medicine', href: '/medicinas' },
    { key: 'growth', icon: TrendingUp, kind: 'growth', href: '/crecimiento' },
    { key: 'photos', icon: Camera, kind: 'moment', href: '/fotos' },
    { key: 'moments', icon: Star, kind: 'moment', href: '/eventos' },
    { key: 'reports', icon: BarChart3, kind: 'growth', href: '/reportes' },
    { key: 'allergies', icon: ShieldAlert, kind: 'food', href: '/alergias' },
    { key: 'contacts', icon: Users, kind: 'sleep', href: '/contactos' },
    { key: 'settings', icon: Settings, kind: 'neutral', href: '/ajustes', adminOnly: true },
];
