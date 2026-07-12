import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { FieldRow } from "@/renderer/forms/FieldRow";

import { columnToFormField, type RepeaterColumn } from "./fieldHelpers";

interface RepeaterColumnFieldsProps {
	columns: RepeaterColumn[];
	/** Read a column's stored cell value (Matrix reads `row.data`, MediaGallery `data.info`). */
	getValue: (columnId: string) => unknown;
	onColumnChange: (columnId: string, next: unknown) => void;
	disabled?: boolean;
}

/**
 * The shared `columns.map → FieldRow/FieldRenderer` panel body used by
 * `MatrixField` and `MediaGalleryField`, which both render a repeater row's
 * sub-fields from a `RepeaterColumn` list via {@link columnToFormField}. The
 * only per-field difference is where a cell value lives, threaded via `getValue`.
 */
export const RepeaterColumnFields = ({
	columns,
	getValue,
	onColumnChange,
	disabled,
}: RepeaterColumnFieldsProps) => (
	<>
		{columns.map((column) => {
			const subField = columnToFormField(column);

			return (
				<FieldRow key={column.id} field={subField}>
					<FieldRenderer
						field={subField}
						value={getValue(column.id)}
						onChange={(next) => onColumnChange(column.id, next)}
						disabled={disabled}
					/>
				</FieldRow>
			);
		})}
	</>
);
