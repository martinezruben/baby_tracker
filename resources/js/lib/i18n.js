import { usePage } from '@inertiajs/react';

function lookup(tree, key) {
    return key.split('.').reduce((node, part) => (node == null ? node : node[part]), tree);
}

/** Devuelve t('grupo.clave', { nombre: 'x' }) con los textos que manda el servidor. */
export function useT() {
    const { t: messages } = usePage().props;

    return (key, vars = {}) => {
        const text = lookup(messages, key);
        if (typeof text !== 'string') return key;

        return Object.entries(vars).reduce((out, [name, value]) => out.replaceAll(`:${name}`, value), text);
    };
}
