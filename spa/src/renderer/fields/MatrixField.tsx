import { useEffect, useId, useMemo, useState } from "react";
import { ChevronDown, ChevronRight, GripVertical, Plus, Trash } from "lucide-react";

import type { ModuleFormField } from "@/api/endpoints/modules";

import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { FieldRow } from "@/renderer/forms/FieldRow";

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

interface MatrixRow {
	uid: string;
	data: RowData;
}

const TITLE_KEY = "__internal-title";
const SUBTITLE_KEY = "__internal-subtitle";

let uidCounter = 0;
const nextUid = (): string => `m${++uidCounter}-${Date.now().toString(36)}`;

const isRecord = (raw: unknown): raw is RowData =>
	Boolean(raw) && typeof raw === "object" && !Array.isArray(raw);

const seedRows = (raw: unknown): MatrixRow[] => {
	if (!Array.isArray(raw)) {
		return [];
	}

	return raw.filter(isRecord).map((data) => ({ uid: nextUid(), data: { ...data } }));
};

const stripUid = (rows: MatrixRow[]): RowData[] => rows.map((r) => r.data);

/**
 * Structural equality of two row sets by their data payloads (ignoring the
 * ephemeral uids), so we can tell our own edit's echo from an external change.
 */
const rowsDataEqual = (a: MatrixRow[], b: MatrixRow[]): boolean => {
	if (a.length !== b.length) {
		return false;
	}

	for (let i = 0; i < a.length; i++) {
		if (JSON.stringify(a[i]?.data) !== JSON.stringify(b[i]?.data)) {
			return false;
		}
	}

	return true;
};

const normalizeColumnSettings = (raw: unknown): Record<string, unknown> => {
	if (typeof raw === "string" && raw.trim().length > 0) {
		try {
			const parsed = JSON.parse(raw);

			return isRecord(parsed) ? parsed : {};
		} catch {
			return {};
		}
	}

	if (isRecord(raw)) {
		return raw;
	}

	return {};
};

const toInt = (raw: unknown): number => {
	const n = typeof raw === "number" ? raw : Number(raw);

	return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0;
};

const isTruthyFlag = (raw: unknown): boolean => {
	if (typeof raw === "boolean") {
		return raw;
	}

	if (typeof raw === "number") {
		return raw !== 0;
	}

	if (typeof raw === "string") {
		const lower = raw.toLowerCase();

		return lower !== "" && lower !== "0" && lower !== "false" && lower !== "off";
	}

	return false;
};

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

