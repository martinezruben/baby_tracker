import { router } from '@inertiajs/react';
import { Moon, Pause, Play, Square } from 'lucide-react';
import { useT } from '../lib/i18n';
import useFormat from '../lib/useFormat';
import { formatClock } from '../lib/time';
import useBreastTimer from './useBreastTimer';
import useTicker from './useTicker';

const circleButton =
    'flex size-12 shrink-0 items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

function BreastBanner({ session }) {
    const t = useT();
    const { total } = useBreastTimer(session);
    const act = (op) => router.patch(`/pecho/${op}`, {}, { preserveScroll: true });

    return (
        <div className="flex items-center gap-3 rounded-[32px] bg-surface py-2 pr-2 pl-[18px] shadow-card">
            <span aria-hidden="true" className={`size-2.5 shrink-0 rounded-full ${session.paused ? 'bg-ink-soft' : 'bg-live'}`} />
            <div className="flex min-w-0 grow flex-col">
                <div className="truncate text-[13px] text-ink-soft">
                    {t('session.breast_short')} · {t(`session.side_${session.side}`)}
                    {session.paused && ` · ${t('session.paused')}`}
                </div>
                <div className="text-[22px] leading-[1.1] font-bold" aria-live="off">
                    {formatClock(total)}
                </div>
            </div>
            <button
                type="button"
                onClick={() => act(session.paused ? 'reanudar' : 'pausar')}
                aria-label={session.paused ? t('session.resume') : t('session.pause')}
                className={`${circleButton} bg-canvas text-ink`}
            >
                {session.paused ? <Play aria-hidden="true" className="size-[22px]" strokeWidth={1.75} /> : <Pause aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />}
            </button>
            <button type="button" onClick={() => act('finalizar')} aria-label={t('session.finish')} className={`${circleButton} bg-ink text-on-ink`}>
                <Square aria-hidden="true" className="size-5" strokeWidth={1.75} />
            </button>
        </div>
    );
}

function SleepBanner({ session }) {
    const t = useT();
    const format = useFormat();
    const now = useTicker(1000);
    const seconds = Math.max(0, (now - Date.parse(session.started_at)) / 1000);

    return (
        <div className="flex items-center gap-3 rounded-[32px] bg-surface py-2 pr-2 pl-[18px] shadow-card">
            <Moon aria-hidden="true" className="size-5 shrink-0 text-sleep-ink" strokeWidth={1.75} />
            <div className="flex min-w-0 grow flex-col">
                <div className="truncate text-[13px] text-ink-soft">
                    {t('session.sleeping_since', { time: format.time(session.started_at) })}
                </div>
                <div className="text-[22px] leading-[1.1] font-bold">{formatClock(seconds)}</div>
            </div>
            <button
                type="button"
                onClick={() => router.patch(`/sueno/${session.id}/despertar`, {}, { preserveScroll: true })}
                className="min-h-12 shrink-0 rounded-full bg-ink px-5 text-[15px] font-semibold text-on-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                {t('session.wake')}
            </button>
        </div>
    );
}

/** Sesiones en curso bajo la cabecera: lactancia y sueño. */
export default function ActiveSessions({ sessions }) {
    if (!sessions?.breast && !sessions?.sleep) return null;

    return (
        <div className="flex flex-col gap-3">
            {sessions.breast && <BreastBanner session={sessions.breast} />}
            {sessions.sleep && <SleepBanner session={sessions.sleep} />}
        </div>
    );
}
