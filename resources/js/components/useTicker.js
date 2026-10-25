import { useEffect, useState } from 'react';

/** Hace que el componente se vuelva a pintar cada `ms` (cronómetros y "hace cuánto"). */
export default function useTicker(ms = 1000) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = setInterval(() => setNow(Date.now()), ms);
        return () => clearInterval(id);
    }, [ms]);

    return now;
}
