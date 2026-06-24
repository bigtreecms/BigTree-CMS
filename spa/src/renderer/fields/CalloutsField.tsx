import { useEffect, useId, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, GripVertical, Plus, Trash } from "lucide-react";

import { useAuthStore } from "@/auth/store";

import { IconButton } from "@/components/ui/IconButton";
import { Select } from "@/components/ui/Select";
import { calloutsApi, type CalloutSummary } from "@/api/endpoints/callouts";
import { resourceToFormField } from "@/api/endpoints/templates";

import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { FieldRow } from "@/renderer/forms/FieldRow";

import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Callouts — repeating field group where each row's schema is determined by
 * its `type` (a callout id from the system catalog). Modelled on
 * `core/admin/field-types/callouts/draw.php` + `process.php`.
 *
 *   value = [
 *     { type: "callout-id", display_title: "...", ...resource values },
 *     ...
 *   ]
 *
 * Settings:
 *   - noun   string  what to call each row in the UI (defaults to "Callout")
 *   - max    numeric upper bound on the row count
 *   - groups string[] when set, restricts the type picker to callouts that
 *                    belong to one of these group ids. Otherwise: all
 *                    callouts the user has access to.
 *
 * Per-callout `level` gates editing — rows whose type requires a higher user
 * level than the current user render as read-only with a note.
 */

interface CalloutsFieldSettings {
	noun?: string;
	max?: number | string;
	groups?: string[] | string;
	/** Legacy 4.1-and-older single-group key — still occasionally in stored configs. */
	group?: string;
}

type RowData = Record<string, unknown>;

interface CalloutRow {
	uid: string;
	data: RowData;
}

const DISPLAY_TITLE_KEY = "display_title";
const TYPE_KEY = "type";

let uidCounter = 0;
const nextUid = (): string => `c${++uidCounter}-${Date.now().toString(36)}`;

const isRecord = (raw: unknown): raw is RowData =>
	Boolean(raw) && typeof raw === "object" && !Array.isArray(raw);

const seedRows = (raw: unknown): CalloutRow[] => {
	if (!Array.isArray(raw)) {
		return [];
	}

	return raw.filter(isRecord).map((data) => ({ uid: nextUid(), data: { ...data } }));
};

const stripUid = (rows: CalloutRow[]): RowData[] => rows.map((r) => r.data);

/**
 * Structural equality of two row sets by their data payloads (ignoring the
 * ephemeral uids). Used to decide whether an incoming `value` is a real change
 * or just the echo of our own edit, so we don't needlessly regenerate uids.
 */
