import { StickyNote } from 'lucide-react';
import RegistersAs from '../../components/form/RegistersAs';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Nota({ record, baby, clock, lastAt }) {
    const t = useT();
    const [form, save] = useActivityForm('nota', { occurred_at: clock.now, content: '' }, record);
    const { data, setData, errors } = form;

    return (
        <SheetLayout
            title={t('registrar.note_form.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="moment"
            icon={StickyNote}
            actionLabel={t('registrar.note_form.save')}
            disabled={!data.content.trim()}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <div className="flex flex-col gap-2.5">
                <label htmlFor="note-content" className="text-[15px] font-semibold text-ink-soft">
                    {t('registrar.note_form.label')}
                </label>
                <textarea
                    id="note-content"
                    value={data.content}
                    onChange={(e) => setData('content', e.target.value)}
                    rows={5}
                    maxLength={1000}
                    aria-invalid={errors.content ? 'true' : undefined}
                    className="min-h-32 rounded-[20px] bg-canvas p-4 text-base focus-visible:outline-2 focus-visible:outline-primary"
                />
                {errors.content && (
                    <p role="alert" className="text-sm font-medium text-danger-ink">
                        {errors.content}
                    </p>
                )}
            </div>

            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <RegistersAs />
        </SheetLayout>
    );
}
