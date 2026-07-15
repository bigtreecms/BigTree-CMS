import { inputClass } from "@/components/ui/TextInput";
import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Shared props for every field component. The renderer owns the value state
 * and passes a controlled value/onChange pair down. `error` is the
 * server-side error message for this column (if any) and is rendered by the
 * wrapping FieldRow.
 */
export interface FieldComponentProps {
	disabled?: boolean;
	error?: string;
	field: ModuleFormField;
	onChange: (next: unknown) => void;
	value: unknown;
}

/**
 * Normalize the legacy "either-dict-or-empty-array" settings shape into a
 * plain object so subcomponents can read keys safely.
 */
export const settingsOf = (field: ModuleFormField): Record<string, unknown> => {
	const raw = field.settings;

	if (!raw || Array.isArray(raw)) {
		return {};
	}

	return raw as Record<string, unknown>;
};

/**
 * Standard Tailwind class for text-style inputs across all field components.
 * Re-exported from the canonical {@link inputClass} primitive so the renderer
 * engine and the rest of the admin can't drift apart.
 */
export const INPUT_CLASS = inputClass;