const rowsDataEqual = (a: CalloutRow[], b: CalloutRow[]): boolean => {
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

const toInt = (raw: unknown): number => {
	const n = typeof raw === "number" ? raw : Number(raw);

	return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0;
};

const normalizeGroups = (settings: CalloutsFieldSettings): string[] => {
	const groups: string[] = [];

	if (Array.isArray(settings.groups)) {
		for (const g of settings.groups) {
			if (typeof g === "string" && g.length > 0) {
				groups.push(g);
			}
		}
	} else if (typeof settings.groups === "string" && settings.groups.length > 0) {
		groups.push(settings.groups);
	}

	if (
		typeof settings.group === "string" &&
		settings.group.length > 0 &&
		!groups.includes(settings.group)
	) {
		groups.push(settings.group);
	}

	return groups;
};

const CALLOUTS_KEY = ["callouts", "list"] as const;
const CALLOUT_GROUPS_KEY = ["callout-groups", "list"] as const;

export const CalloutsField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as CalloutsFieldSettings;
	const noun =
		typeof settings.noun === "string" && settings.noun.length > 0 ? settings.noun : "Callout";
	const max = toInt(settings.max);
	const groupIds = normalizeGroups(settings);
	const reactId = useId();

	const userLevel = useAuthStore((state) => state.user?.level ?? 0);

	const calloutsQuery = useQuery({
		queryKey: CALLOUTS_KEY,
		queryFn: () => calloutsApi.list(),
	});

	const groupsQuery = useQuery({
		queryKey: CALLOUT_GROUPS_KEY,
		queryFn: () => calloutsApi.listGroups(),
		enabled: groupIds.length > 0,
	});

	const calloutsById = useMemo(() => {
		const map = new Map<string, CalloutSummary>();

		for (const c of calloutsQuery.data ?? []) {
			map.set(c.id, c);
		}

		return map;
	}, [calloutsQuery.data]);

	const allowedIds = useMemo<Set<string> | null>(() => {
		if (groupIds.length === 0) {
			return null;
		}

		const groups = groupsQuery.data ?? [];
		const allowed = new Set<string>();

		for (const group of groups) {
			if (!groupIds.includes(group.id)) {
				continue;
			}

			for (const calloutId of group.callouts ?? []) {
				allowed.add(calloutId);
			}
		}

		return allowed;
	}, [groupIds, groupsQuery.data]);

	const availableTypes = useMemo<CalloutSummary[]>(() => {
		const list = calloutsQuery.data ?? [];

		return list
			.filter((c) => c.level <= userLevel)
			.filter((c) => (allowedIds ? allowedIds.has(c.id) : true));
	}, [calloutsQuery.data, allowedIds, userLevel]);

	const [rows, setRows] = useState<CalloutRow[]>(() => seedRows(value));
	const [expanded, setExpanded] = useState<Set<string>>(new Set());
	const [pendingType, setPendingType] = useState<string>("");

	// Re-seed from the incoming value only when it genuinely differs from the
	// rows we already hold. The echo of our own onChange (and any unrelated
	// re-render that passes a structurally-equal value) is ignored, so row uids
	// — and the expand/collapse state keyed on them — survive. Only an external
	// change (e.g. switching records) regenerates the rows.
	useEffect(() => {
		setRows((prev) => {
			const incoming = seedRows(value);

			return rowsDataEqual(prev, incoming) ? prev : incoming;
		});
	}, [value]);

	// Pre-select the first available type once the catalog loads so the Add
	// button isn't disabled-for-no-reason on first paint.
	useEffect(() => {
		if (!pendingType && availableTypes.length > 0 && availableTypes[0]) {
			setPendingType(availableTypes[0].id);
		}
	}, [availableTypes, pendingType]);

	const commit = (next: CalloutRow[]) => {
		setRows(next);
		onChange(stripUid(next));
	};

	const atLimit = max > 0 && rows.length >= max;

	const addRow = () => {
		if (disabled || atLimit || !pendingType) {
			return;
		}

		const fresh: CalloutRow = { uid: nextUid(), data: { [TYPE_KEY]: pendingType } };
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

	const moveRow = (index: number, direction: "up" | "down") => {
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
	};

	if (calloutsQuery.isLoading) {
		return (
			<div className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
				Loading callouts…
			</div>
		);
	}

	return (
		<div className="space-y-2">
			{rows.length === 0 ? (
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-4 text-center text-[12.5px] text-text-3">
					No items yet. Pick a type below and click <strong>Add {noun}</strong>.
				</div>
			) : (
				<ul className="space-y-1.5">
					{rows.map((row, index) => (
						<CalloutRowItem
							key={row.uid}
							row={row}
							index={index}
							totalRows={rows.length}
							callout={calloutsById.get(String(row.data[TYPE_KEY] ?? "")) ?? null}
							userLevel={userLevel}
							expanded={expanded.has(row.uid)}
							onToggle={() => toggleExpanded(row.uid)}
							onDelete={() => deleteRow(row.uid)}
							onCellChange={(columnId, next) => updateCell(row.uid, columnId, next)}
							onMove={(dir) => moveRow(index, dir)}
							disabled={disabled}
							idPrefix={reactId}
						/>
					))}
				</ul>
			)}

			<AddRow
				noun={noun}
				availableTypes={availableTypes}
				value={pendingType}
				onChange={setPendingType}
				onAdd={addRow}
				disabled={disabled || atLimit}
				max={max}
				currentCount={rows.length}
			/>
		</div>
	);
};

interface CalloutRowItemProps {
	row: CalloutRow;
	index: number;
	totalRows: number;
	callout: CalloutSummary | null;
	userLevel: number;
	expanded: boolean;
	onToggle: () => void;
	onDelete: () => void;
	onCellChange: (columnId: string, next: unknown) => void;
	onMove: (direction: "up" | "down") => void;
	disabled?: boolean;
	idPrefix: string;
}

const CalloutRowItem = ({
	row,
	index,
	totalRows,
	callout,
	userLevel,
	expanded,
	onToggle,
	onDelete,
	onCellChange,
	onMove,
	disabled,
	idPrefix,
}: CalloutRowItemProps) => {
	const tooLowLevel = callout ? callout.level > userLevel : false;
	const typeMissing = !callout;
	const rowDisabled = disabled || tooLowLevel;

	const displayTitle = stringifyTitle(row.data[DISPLAY_TITLE_KEY]);
	const typeName = callout?.name ?? `Unknown type (${row.data[TYPE_KEY] ?? "—"})`;

	return (
		<li className="rounded-md border border-border bg-surface">
			<div className="flex items-center gap-2 px-2 py-1.5">
				<IconButton
					label="Move up"
					title="Move up"
					onClick={() => onMove("up")}
					disabled={disabled || index === 0}
				>
					<GripVertical size={13} />
				</IconButton>

				<button
					type="button"
					className="flex min-w-0 flex-1 items-center gap-2 rounded px-1.5 py-1 text-left hover:bg-hover disabled:cursor-not-allowed disabled:opacity-60"
					onClick={onToggle}
					disabled={typeMissing}
					aria-expanded={expanded}
					aria-controls={`${idPrefix}-row-${row.uid}`}
				>
					{expanded ? (
						<ChevronDown size={13} className="text-text-3" />
					) : (
						<ChevronRight size={13} className="text-text-3" />
					)}
					<span className="truncate text-[12.5px] text-text-2">
						{displayTitle || typeName}
					</span>
					{displayTitle && (
						<span className="truncate text-[11.5px] text-text-3">{typeName}</span>
					)}
					{tooLowLevel && (
						<span className="ml-1 rounded bg-surface-2 px-1.5 py-0.5 text-[10.5px] font-medium uppercase tracking-wider text-text-3">
							Locked
						</span>
					)}
					{typeMissing && (
						<span className="ml-1 rounded bg-danger/10 px-1.5 py-0.5 text-[10.5px] font-medium uppercase tracking-wider text-danger">
							Missing type
						</span>
					)}
				</button>

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

			{expanded && callout && (
				<div
					id={`${idPrefix}-row-${row.uid}`}
					className="border-t border-border px-3 pb-1 pt-3"
				>
					{callout.resources.length === 0 ? (
						<div className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
							This callout type has no fields configured.
						</div>
					) : (
						callout.resources.map((resource) => {
							// Callout resources are keyed by `id` (like template
							// resources), so adapt to the `column`-keyed field shape
							// the renderer expects. Using `resource.id` directly is
							// essential: every field shares `row.data[undefined]`
							// otherwise (one field's edits leak into all of them).
							const formField = resourceToFormField(resource);

							return (
								<FieldRow key={formField.column} field={formField}>
									<FieldRenderer
										field={formField}
										value={row.data[formField.column]}
										onChange={(next) => onCellChange(formField.column, next)}
										disabled={rowDisabled}
									/>
								</FieldRow>
							);
						})
					)}

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

interface AddRowProps {
	noun: string;
	availableTypes: CalloutSummary[];
	value: string;
	onChange: (next: string) => void;
	onAdd: () => void;
	disabled: boolean;
	max: number;
	currentCount: number;
}

const AddRow = ({
	noun,
	availableTypes,
	value,
	onChange,
	onAdd,
	disabled,
	max,
	currentCount,
}: AddRowProps) => {
	if (availableTypes.length === 0) {
		return (
			<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-2 text-[12px] text-text-3">
				No {noun.toLowerCase()} types available for your access level.
			</div>
		);
	}

	return (
		<div className="flex flex-wrap items-center justify-between gap-2">
			<div className="flex flex-wrap items-center gap-2">
				<Select
					compact
					value={value}
					onChange={(e) => onChange(e.target.value)}
					disabled={disabled}
				>
					{availableTypes.map((type) => (
						<option key={type.id} value={type.id}>
							{type.name}
						</option>
					))}
				</Select>

				<button
					type="button"
					className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
					onClick={onAdd}
					disabled={disabled || !value}
				>
					<Plus size={13} />
					Add {noun}
				</button>
			</div>

			{max > 0 && (
				<span className="text-[11.5px] text-text-3 tabular-nums">
					{currentCount} / {max}
				</span>
			)}
		</div>
	);
};

const stringifyTitle = (raw: unknown): string => {
	if (typeof raw === "string") {
		return raw;
	}

	if (typeof raw === "number" || typeof raw === "boolean") {
		return String(raw);
	}

	return "";
};
