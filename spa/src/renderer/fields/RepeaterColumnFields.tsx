import type { ModuleFormField } from "@/api/endpoints/modules";
import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { FieldRow } from "@/renderer/forms/FieldRow";

import { columnToFormField, type RepeaterColumn } from "./fieldHelpers";

interface RepeaterColumnFieldsProps {
	/**
	 * Pre-mapped form fields. When provided, wins over `columns` — use for
	 * Callouts (resourceToFormField) and Declarative (descriptor → ModuleFormField).
	 */
	fields?: ModuleFormField[];
	/** Raw repeater columns; mapped via {@link columnToFormField} when `fields` is absent. */
	columns?: RepeaterColumn[];
	/** Read a column/field's stored cell value. */
	getValue: (columnId: string) => unknown;
	onColumnChange: (columnId: string, next: unknown) => void;
	disabled?: boolean;
}

/**
 * Shared `fields.map → FieldRow/FieldRenderer` panel body used by Matrix,
 * MediaGallery, Callouts, and Declarative field types. Prefer `fields` when
 * the source shape is not a `RepeaterColumn` list.
 */
export const RepeaterColumnFields = ({
	fields,
	columns,
	getValue,
	onColumnChange,
	disabled,
}: RepeaterColumnFieldsProps) => {
	const resolved: ModuleFormField[] =
		fields ?? (columns ?? []).map((column) => columnToFormField(column));

	return (
		<>
			{resolved.map((subField) => (
				<FieldRow key={subField.column} field={subField}>
					<FieldRenderer
						field={subField}
						value={getValue(subField.column)}
						onChange={(next) => onColumnChange(subField.column, next)}
						disabled={disabled}
					/>
				</FieldRow>
			))}
		</>
	);
};
