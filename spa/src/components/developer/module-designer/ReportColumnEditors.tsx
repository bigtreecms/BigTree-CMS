import { GripVertical, Plus, Trash } from "lucide-react";

import type { DbOption } from "@/api/endpoints/db";
import type { ModuleReportFilter, ModuleReportFilterType } from "@/api/endpoints/modules";

import { useDragReorder } from "@/hooks/useDragReorder";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

/**
 * Column-driven editors for a module report's Filters and Fields, replacing the
 * raw JSON textareas with the pick-from-table UI the legacy admin's
 * load-report.php builds. Each row is tied to a real column of the report's
 * data table; reordering controls the stored key order (which drives filter
 * display order and CSV column order), and a picker offers the columns not yet
 * in use.
 */

/** A filter row in the editor; `rest` round-trips any non-edited filter config. */
export interface FilterRow {
	column: string;
	title: string;
	type: ModuleReportFilterType;
	rest: Omit<ModuleReportFilter, "title" | "type">;
}

/** A CSV field row: a column mapped to a CSV heading. */
export interface FieldRow {
	column: string;
	title: string;
}

const FILTER_TYPE_OPTIONS: Array<{ value: ModuleReportFilterType; label: string }> = [
	{ value: "search", label: "Simple Search" },
	{ value: "dropdown", label: "Dropdown Select" },
	{ value: "boolean", label: "Yes/No/Both Select" },
	{ value: "date-range", label: "Date Range" },
];

/**
 * Humanize a column name into a default heading — mirrors the legacy
 * ucwords(str_replace) with the URL/PDF/SQL acronym fix-ups.
 */
export const humanizeColumn = (name: string): string => {
	const title = name.replace(/[-_]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());

	return title.replace(/Url/g, "URL").replace(/Pdf/g, "PDF").replace(/Sql/g, "SQL");
};

/** Default filter type for a column, matching the legacy heuristics. */
export const defaultFilterType = (column: DbOption): ModuleReportFilterType => {
	const type = column.type ?? "";

	if (type === "date" || type === "datetime" || type === "timestamp") {
		return "date-range";
	}

	if (column.value === "approved" || column.value === "archived" || column.value === "featured") {
		return "boolean";
	}

	return "search";
};

interface AddColumnPickerProps {
	label: string;
	columns: DbOption[];
	usedColumns: string[];
	onAdd: (column: DbOption) => void;
}

/** Dropdown that offers the table columns not yet present in a list. */
const AddColumnPicker = ({ label, columns, usedColumns, onAdd }: AddColumnPickerProps) => {
	const unused = columns.filter((c) => !usedColumns.includes(c.value));

	if (unused.length === 0) {
		return null;
	}

	return (
		<div className="mt-2 inline-flex items-center gap-1.5">
			<Plus size={13} className="text-text-3" />
			<select
				value=""
				onChange={(e) => {
					const column = unused.find((c) => c.value === e.target.value);

					if (column) {
						onAdd(column);
					}
				}}
				className="rounded-md border border-border bg-surface px-2.5 py-1.5 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
			>
				<option value="">{label}</option>
				{unused.map((c) => (
					<option key={c.value} value={c.value}>
						{humanizeColumn(c.value)}
					</option>
				))}
			</select>
		</div>
	);
};

const rowClasses = (isDragging: boolean, isDropTarget: boolean) =>
	`flex items-center gap-2 rounded-md border border-border bg-surface px-2 py-1.5 transition-colors ${
		isDragging ? "bg-accent-soft shadow-md" : ""
	} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""}`;

interface DragHandleProps {
	onDragStart: (e: React.DragEvent) => void;
	onDragEnd: () => void;
}

const DragHandle = ({ onDragStart, onDragEnd }: DragHandleProps) => (
	<span
		className="grid size-6 shrink-0 cursor-grab place-items-center rounded text-text-4 hover:bg-hover hover:text-text-2 active:cursor-grabbing"
		title="Drag to reorder"
		draggable
		onDragStart={onDragStart}
		onDragEnd={onDragEnd}
	>
		<GripVertical size={14} />
	</span>
);

interface DeleteButtonProps {
	onClick: () => void;
}

const DeleteButton = ({ onClick }: DeleteButtonProps) => (
	<button
		type="button"
		className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
		onClick={onClick}
		title="Remove"
		aria-label="Remove"
	>
		<Trash size={13} />
	</button>
);

const ColumnTag = ({ column }: { column: string }) => (
	<span className="w-32 shrink-0 truncate font-mono text-[11px] text-text-3" title={column}>
		{column}
	</span>
);

interface ReportFiltersEditorProps {
	rows: FilterRow[];
	onChange: (rows: FilterRow[]) => void;
	columns: DbOption[];
	loading?: boolean;
}

