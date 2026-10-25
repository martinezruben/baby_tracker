import { Bath } from 'lucide-react';
import ChoiceChips from '../../components/form/Chips';
import MoreDetails from '../../components/form/MoreDetails';
import RegistersAs from '../../components/form/RegistersAs';
import TimeBlock from '../../components/form/TimeBlock';
import Switch from '../../components/Switch';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Bano({ record, baby, clock, lastAt, last }) {
    const t = useT();
    const [form, save] = useActivityForm('bano', { occurred_at: clock.now, bath_type: last?.bath_type ?? 'full', soap_used: false, shampoo_used: false }, record);
    const { data, setData, errors } = form;

    return (
        <SheetLayout
            title={t('registrar.bath.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="growth"
            icon={Bath}
            actionLabel={t('registrar.bath.save')}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <ChoiceChips
                label={t('registrar.bath.type')}
                value={data.bath_type}
                onChange={(v) => setData('bath_type', v)}
                options={[{ value: 'full', label: t('registrar.bath.full') }, { value: 'sponge', label: t('registrar.bath.sponge') }]}
            />

            <MoreDetails hint={t('registrar.bath.details_hint')}>
                <Switch label={t('registrar.bath.soap')} checked={data.soap_used} onChange={(v) => setData('soap_used', v)} />
                <Switch label={t('registrar.bath.shampoo')} checked={data.shampoo_used} onChange={(v) => setData('shampoo_used', v)} />
            </MoreDetails>

            <RegistersAs />
        </SheetLayout>
    );
}
