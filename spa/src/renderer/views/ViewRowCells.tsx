import type { ModuleEntryRow } from "@/api/endpoints/auto-modules";

import { formatCellValue } from "./viewHelpers";

interface ViewRowCellsProps {
	/** Status "dim" class applied to the cell group (archived / not-approved rows). */
	dim?: string;
	/** `Object.entries(view.fields)` — only the entry order matters; values map to `column{n}`. */
	fieldColumns: [string, unknown][];
	row: ModuleEntryRow;
}

/**
 * The flat field-column cell block shared by the `grouped`, `nested`, and
 * `draggable` list views: a flex row of truncated `text-text-2` spans, the
 * first bolded, resolving positional `column{n}` keys in field-definition
 * order (see BigTreeAutoModule::getSearchResults).
 */
export const ViewRowCells = ({ fieldColumns, row, dim = "" }: ViewRowCellsProps) => (
	<div className={`flex min-w-0 flex-1 items-center gap-4 ${dim}`}>
		{fieldColumns.map(([key], index) => {
			const valueKey = `column${index + 1}`;
			const isFirst = index === 0;

			return (
				<span
					className={`truncate text-text-2 ${isFirst ? "font-medium text-text" : "flex-1"}`}
					key={key}
				>
					{formatCellValue(row[valueKey])}
				</span>
			);
		})}
	</div>
);