export const ReportFiltersEditor = ({
	rows,
	onChange,
	columns,
	loading,
}: ReportFiltersEditorProps) => {
	const drag = useDragReorder<{ id: number }, number>(
		rows.map((_, i) => ({ id: i })),
		() => {},
		(orderedIds) => onChange(orderedIds.map((i) => rows[i]!))
	);

	const setRow = (index: number, patch: Partial<FilterRow>) =>
		onChange(rows.map((r, i) => (i === index ? { ...r, ...patch } : r)));

	const removeRow = (index: number) => onChange(rows.filter((_, i) => i !== index));

	const addColumn = (column: DbOption) =>
		onChange([
			...rows,
			{
				column: column.value,
				title: humanizeColumn(column.value),
				type: defaultFilterType(column),
				rest: {},
			},
		]);

	return (
		<div>
			<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
				Report Filters
			</div>
			{rows.length === 0 ? (
				<InlineEmpty align="center">
					No filters. Add columns below to let users filter the report.
				</InlineEmpty>
			) : (
				<ul className="space-y-1.5">
					{rows.map((row, index) => {
						const isDragging = drag.dragId === index;
						const isDropTarget = drag.overId === index && drag.dragId !== index;

						return (
							<li
								key={`${row.column}-${index}`}
								className={rowClasses(isDragging, isDropTarget)}
								onDragOver={(e) => drag.onDragOver(e, index)}
								onDrop={drag.onDrop}
							>
								<DragHandle
									onDragStart={(e) => drag.onDragStart(e, index)}
									onDragEnd={drag.onDragEnd}
								/>
								<ColumnTag column={row.column} />
								<input
									type="text"
									value={row.title}
									onChange={(e) => setRow(index, { title: e.target.value })}
									placeholder="Filter label"
									className="min-w-0 flex-1 rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								/>
								<select
									value={row.type}
									onChange={(e) =>
										setRow(index, {
											type: e.target.value as ModuleReportFilterType,
										})
									}
									className="w-44 shrink-0 rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								>
									{FILTER_TYPE_OPTIONS.map((o) => (
										<option key={o.value} value={o.value}>
											{o.label}
										</option>
									))}
								</select>
								<DeleteButton onClick={() => removeRow(index)} />
							</li>
						);
					})}
				</ul>
			)}
			{loading ? (
				<p className="mt-2 text-[11.5px] text-text-3">Loading columns…</p>
			) : (
				<AddColumnPicker
					label="Add filter column…"
					columns={columns}
					usedColumns={rows.map((r) => r.column)}
					onAdd={addColumn}
				/>
			)}
		</div>
	);
};

interface ReportFieldsEditorProps {
	rows: FieldRow[];
	onChange: (rows: FieldRow[]) => void;
	columns: DbOption[];
	loading?: boolean;
}

export const ReportFieldsEditor = ({
	rows,
	onChange,
	columns,
	loading,
}: ReportFieldsEditorProps) => {
	const drag = useDragReorder<{ id: number }, number>(
		rows.map((_, i) => ({ id: i })),
		() => {},
		(orderedIds) => onChange(orderedIds.map((i) => rows[i]!))
	);

	const setRow = (index: number, patch: Partial<FieldRow>) =>
		onChange(rows.map((r, i) => (i === index ? { ...r, ...patch } : r)));

	const removeRow = (index: number) => onChange(rows.filter((_, i) => i !== index));

	const addColumn = (column: DbOption) =>
		onChange([...rows, { column: column.value, title: humanizeColumn(column.value) }]);

	return (
		<div>
			<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
				Fields to Include in CSV
			</div>
			{rows.length === 0 ? (
				<InlineEmpty align="center">
					No fields. Add columns below to include them in the CSV export.
				</InlineEmpty>
			) : (
				<ul className="space-y-1.5">
					{rows.map((row, index) => {
						const isDragging = drag.dragId === index;
						const isDropTarget = drag.overId === index && drag.dragId !== index;

						return (
							<li
								key={`${row.column}-${index}`}
								className={rowClasses(isDragging, isDropTarget)}
								onDragOver={(e) => drag.onDragOver(e, index)}
								onDrop={drag.onDrop}
							>
								<DragHandle
									onDragStart={(e) => drag.onDragStart(e, index)}
									onDragEnd={drag.onDragEnd}
								/>
								<ColumnTag column={row.column} />
								<input
									type="text"
									value={row.title}
									onChange={(e) => setRow(index, { title: e.target.value })}
									placeholder="CSV heading"
									className="min-w-0 flex-1 rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								/>
								<DeleteButton onClick={() => removeRow(index)} />
							</li>
						);
					})}
				</ul>
			)}
			{loading ? (
				<p className="mt-2 text-[11.5px] text-text-3">Loading columns…</p>
			) : (
				<AddColumnPicker
					label="Add CSV column…"
					columns={columns}
					usedColumns={rows.map((r) => r.column)}
					onAdd={addColumn}
				/>
			)}
		</div>
	);
};
