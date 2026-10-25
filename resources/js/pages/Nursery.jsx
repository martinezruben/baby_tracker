import { Head, Link, router } from '@inertiajs/react';
import { Baby, Heart, Milk, Moon, Sun, X } from 'lucide-react';
import { useEffect } from 'react';
import Snackbar from '../components/Snackbar';
import useTicker from '../components/useTicker';
import { agoLabel, useT } from '../lib/i18n';
import { enterNursery, exitNursery, setBrightness, useTheme } from '../lib/theme';
import { minutesSince } from '../lib/time';
import useFormat from '../lib/useFormat';

const tile =
    'flex min-h-36 flex-col items-center justify-center gap-3 rounded-[20px] border border-line bg-surface text-[22px] font-bold text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

export default function Nursery({ baby, lastFeedAt, lastDiaperAt, sleepingId, breastActive }) {
    const t = useT();
    const format = useFormat();
    const now = useTicker(1000);
    const { pref, brightness } = useTheme();

    // Esta pantalla es el modo nursery: se activa al entrar y "Salir" devuelve el tema anterior.
    useEffect(() => {
        if (pref !== 'nursery') enterNursery();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const leave = () => {
        exitNursery();
        router.visit('/');
    };

    const clockText = format.time(new Date(now).toISOString());
    const ago = (iso) => (iso ? agoLabel(t, minutesSince(iso)).toLowerCase() : t('registrar.none'));

    return (
        <>
            <Head title={t('nursery.title')} />
            <main className="mx-auto flex min-h-dvh w-full max-w-xl flex-col gap-4 px-4 pt-2 pb-6">
                <header className="flex min-h-14 items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div aria-hidden="true" className="flex size-10 items-center justify-center rounded-full bg-track text-lg font-bold text-ink">
                            {baby.first_name.slice(0, 1).toUpperCase()}
                        </div>
                        <div className="text-xl font-bold">{baby.first_name}</div>
                    </div>
                    <button type="button" onClick={leave} className="flex min-h-12 items-center gap-2 rounded-full border border-line px-4 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-primary">
                        <X aria-hidden="true" className="size-5" strokeWidth={1.75} />
                        {t('nursery.exit')}
                    </button>
                </header>

                <section aria-live="off" className="flex flex-col items-center gap-1 py-2 text-center">
                    <div className="text-[72px] leading-none font-bold">
                        {clockText}
                    </div>
                    <p className="text-base text-ink-soft">{t('nursery.last', { feed: ago(lastFeedAt), diaper: ago(lastDiaperAt) })}</p>
                </section>

                <div className="grid flex-1 grid-cols-2 gap-3">
                    <Link href="/registrar/panal?nursery=1" className={tile}>
                        <Baby aria-hidden="true" className="size-14" strokeWidth={1.5} />
                        {t('registrar.tiles.panal')}
                    </Link>
                    <Link href="/registrar/biberon?nursery=1" className={tile}>
                        <Milk aria-hidden="true" className="size-14" strokeWidth={1.5} />
                        {t('registrar.tiles.biberon')}
                    </Link>
                    <Link href="/registrar/pecho" className={tile}>
                        <Heart aria-hidden="true" className="size-14" strokeWidth={1.5} />
                        {breastActive ? t('nursery.breast_active') : t('registrar.tiles.pecho')}
                    </Link>
                    {sleepingId ? (
                        <button type="button" onClick={() => router.patch(`/sueno/${sleepingId}/despertar`, {}, { preserveScroll: true })} className={tile}>
                            <Sun aria-hidden="true" className="size-14" strokeWidth={1.5} />
                            {t('nursery.wake')}
                        </button>
                    ) : (
                        <Link href="/registrar/sueno?nursery=1" className={tile}>
                            <Moon aria-hidden="true" className="size-14" strokeWidth={1.5} />
                            {t('nursery.sleep')}
                        </Link>
                    )}
                </div>

                <div className="flex min-h-12 items-center gap-3">
                    <Sun aria-hidden="true" className="size-6 shrink-0 text-ink-soft" strokeWidth={1.75} />
                    <input
                        type="range"
                        min="5"
                        max="100"
                        step="5"
                        value={brightness}
                        onChange={(e) => setBrightness(Number(e.target.value))}
                        aria-label={t('nursery.brightness')}
                        className="h-12 grow accent-primary"
                    />
                    <div className="w-12 text-right text-sm text-ink-soft">{brightness} %</div>
                </div>
            </main>
            <Snackbar />
        </>
    );
}
