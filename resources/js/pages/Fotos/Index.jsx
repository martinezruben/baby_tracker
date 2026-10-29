import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Camera, ChevronLeft, ChevronRight, Heart, ImagePlus, MapPin, Search } from 'lucide-react';
import { useRef, useState } from 'react';
import Button from '../../components/Button';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';
import { monthLabel } from '../../lib/lifeEvents';

function Thumb({ photo }) {
    return (
        <li>
            <Link
                href={`/fotos/${photo.id}`}
                className="relative block aspect-square overflow-hidden rounded-2xl bg-track focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                <img src={photo.thumb_url} alt={photo.place_name ?? ''} width="320" height="320" loading="lazy" decoding="async" className="size-full object-cover" />
                {photo.favorite && (
                    <Heart aria-label="♥" className="absolute top-1.5 right-1.5 size-5 fill-white text-white drop-shadow" strokeWidth={1.5} />
                )}
                {photo.has_location && <MapPin aria-hidden="true" className="absolute bottom-1.5 left-1.5 size-4 text-white drop-shadow" strokeWidth={2} />}
            </Link>
        </li>
    );
}

/** Números de página con huecos: 1 … 4 5 6 … 20. */
function pageList(page, pages) {
    const set = new Set([1, pages, page - 1, page, page + 1].filter((n) => n >= 1 && n <= pages));
    const sorted = [...set].sort((a, b) => a - b);
    return sorted.flatMap((n, i) => (i > 0 && n - sorted[i - 1] > 1 ? ['gap-' + n, n] : [n]));
}

function Pagination({ pagination, onPage }) {
    const t = useT();
    const { page, pages, total, per } = pagination;
    if (pages <= 1) return null;

    const btn = 'flex size-12 shrink-0 items-center justify-center rounded-full text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-40';

    return (
        <nav aria-label={t('fotos.pages')} className="flex flex-col items-center gap-2">
            <ul className="flex flex-wrap items-center justify-center gap-1">
                <li>
                    <button type="button" aria-label={t('fotos.prev')} disabled={page <= 1} onClick={() => onPage(page - 1)} className={`${btn} bg-surface`}>
                        <ChevronLeft aria-hidden="true" className="size-5" />
                    </button>
                </li>
                {pageList(page, pages).map((n) =>
                    typeof n === 'string' ? (
                        <li key={n} aria-hidden="true" className="px-1 text-ink-soft">
                            …
                        </li>
                    ) : (
                        <li key={n}>
                            <button
                                type="button"
                                aria-current={n === page ? 'page' : undefined}
                                aria-label={t('fotos.page_n', { n })}
                                onClick={() => onPage(n)}
                                className={`${btn} ${n === page ? 'bg-primary font-bold text-on-primary' : 'bg-surface font-medium'}`}
                            >
                                {n}
                            </button>
                        </li>
                    ),
                )}
                <li>
                    <button type="button" aria-label={t('fotos.next')} disabled={page >= pages} onClick={() => onPage(page + 1)} className={`${btn} bg-surface`}>
                        <ChevronRight aria-hidden="true" className="size-5" />
                    </button>
                </li>
            </ul>
            <p className="text-xs text-ink-soft">{t('fotos.showing', { first: (page - 1) * per + 1, last: Math.min(page * per, total), total })}</p>
        </nav>
    );
}

