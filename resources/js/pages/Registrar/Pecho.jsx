import { Link, useForm } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import OptionGrid from '../../components/form/OptionGrid';
import RegistersAs from '../../components/form/RegistersAs';
import SheetLayout from '../../layouts/SheetLayout';
import { useT } from '../../lib/i18n';

export default function Pecho({ baby, active }) {
    const t = useT();
    const form = useForm({ side: 'left' });

    return (
        <SheetLayout
            title={t('registrar.breast.title')}
            subtitle={t('registrar.breast.subtitle', { name: baby.first_name })}
            kind="food"
            icon={Heart}
            actionLabel={t('registrar.breast.start')}
            disabled={active}
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/registrar/pecho');
            }}
        >
            {active ? (
                <p role="status" className="rounded-[20px] bg-canvas p-4 text-base">
                    {t('registrar.breast.already_active')}{' '}
                    <Link href="/" className="font-semibold text-primary underline">
                        {t('registrar.go_home')}
                    </Link>
                </p>
            ) : (
                <OptionGrid
                    label={t('registrar.breast.side')}
                    columns={2}
                    value={form.data.side}
                    onChange={(v) => form.setData('side', v)}
                    options={[
                        { value: 'left', label: t('registrar.breast.left'), icon: Heart, iconClass: 'text-food-ink' },
                        { value: 'right', label: t('registrar.breast.right'), icon: Heart, iconClass: 'text-food-ink' },
                    ]}
                />
            )}
            {form.errors.side && (
                <p role="alert" className="text-sm font-medium text-red-700">
                    {form.errors.side}
                </p>
            )}
            <RegistersAs />
        </SheetLayout>
    );
}
