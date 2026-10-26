import { useT } from '../../lib/i18n';
import ChoiceChips from './Chips';

/** Cambiar de ml a oz solo para este registro (la unidad de la familia se elige en Ajustes). */
export default function UnitSwitch({ value, onChange }) {
    const t = useT();

    return <ChoiceChips label={t('registrar.unit')} value={value} onChange={onChange} options={[{ value: 'ml', label: 'ml' }, { value: 'oz', label: 'oz' }]} />;
}
