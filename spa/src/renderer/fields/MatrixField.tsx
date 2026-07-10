import { useId, useMemo } from "react";
import { GripVertical, Plus, Trash } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { IconButton } from "@/components/ui/IconButton";
import { useRepeaterRows, type RepeaterRow } from "@/hooks/useRepeaterRows";

import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { FieldRow } from "@/renderer/forms/FieldRow";

import { CollapsibleRowHeader } from "./CollapsibleRowHeader";
import { columnToFormField, isTruthyFlag, stringifyForTitle, toInt } from "./fieldHelpers";
import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Matrix — a repeating group of sub-fields, modelled on
 * `core/admin/field-types/matrix/draw.php` + `process.php`.
 *
 *   value = [
 *     { col_id: value, ..., __internal-title: "...", __internal-subtitle: "..." },
 *     ...
 *   ]
 *
 * Sub-field rendering is delegated back to FieldRenderer, which makes the
 * matrix recursive — a matrix-inside-matrix is supported, the same way the
 * legacy admin allowed it. We carry an internal UI id per row (`__uid`) for
 * the drag-reorder hook + React keys; the id is stripped before the value
 * reaches `onChange` so the wire payload matches the legacy storage shape.
 *
 * Settings consumed:
 *   - max     numeric  upper bound on the row count
 *   - style   "list"|"callout"   visual variant
 *   - columns array     [{ id, title, subtitle, type, settings, display_title }]
 */

interface MatrixColumn {
	id: string;
	title: string;
	subtitle?: string;
	type: string;
	settings?: unknown;
	display_title?: boolean | string | number;
}

interface MatrixFieldSettings {
	max?: number | string;
	style?: "list" | "callout" | string;
	columns?: MatrixColumn[];
}

type RowData = Record<string, unknown>;

type MatrixRow = RepeaterRow<RowData>;

const TITLE_KEY = "__internal-title";
const SUBTITLE_KEY = "__internal-subtitle";

/**
 * Derive the display title + subtitle for a collapsed row. Any column flagged
 * `display_title` contributes — first contributor wins the title slot, second
 * wins the subtitle slot. Stored `__internal-title` / `__internal-subtitle`
 * keys (carried over from the legacy admin) act as a fallback so unchanged
 * rows keep showing whatever the server computed last.
 */
const deriveRowSummary = (
	row: RowData,
	columns: MatrixColumn[]
): { title: string; subtitle: string } => {
	let title = "";
	let subtitle = "";

	for (const col of columns) {
		if (!isTruthyFlag(col.display_title)) {
			continue;
		}

		const raw = row[col.id];

		if (raw == null || raw === "") {
			continue;
		}

		const text = stringifyForTitle(raw);

		if (!title) {
			title = text;
		} else if (!subtitle) {
			subtitle = text;
			break;
		}
	}

	if (!title && typeof row[TITLE_KEY] === "string") {
		title = String(row[TITLE_KEY]);
	}

	if (!subtitle && typeof row[SUBTITLE_KEY] === "string") {
		subtitle = String(row[SUBTITLE_KEY]);
	}

	return { title, subtitle };
};

export const MatrixField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as MatrixFieldSettings;
	const columns = useMemo<MatrixColumn[]>(
		() => (Array.isArray(settings.columns) ? settings.columns : []),
		[settings.columns]
	);
	const max = toInt(settings.max);
	const style = settings.style === "callout" ? "callout" : "list";
	const reactId = useId();

	const { rows, isExpanded, toggleExpanded, atLimit, add, remove, move, update } =
		useRepeaterRows<RowData>({ value, onChange, uidPrefix: "m", max });

	const addRow = () => {
		if (disabled) {
			return;
		}

		add({});
	};

	const updateCell = (uid: string, columnId: string, next: unknown) => {
		update(uid, { [columnId]: next });
	};

	if (columns.length === 0) {
		return (
			<EmptyState size="sm" dashed>
				This matrix field has no columns configured.
			</EmptyState>
		);
	}

	return (
		<div className="space-y-2" data-matrix-style={style}>
			{rows.length === 0 ? (
				<EmptyState size="sm" dashed>
					No items yet. Click <strong>Add item</strong> to create one.
				</EmptyState>
			) : (
				<ul className="space-y-1.5">
					{rows.map((row, index) => (
						<MatrixRowItem
							key={row.uid}
							row={row}
							index={index}
							columns={columns}
							expanded={isExpanded(row.uid)}
							onToggle={() => toggleExpanded(row.uid)}
							onDelete={() => remove(row.uid)}
							onCellChange={(columnId, next) => updateCell(row.uid, columnId, next)}
							onMove={(direction) => move(index, direction)}
							style={style}
							disabled={disabled}
							totalRows={rows.length}
							idPrefix={reactId}
						/>
					))}
				</ul>
			)}

			<div className="flex items-center justify-between gap-2">
				<Button
					variant="secondary"
					icon={<Plus size={13} />}
					onClick={addRow}
					disabled={disabled || atLimit}
				>
					Add item
				</Button>
				{max > 0 && (
					<span className="text-[11.5px] text-text-3 tabular-nums">
						{rows.length} / {max}
					</span>
				)}
			</div>
		</div>
	);
};

interface MatrixRowItemProps {
	row: MatrixRow;
	index: number;
	totalRows: number;
	columns: MatrixColumn[];
	expanded: boolean;
	onToggle: () => void;
	onDelete: () => void;
	onCellChange: (columnId: string, next: unknown) => void;
	onMove: (direction: "up" | "down") => void;
	style: "list" | "callout";
	disabled?: boolean;
	idPrefix: string;
}

const MatrixRowItem = ({
	row,
	index,
	totalRows,
	columns,
	expanded,
	onToggle,
	onDelete,
	onCellChange,
	onMove,
	style,
	disabled,
	idPrefix,
}: MatrixRowItemProps) => {
	const summary = deriveRowSummary(row.data, columns);
	const titleText = summary.title || `Item ${index + 1}`;

	const wrapperClass =
		style === "callout"
			? "rounded-md border border-border bg-surface-2"
			: "rounded-md border border-border bg-surface";

	return (
		<li className={wrapperClass}>
			<div className="flex items-center gap-2 px-2 py-1.5">
				<IconButton
					label="Move up"
					title="Move up"
					onClick={() => onMove("up")}
					disabled={disabled || index === 0}
				>
					<GripVertical size={13} />
				</IconButton>

				<CollapsibleRowHeader
					open={expanded}
					onToggle={onToggle}
					controls={`${idPrefix}-row-${row.uid}`}
					title={titleText}
					subtitle={summary.subtitle || undefined}
				/>

				<IconButton
					label="Delete item"
					title="Delete item"
					tone="danger"
					onClick={onDelete}
					disabled={disabled}
				>
					<Trash size={13} />
				</IconButton>
			</div>

			{expanded && (
				<div
					id={`${idPrefix}-row-${row.uid}`}
					className="border-t border-border px-3 pb-1 pt-3"
				>
					{columns.map((column) => {
						const subField = columnToFormField(column);

						return (
							<FieldRow key={column.id} field={subField}>
								<FieldRenderer
									field={subField}
									value={row.data[column.id]}
									onChange={(next) => onCellChange(column.id, next)}
									disabled={disabled}
								/>
							</FieldRow>
						);
					})}

					{index < totalRows - 1 && (
						<Button
							variant="secondary"
							size="sm"
							className="mb-2"
							onClick={() => onMove("down")}
							disabled={disabled}
						>
							Move down
						</Button>
					)}
				</div>
			)}
		</li>
	);
};
