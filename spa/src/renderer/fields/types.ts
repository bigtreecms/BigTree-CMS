import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Shared props for every field component. The renderer owns the value state
 * and passes a controlled value/onChange pair down. `error` is the
 * server-side error message for this column (if any) and is rendered by the
 * wrapping FieldRow.
 */
export interface FieldComponentProps {
	field: ModuleFormField;
	value: unknown;
	onChange: (next: unknown) => void;
	disabled?: boolean;
	error?: string;
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

/** Standard Tailwind class for text-style inputs across all field components. */
export const INPUT_CLASS =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60";
