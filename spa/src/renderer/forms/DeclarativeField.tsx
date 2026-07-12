import type { InputDescriptor } from "@/api/endpoints/field-types";
import type { ModuleFormField } from "@/api/endpoints/modules";
import type { FieldComponentProps } from "@/renderer/fields/types";

import { RepeaterColumnFields } from "@/renderer/fields/RepeaterColumnFields";

interface DeclarativeFieldProps extends FieldComponentProps {
	inputSchema: InputDescriptor[];
}

/** Coerce the composite value to an object so sub-field reads/writes are safe. */
const asObject = (value: unknown): Record<string, unknown> =>
	value && typeof value === "object" && !Array.isArray(value)
		? (value as Record<string, unknown>)
		: {};

/**
 * Merge a `required` flag into a sub-field's `settings.validation` string so the
 * shared FieldRow / validation helpers treat it as required — the same
 * space-separated convention the rest of the form runtime parses.
 */
const settingsFor = (descriptor: InputDescriptor): Record<string, unknown> => {
	const settings = { ...(descriptor.settings ?? {}) };

	if (descriptor.required) {
		const rules =
			typeof settings.validation === "string" ? settings.validation.split(/\s+/) : [];

		if (!rules.includes("required")) {
			rules.push("required");
		}

		settings.validation = rules.filter(Boolean).join(" ");
	}

	return settings;
};

/**
 * Renders a `declarative` custom field type: an ordered list of primitive
 * sub-fields (from the type's `input_schema`) composed into a single object
 * value keyed by each descriptor's `id`. Each sub-field reuses the built-in
 * field components through FieldRenderer, so declarative types inherit every
 * primitive (and can even nest other custom types) for free.
 *
 * See spa/.custom-field-types-design.md (Tier 1).
 */
export const DeclarativeField = ({
	inputSchema,
	value,
	onChange,
	disabled,
}: DeclarativeFieldProps) => {
	const obj = asObject(value);

	const fields: ModuleFormField[] = inputSchema.map((descriptor) => ({
		column: descriptor.id,
		type: descriptor.type,
		title: descriptor.title ?? descriptor.id,
		subtitle: descriptor.subtitle,
		settings: settingsFor(descriptor),
	}));

	return (
		<div className="space-y-1 rounded-md border border-border bg-surface-2 p-3">
			<RepeaterColumnFields
				fields={fields}
				getValue={(columnId) => obj[columnId]}
				onColumnChange={(columnId, next) => onChange({ ...obj, [columnId]: next })}
				disabled={disabled}
			/>
		</div>
	);
};
