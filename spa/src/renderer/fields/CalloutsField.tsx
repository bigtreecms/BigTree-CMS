import { useEffect, useId, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus } from "lucide-react";

import { useAuthStore } from "@/auth/store";

import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { LoadingText } from "@/components/ui/LoadingText";
import { Select } from "@/components/ui/Select";
import { useRepeaterRows, type RepeaterRow } from "@/hooks/useRepeaterRows";
import { calloutsApi, type CalloutSummary } from "@/api/endpoints/callouts";
import { queryKeys } from "@/lib/queryKeys";
import { resourceToFormField } from "@/api/endpoints/templates";

import { stringifyForTitle, toInt } from "./fieldHelpers";
import { RepeaterColumnFields } from "./RepeaterColumnFields";
import { RepeaterRowShell } from "./RepeaterRowShell";
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

type CalloutRow = RepeaterRow<RowData>;

const DISPLAY_TITLE_KEY = "display_title";
const TYPE_KEY = "type";

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

export const CalloutsField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as CalloutsFieldSettings;
	const noun =
		typeof settings.noun === "string" && settings.noun.length > 0 ? settings.noun : "Callout";
	const max = toInt(settings.max);
	const groupIds = normalizeGroups(settings);
	const reactId = useId();

	const userLevel = useAuthStore((state) => state.user?.level ?? 0);

	const calloutsQuery = useQuery({
		queryKey: queryKeys.callouts.list(),
		queryFn: () => calloutsApi.list(),
	});

	const groupsQuery = useQuery({
		queryKey: queryKeys.calloutGroups.list(),
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

	const [pendingType, setPendingType] = useState<string>("");

	const { rows, isExpanded, toggleExpanded, atLimit, add, remove, move, update } =
		useRepeaterRows<RowData>({ value, onChange, uidPrefix: "c", max });

	// Pre-select the first available type once the catalog loads so the Add
	// button isn't disabled-for-no-reason on first paint.
	useEffect(() => {
		if (!pendingType && availableTypes.length > 0 && availableTypes[0]) {
			setPendingType(availableTypes[0].id);
		}
	}, [availableTypes, pendingType]);

	const addRow = () => {
		if (disabled || !pendingType) {
			return;
		}

		add({ [TYPE_KEY]: pendingType });
	};

	const updateCell = (uid: string, columnId: string, next: unknown) => {
		update(uid, { [columnId]: next });
	};

	if (calloutsQuery.isLoading) {
		return <LoadingText boxed label="Loading callouts…" />;
	}

	return (
		<div className="space-y-2">
			{rows.length === 0 ? (
				<EmptyState size="sm" dashed>
					No items yet. Pick a type below and click <strong>Add {noun}</strong>.
				</EmptyState>
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
							expanded={isExpanded(row.uid)}
							onToggle={() => toggleExpanded(row.uid)}
							onDelete={() => remove(row.uid)}
							onCellChange={(columnId, next) => updateCell(row.uid, columnId, next)}
							onMove={(dir) => move(index, dir)}
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

	const displayTitle = stringifyForTitle(row.data[DISPLAY_TITLE_KEY]);
	const typeName = callout?.name ?? `Unknown type (${row.data[TYPE_KEY] ?? "—"})`;

	return (
		<RepeaterRowShell
			index={index}
			total={totalRows}
			expanded={expanded}
			onToggle={onToggle}
			onMove={onMove}
			onDelete={onDelete}
			disabled={disabled}
			panelId={`${idPrefix}-row-${row.uid}`}
			headerDisabled={typeMissing}
			title={displayTitle || typeName}
			subtitle={displayTitle ? typeName : undefined}
			trailing={
				<>
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
				</>
			}
		>
			{callout &&
				(callout.resources.length === 0 ? (
					<EmptyState size="sm" dashed>
						This callout type has no fields configured.
					</EmptyState>
				) : (
					// Callout resources are keyed by `id` (like template resources),
					// so adapt via resourceToFormField. Using `resource.id` directly
					// is essential: every field shares `row.data[undefined]` otherwise
					// (one field's edits leak into all of them).
					<RepeaterColumnFields
						fields={callout.resources.map(resourceToFormField)}
						getValue={(columnId) => row.data[columnId]}
						onColumnChange={onCellChange}
						disabled={rowDisabled}
					/>
				))}
		</RepeaterRowShell>
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
			<EmptyState size="sm" dashed>
				No {noun.toLowerCase()} types available for your access level.
			</EmptyState>
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

				<Button
					variant="secondary"
					icon={<Plus size={13} />}
					onClick={onAdd}
					disabled={disabled || !value}
				>
					Add {noun}
				</Button>
			</div>

			{max > 0 && (
				<span className="text-[11.5px] text-text-3 tabular-nums">
					{currentCount} / {max}
				</span>
			)}
		</div>
	);
};
