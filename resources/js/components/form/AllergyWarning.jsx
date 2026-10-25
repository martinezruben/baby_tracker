import { ShieldAlert } from 'lucide-react';
import { allergyHit } from '../../lib/forms';
import { useT } from '../../lib/i18n';

/** Aviso (no bloquea) cuando lo que se va a registrar coincide con una alergia conocida del bebé. */
export default function AllergyWarning({ allergies, name, babyName }) {
    const t = useT();
    const hit = allergyHit(allergies ?? [], name);
    if (!hit) return null;

    return (
        <div role="alert" className="flex items-start gap-3 rounded-[20px] border border-danger-line bg-danger-soft p-4 text-danger-ink">
            <ShieldAlert aria-hidden="true" className="mt-0.5 size-6 shrink-0" strokeWidth={1.75} />
            <p className="text-base font-semibold">{t('alergias.warning', { name: babyName, allergy: hit })}</p>
        </div>
    );
}
