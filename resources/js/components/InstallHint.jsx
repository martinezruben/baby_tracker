import { Download, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useT } from '../lib/i18n';
import Button from './Button';

const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent);

/** Ayuda para instalar la app: botón nativo en Android/Chrome, instrucciones en iPhone. */
export default function InstallHint() {
    const t = useT();
    const [installed, setInstalled] = useState(false);
    const [prompt, setPrompt] = useState(null);
    const [ios, setIos] = useState(false);

    useEffect(() => {
        setInstalled(standalone());
        setIos(isIos());
        const onPrompt = (e) => {
            e.preventDefault();
            setPrompt(e);
        };
        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', () => setInstalled(true));

        return () => window.removeEventListener('beforeinstallprompt', onPrompt);
    }, []);

    const install = async () => {
        prompt.prompt();
        await prompt.userChoice;
        setPrompt(null);
    };

    return (
        <div className="flex flex-col gap-3 rounded-3xl bg-surface p-4 shadow-card">
            <div className="flex items-center gap-3">
                <span className="flex size-[46px] shrink-0 items-center justify-center rounded-2xl bg-growth text-growth-ink">
                    <Smartphone aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </span>
                <h2 className="text-base font-bold">{t('more.install_title')}</h2>
            </div>
            {installed ? (
                <p className="text-sm text-ink-soft">{t('more.installed')}</p>
            ) : (
                <>
                    <p className="text-sm text-ink-soft">{ios ? t('more.install_ios') : t('more.install_android')}</p>
                    {prompt && (
                        <Button onClick={install}>
                            <Download aria-hidden="true" className="size-5" />
                            {t('more.install_button')}
                        </Button>
                    )}
                </>
            )}
        </div>
    );
}
