import { useMemo } from 'react';
import useTicker from './useTicker';

/** Segundos por lado de la lactancia en curso, corriendo en vivo entre recargas del servidor. */
export default function useBreastTimer(session) {
    const now = useTicker(1000);

    // Momento en que llegó este estado del servidor: desde ahí corre el lado activo.
    const anchor = useMemo(() => Date.now(), [session?.left_seconds, session?.right_seconds, session?.side, session?.paused]);

    if (!session) return { left: 0, right: 0, total: 0 };

    const elapsed = session.paused ? 0 : Math.max(0, (now - anchor) / 1000);
    const left = session.left_seconds + (session.side === 'left' ? elapsed : 0);
    const right = session.right_seconds + (session.side === 'right' ? elapsed : 0);

    return { left, right, total: left + right };
}