const stringifyForTitle = (raw: unknown): string => {
	if (typeof raw === "string") {
		return raw;
	}

	if (typeof raw === "number" || typeof raw === "boolean") {
		return String(raw);
	}

	if (isRecord(raw)) {
		const candidate =
			(typeof raw.title === "string" && raw.title) ||
			(typeof raw.name === "string" && raw.name) ||
			(typeof raw.id === "string" && raw.id);

		return candidate || "";
	}

	return "";
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

	const [rows, setRows] = useState<MatrixRow[]>(() => seedRows(value));
	const [expanded, setExpanded] = useState<Set<string>>(new Set());

	// Re-seed only when the incoming value genuinely differs from the rows we
	// already hold (edit-page mount / record switch / reset). The echo of our own
	// onChange — and any unrelated re-render passing a structurally-equal value —
	// is ignored, so row uids (and the expand state keyed on them) survive.
	useEffect(() => {
		setRows((prev) => {
			const incoming = seedRows(value);

			return rowsDataEqual(prev, incoming) ? prev : incoming;
		});
	}, [value]);

	const commit = (next: MatrixRow[]) => {
		setRows(next);
		onChange(stripUid(next));
	};

	const atLimit = max > 0 && rows.length >= max;

	const addRow = () => {
		if (disabled || atLimit) {
			return;
		}

		const fresh: MatrixRow = { uid: nextUid(), data: {} };
		commit([...rows, fresh]);
		setExpanded((prev) => new Set(prev).add(fresh.uid));
	};

	const deleteRow = (uid: string) => {
		commit(rows.filter((r) => r.uid !== uid));
		setExpanded((prev) => {
			const copy = new Set(prev);
			copy.delete(uid);

			return copy;
		});
	};

	const updateCell = (uid: string, columnId: string, next: unknown) => {
		commit(
			rows.map((r) => (r.uid === uid ? { ...r, data: { ...r.data, [columnId]: next } } : r))
		);
	};

	const toggleExpanded = (uid: string) => {
		setExpanded((prev) => {
			const copy = new Set(prev);

			if (copy.has(uid)) {
				copy.delete(uid);
			} else {
				copy.add(uid);
			}

			return copy;
		});
	};

	if (columns.length === 0) {
		return (
			<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-3 text-[12px] text-text-3">
				This matrix field has no columns configured.
			</div>
		);
	}

	return (
		<div className="space-y-2" data-matrix-style={style}>
			{rows.length === 0 ? (
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-4 text-center text-[12.5px] text-text-3">
					No items yet. Click <strong>Add item</strong> to create one.
				</div>
			) : (
				<ul className="space-y-1.5">
					{rows.map((row, index) => (
						<MatrixRowItem
							key={row.uid}
							row={row}
							index={index}
							columns={columns}
							expanded={expanded.has(row.uid)}
							onToggle={() => toggleExpanded(row.uid)}
							onDelete={() => deleteRow(row.uid)}
							onCellChange={(columnId, next) => updateCell(row.uid, columnId, next)}
							onMove={(direction) => {
								const swapWith = direction === "up" ? index - 1 : index + 1;

								if (swapWith < 0 || swapWith >= rows.length) {
									return;
								}

								const next = [...rows];
								const removed = next.splice(index, 1)[0];

								if (removed) {
									next.splice(swapWith, 0, removed);
									commit(next);
								}
							}}
							style={style}
							disabled={disabled}
							totalRows={rows.length}
							idPrefix={reactId}
						/>
					))}
				</ul>
			)}

			<div className="flex items-center justify-between gap-2">
				<button
					type="button"
					className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
					onClick={addRow}
					disabled={disabled || atLimit}
				>
					<Plus size={13} />
					Add item
				</button>
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
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-text disabled:cursor-not-allowed disabled:opacity-40"
					onClick={() => onMove("up")}
					disabled={disabled || index === 0}
					title="Move up"
					aria-label="Move up"
				>
					<GripVertical size={13} />
				</button>

				<button
					type="button"
					className="flex min-w-0 flex-1 items-center gap-2 rounded px-1.5 py-1 text-left hover:bg-hover"
					onClick={onToggle}
					aria-expanded={expanded}
					aria-controls={`${idPrefix}-row-${row.uid}`}
				>
					{expanded ? (
						<ChevronDown size={13} className="text-text-3" />
					) : (
						<ChevronRight size={13} className="text-text-3" />
					)}
					<span className="truncate text-[12.5px] text-text-2">{titleText}</span>
					{summary.subtitle && (
						<span className="truncate text-[11.5px] text-text-3">
							{summary.subtitle}
						</span>
					)}
				</button>

				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger disabled:opacity-40"
					onClick={onDelete}
					disabled={disabled}
					title="Delete item"
					aria-label="Delete item"
				>
					<Trash size={13} />
				</button>
			</div>

			{expanded && (
				<div
					id={`${idPrefix}-row-${row.uid}`}
					className="border-t border-border px-3 pb-1 pt-3"
				>
					{columns.map((column) => {
						const subField: ModuleFormField = {
							column: column.id,
							title: column.title || column.id,
							subtitle: column.subtitle,
							type: column.type,
							settings: normalizeColumnSettings(column.settings),
						};

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
						<button
							type="button"
							className="mb-2 inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2 py-1 text-[11.5px] text-text-3 hover:bg-hover disabled:opacity-40"
							onClick={() => onMove("down")}
							disabled={disabled}
						>
							Move down
						</button>
					)}
				</div>
			)}
		</li>
	);
};
