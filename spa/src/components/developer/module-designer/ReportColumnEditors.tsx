import { Plus, Trash } from "lucide-react";

import type { DbOption } from "@/api/endpoints/db";
import type { ModuleReportFilter, ModuleReportFilterType } from "@/api/endpoints/modules";

import { useDragReorder } from "@/hooks/useDragReorder";
import { useListEditor } from "@/hooks/useListEditor";
import { DragHandle } from "@/components/ui/DragHandle";
import { Select } from "@/components/ui/Select";
import { TextInput } from "@/components/ui/TextInput";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { Loading } from "@/components/ui/Loading";
import { IconButton } from "@/components/ui/IconButton";
import { MonoText } from "@/components/ui/MonoText";
import { SectionLabel } from "@/components/ui/SectionLabel";

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
	rest: Omit<ModuleReportFilter, "title" | "type">;
	title: string;
	type: ModuleReportFilterType;
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
	columns: DbOption[];
	label: string;
	onAdd: (column: DbOption) => void;
	usedColumns: string[];
}

/** Dropdown that offers the table columns not yet present in a list. */
const AddColumnPicker = ({ label, columns, usedColumns, onAdd }: AddColumnPickerProps) => {
	const unused = columns.filter((c) => !usedColumns.includes(c.value));

	if (unused.length === 0) {
		return null;
	}

	return (
		<div className="mt-2 inline-flex items-center gap-1.5">
			<Plus className="text-text-3" size={13} />
			<Select
				compact
				value=""
				onChange={(e) => {
					const column = unused.find((c) => c.value === e.target.value);

					if (column) {
						onAdd(column);
					}
				}}
			>
				<option value="">{label}</option>
				{unused.map((c) => (
					<option key={c.value} value={c.value}>
						{humanizeColumn(c.value)}
					</option>
				))}
			</Select>
		</div>
	);
};

const rowClasses = (isDragging: boolean, isDropTarget: boolean) =>
	`flex items-center gap-2 rounded-md border border-border bg-surface px-2 py-1.5 transition-colors ${
		isDragging ? "bg-accent-soft shadow-md" : ""
	} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""}`;

interface DeleteButtonProps {
	onClick: () => void;
}

const DeleteButton = ({ onClick }: DeleteButtonProps) => (
	<IconButton label="Remove" title="Remove" tone="danger" onClick={onClick}>
		<Trash size={13} />
	</IconButton>
);

const ColumnTag = ({ column }: { column: string }) => (
	<MonoText className="w-32 shrink-0" title={column}>
		{column}
	</MonoText>
);

interface ReportFiltersEditorProps {
	columns: DbOption[];
	loading?: boolean;
	onChange: (rows: FilterRow[]) => void;
	rows: FilterRow[];
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

	const { update: setRow, remove: removeRow, add } = useListEditor<FilterRow>(rows, onChange);

	const addColumn = (column: DbOption) =>
		add({
			column: column.value,
			title: humanizeColumn(column.value),
			type: defaultFilterType(column),
			rest: {},
		});

	return (
		<div>
			<SectionLabel className="mb-2">Report Filters</SectionLabel>
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
								className={rowClasses(isDragging, isDropTarget)}
								key={`${row.column}-${index}`}
								onDragOver={(e) => drag.onDragOver(e, index)}
								onDrop={drag.onDrop}
							>
								<DragHandle
									draggable
									onDragEnd={drag.onDragEnd}
									onDragStart={(e) => drag.onDragStart(e, index)}
								/>
								<ColumnTag column={row.column} />
								<TextInput
									compact
									aria-label="Filter label"
									className="min-w-0 flex-1"
									placeholder="Filter label"
									value={row.title}
									onChange={(e) => setRow(index, { title: e.target.value })}
								/>
								<Select
									compact
									className="w-44 shrink-0"
									value={row.type}
									onChange={(e) =>
										setRow(index, {
											type: e.target.value as ModuleReportFilterType,
										})
									}
								>
									{FILTER_TYPE_OPTIONS.map((o) => (
										<option key={o.value} value={o.value}>
											{o.label}
										</option>
									))}
								</Select>
								<DeleteButton onClick={() => removeRow(index)} />
							</li>
						);
					})}
				</ul>
			)}
			{loading ? (
				<Loading className="mt-2" label="Loading columns…" />
			) : (
				<AddColumnPicker
					columns={columns}
					label="Add filter column…"
					usedColumns={rows.map((r) => r.column)}
					onAdd={addColumn}
				/>
			)}
		</div>
	);
};

interface ReportFieldsEditorProps {
	columns: DbOption[];
	loading?: boolean;
	onChange: (rows: FieldRow[]) => void;
	rows: FieldRow[];
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

	const { update: setRow, remove: removeRow, add } = useListEditor<FieldRow>(rows, onChange);

	const addColumn = (column: DbOption) =>
		add({ column: column.value, title: humanizeColumn(column.value) });

	return (
		<div>
			<SectionLabel className="mb-2">Fields to Include in CSV</SectionLabel>
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
								className={rowClasses(isDragging, isDropTarget)}
								key={`${row.column}-${index}`}
								onDragOver={(e) => drag.onDragOver(e, index)}
								onDrop={drag.onDrop}
							>
								<DragHandle
									draggable
									onDragEnd={drag.onDragEnd}
									onDragStart={(e) => drag.onDragStart(e, index)}
								/>
								<ColumnTag column={row.column} />
								<TextInput
									compact
									aria-label="CSV heading"
									className="min-w-0 flex-1"
									placeholder="CSV heading"
									value={row.title}
									onChange={(e) => setRow(index, { title: e.target.value })}
								/>
								<DeleteButton onClick={() => removeRow(index)} />
							</li>
						);
					})}
				</ul>
			)}
			{loading ? (
				<Loading className="mt-2" label="Loading columns…" />
			) : (
				<AddColumnPicker
					columns={columns}
					label="Add CSV column…"
					usedColumns={rows.map((r) => r.column)}
					onAdd={addColumn}
				/>
			)}
		</div>
	);
};
