/**
 * Shared `{ value, label }` option shape used by Select fields, Comboboxes,
 * schema builders, and service settings descriptors.
 */
export interface LabeledOption {
	label: string;
	value: string;
}
