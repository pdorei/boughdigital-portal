import { labelize } from '@/utils/text';

export function optionLabel(options = [], value) {
    return options.find((option) => option.value === value)?.label ?? labelize(value);
}