export default function Index({ filters, photos, pagination, tags, months, quota }) {
    const t = useT();
    const { babies, activeBabyId, locale } = usePage().props;
    const baby = babies.find((b) => b.id === activeBabyId);
    const input = useRef(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [place, setPlace] = useState(filters.lugar ?? '');

    const apply = (patch) => {
        const params = { ...filters, pagina: pagination.page, ...patch };
        if (!('pagina' in patch)) delete params.pagina; // cambiar un filtro vuelve a la página 1
        if (params.orden === 'recientes') delete params.orden;
        Object.keys(params).forEach((k) => (params[k] === null || params[k] === '' || params[k] === false) && delete params[k]);
        router.get('/fotos', params, { only: ['filters', 'photos', 'pagination', 'tags', 'months'], preserveState: true, preserveScroll: !('pagina' in patch), replace: true });
    };

    const goTo = (pagina) => apply({ pagina });

    const upload = (fileList) => {
        const files = Array.from(fileList);
        if (files.length === 0) return;
        setBusy(true);
        setError(null);
        router.post('/fotos', { photos: files }, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => setError(Object.values(errors)[0]),
            onFinish: () => {
                setBusy(false);
                if (input.current) input.current.value = '';
            },
        });
    };

    const groups = [];
    for (const photo of photos) {
        const key = photo.taken_at.slice(0, 7);
        const last = groups.at(-1);
        if (last?.key === key) last.photos.push(photo);
        else groups.push({ key, photos: [photo] });
    }

    const chip = (selected, label, onClick) => (
        <button
            key={label}
            type="button"
            aria-pressed={selected}
            onClick={onClick}
            className={`flex min-h-11 shrink-0 items-center rounded-full px-5 text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                selected ? 'bg-primary font-semibold text-on-primary' : 'bg-surface font-medium text-ink'
            }`}
        >
            {label}
        </button>
    );

    const anyFilter = filters.etiqueta || filters.favoritas || filters.mes || filters.lugar;

    return (
        <AppLayout title={t('fotos.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav.home')}
                </Link>

                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl leading-tight font-bold">{t('fotos.title')}</h1>
                        {baby && <div className="text-sm text-ink-soft">{t('fotos.subtitle', { name: baby.first_name })}</div>}
                    </div>
                    <Button onClick={() => input.current?.click()} disabled={busy}>
                        <ImagePlus aria-hidden="true" className="size-5" />
                        {busy ? t('fotos.uploading') : t('fotos.add')}
                    </Button>
                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        aria-label={t('fotos.add')}
                        onChange={(e) => upload(e.target.files)}
                    />
                </header>
                {error && (
                    <p role="alert" className="text-sm font-medium text-danger-ink">
                        {error}
                    </p>
                )}

                <nav aria-label={t('fotos.filters')} className="-mr-5 flex gap-2 overflow-x-auto pr-5 [scrollbar-width:none]">
                    {chip(!anyFilter, t('fotos.all'), () => apply({ etiqueta: null, favoritas: false, mes: null, lugar: null }))}
                    {chip(Boolean(filters.favoritas), t('fotos.favorites'), () => apply({ favoritas: !filters.favoritas }))}
                    {tags.map((tag) => chip(filters.etiqueta === tag.id, `${tag.name} · ${tag.count}`, () => apply({ etiqueta: filters.etiqueta === tag.id ? null : tag.id })))}
                </nav>

                <div className="flex gap-2">
                    <label className="sr-only" htmlFor="photo-month">
                        {t('fotos.month')}
                    </label>
                    <select
                        id="photo-month"
                        value={filters.mes ?? ''}
                        onChange={(e) => apply({ mes: e.target.value || null })}
                        className="min-h-12 min-w-0 shrink-0 rounded-full border border-line bg-surface px-4 text-[15px]"
                    >
                        <option value="">{t('fotos.any_month')}</option>
                        {months.map((m) => (
                            <option key={m} value={m}>
                                {monthLabel(m, locale)}
                            </option>
                        ))}
                    </select>
                    <label className="sr-only" htmlFor="photo-order">
                        {t('fotos.order')}
                    </label>
                    <select
                        id="photo-order"
                        value={filters.orden}
                        onChange={(e) => apply({ orden: e.target.value })}
                        className="min-h-12 min-w-0 shrink-0 rounded-full border border-line bg-surface px-4 text-[15px]"
                    >
                        <option value="recientes">{t('fotos.newest')}</option>
                        <option value="antiguas">{t('fotos.oldest')}</option>
                    </select>
                </div>

                <div className="flex gap-2">
                    <form
                        className="relative min-w-0 grow"
                        role="search"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ lugar: place.trim() || null });
                        }}
                    >
                        <label className="sr-only" htmlFor="photo-place">
                            {t('fotos.place_search')}
                        </label>
                        <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-ink-soft" />
                        <input
                            id="photo-place"
                            value={place}
                            onChange={(e) => setPlace(e.target.value)}
                            placeholder={t('fotos.place_search')}
                            className="min-h-12 w-full rounded-full border border-line bg-surface pr-4 pl-10 text-[15px]"
                        />
                    </form>
                </div>

                {photos.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center shadow-card">
                        <Camera aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                        <p className="text-base font-semibold">{anyFilter ? t('fotos.empty_filtered') : t('fotos.empty')}</p>
                        {!anyFilter && <p className="text-sm text-ink-soft">{t('fotos.empty_hint')}</p>}
                    </div>
                ) : (
                    groups.map((group) => (
                        <section key={group.key} aria-label={monthLabel(group.key, locale)} className="flex flex-col gap-2">
                            <h2 className="text-[15px] font-semibold text-ink-soft">{monthLabel(group.key, locale)}</h2>
                            <ul className="grid grid-cols-3 gap-1.5">
                                {group.photos.map((photo) => (
                                    <Thumb key={photo.id} photo={photo} />
                                ))}
                            </ul>
                        </section>
                    ))
                )}

                <Pagination pagination={pagination} onPage={goTo} />

                <p className="text-center text-xs text-ink-soft">{t('fotos.quota', { used: quota.used_mb, total: quota.total_mb })}</p>
            </div>
        </AppLayout>
    );
}
