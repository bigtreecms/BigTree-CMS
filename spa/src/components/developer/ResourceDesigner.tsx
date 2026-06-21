import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, GripVertical, Plus, Trash } from "lucide-react";

import { Button } from "@/components/ui/Button";
import type { ModuleFormField } from "@/api/endpoints/modules";
import {
	fieldTypesApi,
	fieldTypesForUseCase,
	fieldTypeName,
	type FieldUseCase,
} from "@/api/endpoints/field-types";
import { dbApi } from "@/api/endpoints/db";
import { useDragReorder } from "@/hooks/useDragReorder";
import { Combobox } from "@/components/ui/Combobox";

import { FieldSettingsEditor } from "./FieldSettingsEditor";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

/**
 * Shared editor for an array of `{column|id, type, title, subtitle, settings}`
 * field definitions. Used by:
 *   - Template designer       (use_case "templates", key = `id`)
 *   - Callout designer        (use_case "callouts",  key = `id`)
 *   - Feed designer           (use_case "feeds",     key = `id`)
 *   - eventual Module form    (use_case "modules",   key = `column`)
 *
 * The persisted JSON shape uses `id` everywhere except module forms which use
 * `column`. The designer accepts a `keyField` prop so each caller can write
 * the legacy field name without an in-component normalization layer.
 *
 * Per-field settings are edited by `FieldSettingsEditor`, a schema-driven UI
 * that reads each field type's settings schema (GET /field-types/{id}/schema)
 * and renders typed controls — the SPA equivalent of the legacy field-type
 * settings.php forms. It falls back to a raw-JSON editor for custom/extension
 * types that ship no schema.
 */

export type ResourceShape = "id" | "column";

export interface ResourceEntry {
	type: string;
	title: string;
	subtitle?: string;
	settings?: Record<string, unknown> | unknown[];
	/** Either `id` (templates / callouts / feeds) or `column` (module forms). */
	[k: string]: unknown;
}

interface ResourceDesignerProps {
	resources: ResourceEntry[];
	onChange: (next: ResourceEntry[]) => void;
	/** Which key on each entry holds the field id. */
	keyField: ResourceShape;
	/** Filter the field-type catalog to types whose `use_cases` include this slug. */
	useCase: FieldUseCase;
	/**
	 * When set (and `keyField` is "column"), each field's ID becomes a searchable
	 * select of this data table's columns instead of a free-text input. Used by
	 * the module / embeddable form designers, whose fields map to real columns.
	 */
	columnsTable?: string;
	/** Optional `display_field` callback so the host can render an inline "Use as title" pill. */
	displayFieldId?: string;
	onSetDisplayField?: (id: string) => void;
	/**
	 * Required field-setting errors keyed by entry index, then descriptor id
	 * (from `useResourceSettingsValidation`). Entries with errors auto-expand so
	 * the inline messages are visible; passed through to each field's
	 * `FieldSettingsEditor`.
	 */
	settingsErrors?: Record<number, Record<string, string>>;
}

export const ResourceDesigner = ({
	resources,
	onChange,
	keyField,
	useCase,
	columnsTable,
	displayFieldId,
	onSetDisplayField,
	settingsErrors,
}: ResourceDesignerProps) => {
	const [expanded, setExpanded] = useState<Set<number>>(new Set());

	// Surface validation failures: open any field whose settings just failed a
	// required check so its inline error isn't hidden in a collapsed panel.
	useEffect(() => {
		if (!settingsErrors) {
			return;
		}

		const failing = Object.keys(settingsErrors).map(Number);

		if (failing.length === 0) {
			return;
		}

		setExpanded((prev) => {
			const next = new Set(prev);
			failing.forEach((index) => next.add(index));

			return next;
		});
	}, [settingsErrors]);

	const fieldTypesQ = useQuery({
		queryKey: ["field-types", "list"],
		queryFn: () => fieldTypesApi.list(),
	});

	// When bound to a real table, fields must map to columns: pick the ID from a
	// searchable list and block adding fields until a table is chosen.
	const columnBound = keyField === "column";
	const useColumnSelect = columnBound && !!columnsTable;
	const addDisabled = columnBound && !columnsTable;

	const columnsQ = useQuery({
		queryKey: ["db", "columns", columnsTable],
		queryFn: () => dbApi.columns(columnsTable as string),
		enabled: useColumnSelect,
		staleTime: 5 * 60 * 1000,
	});

	const columnOptions = useMemo(
		() => (columnsQ.data ?? []).map((c) => ({ value: c.value, label: c.value })),
		[columnsQ.data]
	);

	// Field types split into the legacy "Default" / "Custom" optgroups. Names
	// only — the legacy admin doesn't surface type ids in this picker.
	const typeGroups = useMemo(() => {
		const all = fieldTypesForUseCase(fieldTypesQ.data, useCase);

		return [
			{
				label: "Default",
				options: all
					.filter((t) => t.group === "default")
					.map((t) => ({ value: t.id, label: t.name })),
			},
			{
				label: "Custom",
				options: all
					.filter((t) => t.group === "custom")
					.map((t) => ({ value: t.id, label: t.name })),
			},
		].filter((g) => g.options.length > 0);
	}, [fieldTypesQ.data, useCase]);

	const updateEntry = (index: number, patch: Partial<ResourceEntry>) => {
		const next = resources.map((r, i) => (i === index ? { ...r, ...patch } : r));
		onChange(next);
	};

	const addEntry = () => {
		const fresh: ResourceEntry = {
			[keyField]: "",
			type: "text",
			title: "",
			subtitle: "",
			settings: {},
		};
		const next = [...resources, fresh];
		onChange(next);
		setExpanded((prev) => new Set(prev).add(next.length - 1));
	};

	const removeEntry = (index: number) => {
		onChange(resources.filter((_, i) => i !== index));
		setExpanded((prev) => {
			const copy = new Set<number>();

			for (const i of prev) {
				if (i < index) {
					copy.add(i);
				} else if (i > index) {
					copy.add(i - 1);
				}
			}

			return copy;
		});
	};

	/**
	 * Reorder fields by drag. The list has no stable per-row id (a new field's
	 * key starts empty), so we drag on array index: each row is identified by
	 * its position and `reorder` receives the new order of original indices.
	 * We remap the `expanded` set through the same permutation so open panels
	 * follow their field to its new slot.
	 */
	const reorder = (orderedIndices: number[]) => {
		onChange(orderedIndices.map((i) => resources[i]!));

		setExpanded((prev) => {
			const next = new Set<number>();

			orderedIndices.forEach((oldIndex, newIndex) => {
				if (prev.has(oldIndex)) {
					next.add(newIndex);
				}
			});

			return next;
		});
	};

	const drag = useDragReorder<{ id: number }, number>(
		resources.map((_, i) => ({ id: i })),
		() => {},
		reorder
	);

	const toggle = (index: number) => {
		setExpanded((prev) => {
			const copy = new Set(prev);

			if (copy.has(index)) {
				copy.delete(index);
			} else {
				copy.add(index);
			}

			return copy;
		});
	};

	return (
		<div className="space-y-2">
			{resources.length === 0 ? (
				<InlineEmpty align="center">
					No fields configured yet. Click <strong>Add field</strong> below to start.
				</InlineEmpty>
			) : (
				<ul className="space-y-1.5">
					{resources.map((entry, index) => {
						const id = String(entry[keyField] ?? "");
						const isOpen = expanded.has(index);
						const isDisplay = displayFieldId && id && displayFieldId === id;

						const isDragging = drag.dragId === index;
						const isDropTarget = drag.overId === index && drag.dragId !== index;

						return (
							<li
								key={index}
								className={`rounded-md border border-border bg-surface transition-colors ${
									isDragging ? "bg-accent-soft shadow-md" : ""
								} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""}`}
								onDragOver={(e) => drag.onDragOver(e, index)}
								onDrop={drag.onDrop}
							>
								<div
									className="flex items-center gap-2 px-2 py-1.5"
									draggable
									onDragStart={(e) => drag.onDragStart(e, index)}
									onDragEnd={drag.onDragEnd}
								>
									<span
										className="grid size-6 shrink-0 cursor-grab place-items-center rounded text-text-4 hover:bg-hover hover:text-text-2 active:cursor-grabbing"
										title="Drag to reorder"
										aria-hidden="true"
									>
										<GripVertical size={14} />
									</span>

									<button
										type="button"
										className="flex min-w-0 flex-1 items-center gap-2 rounded px-1.5 py-1 text-left hover:bg-hover"
										onClick={() => toggle(index)}
										aria-expanded={isOpen}
									>
										{isOpen ? (
											<ChevronDown size={13} className="text-text-3" />
										) : (
											<ChevronRight size={13} className="text-text-3" />
										)}
										<span className="truncate text-[12.5px] text-text-2">
											{entry.title || id || `Field ${index + 1}`}
										</span>
										<span className="rounded bg-surface-2 px-1.5 py-0.5 text-[10.5px] text-text-3">
											{fieldTypeName(
												fieldTypesQ.data,
												useCase,
												entry.type || "text"
											)}
										</span>
										{isDisplay && (
											<span className="rounded bg-accent-soft px-1.5 py-0.5 text-[10.5px] font-medium text-accent">
												Title
											</span>
										)}
									</button>

									<button
										type="button"
										className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
										onClick={() => removeEntry(index)}
										title="Delete field"
										aria-label="Delete field"
									>
										<Trash size={13} />
									</button>
								</div>

								{isOpen && (
									<div className="border-t border-border p-3">
										<div className="grid grid-cols-1 gap-3 md:grid-cols-2">
											{useColumnSelect ? (
												<LabelledCombobox
													label="Column"
													value={id}
													onChange={(v) =>
														updateEntry(index, { [keyField]: v })
													}
													options={columnOptions}
													loading={columnsQ.isLoading}
													placeholder="Select a column…"
													searchPlaceholder="Search columns…"
													emptyLabel="No columns found."
													hint="The data table column this field reads and writes."
												/>
											) : (
												<LabelledInput
													label="ID"
													value={id}
													onChange={(v) =>
														updateEntry(index, { [keyField]: v })
													}
													hint={
														keyField === "column"
															? "Database column name (no spaces, lowercase)"
															: "Field key (used in storage). Stable across edits."
													}
												/>
											)}
											<LabelledSelect
												label="Type"
												value={entry.type || "text"}
												onChange={(v) => updateEntry(index, { type: v })}
												groups={typeGroups}
												loading={fieldTypesQ.isLoading}
											/>
											<LabelledInput
												label="Label"
												value={entry.title ?? ""}
												onChange={(v) => updateEntry(index, { title: v })}
											/>
											<LabelledInput
												label="Subtitle / hint"
												value={entry.subtitle ?? ""}
												onChange={(v) =>
													updateEntry(index, { subtitle: v })
												}
												hint="Shown in parens next to the label."
											/>
										</div>

										<label className="mt-3 flex items-center gap-2 text-[12px] text-text-2">
											<input
												type="checkbox"
												className="size-4 accent-accent"
												checked={readRequired(entry.settings)}
												onChange={(e) =>
													updateEntry(index, {
														settings: writeRequired(
															entry.settings,
															e.target.checked
														),
													})
												}
											/>
											Required field
										</label>

										<div className="mt-3">
											<span className="mb-1 block text-[12px] font-medium text-text-2">
												Field settings
											</span>
											<div className="rounded-md border border-border bg-surface-2 p-3">
												<FieldSettingsEditor
													hideLabel
													type={entry.type || "text"}
													useCase={useCase}
													value={
														entry.settings as
															| Record<string, unknown>
															| undefined
													}
													onChange={(v) =>
														updateEntry(index, { settings: v })
													}
													errors={settingsErrors?.[index]}
												/>
											</div>
										</div>

										{onSetDisplayField && (
											<label className="mt-2 flex items-center gap-2 text-[12px] text-text-2">
												<input
													type="checkbox"
													className="size-4 accent-accent"
													checked={!!isDisplay}
													onChange={() => onSetDisplayField(id)}
													disabled={!id}
												/>
												Use this field's value as the row title
											</label>
										)}
									</div>
								)}
							</li>
						);
					})}
				</ul>
			)}

			<div className="flex items-center gap-2">
				<Button
					variant="secondary"
					icon={<Plus size={13} />}
					onClick={addEntry}
					disabled={addDisabled}
				>
					Add field
				</Button>
				{addDisabled && (
					<span className="text-[11.5px] text-text-3">
						Choose a data table before adding fields.
					</span>
				)}
			</div>
		</div>
	);
};

interface LabelledInputProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	hint?: string;
}

const LabelledInput = ({ label, value, onChange, hint }: LabelledInputProps) => (
	<label className="block">
		<span className="mb-1 block text-[11.5px] font-medium text-text-2">{label}</span>
		<input
			type="text"
			className="w-full rounded-md border border-border bg-surface px-3 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
			value={value}
			onChange={(e) => onChange(e.target.value)}
		/>
		{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
	</label>
);

interface SelectOption {
	value: string;
	label: string;
}

interface SelectGroup {
	label: string;
	options: SelectOption[];
}

interface LabelledSelectProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	/** Grouped options rendered as <optgroup>s (e.g. Default / Custom). */
	groups: SelectGroup[];
	loading?: boolean;
}

const LabelledSelect = ({ label, value, onChange, groups, loading }: LabelledSelectProps) => {
	const known = groups.some((g) => g.options.some((o) => o.value === value));

	return (
		<label className="block">
			<span className="mb-1 block text-[11.5px] font-medium text-text-2">{label}</span>
			<select
				className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:opacity-50"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				disabled={loading}
			>
				{loading && <option value={value}>Loading field types…</option>}
				{!loading && !known && <option value={value}>{value}</option>}
				{groups.map((group) => (
					<optgroup key={group.label} label={group.label}>
						{group.options.map((o) => (
							<option key={o.value} value={o.value}>
								{o.label}
							</option>
						))}
					</optgroup>
				))}
			</select>
		</label>
	);
};

interface LabelledComboboxProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	options: Array<{ value: string; label: string }>;
	loading?: boolean;
	hint?: string;
	placeholder?: string;
	searchPlaceholder?: string;
	emptyLabel?: string;
}

const LabelledCombobox = ({
	label,
	value,
	onChange,
	options,
	loading,
	hint,
	placeholder,
	searchPlaceholder,
	emptyLabel,
}: LabelledComboboxProps) => {
	const selected = value ? { value, label: value } : null;

	return (
		<label className="block">
			<span className="mb-1 block text-[11.5px] font-medium text-text-2">{label}</span>
			<Combobox<string>
				value={selected}
				onChange={(option) => onChange(option ? option.value : "")}
				options={options}
				isLoading={loading}
				placeholder={placeholder}
				searchPlaceholder={searchPlaceholder}
				emptyLabel={emptyLabel}
				ariaLabel={label}
			/>
			{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
		</label>
	);
};

/**
 * The "required" flag lives as a token inside the field's space-separated
 * `settings.validation` string (e.g. "required email"), mirroring legacy
 * BigTree. These helpers read and toggle just the `required` token while
 * preserving any other rules (email / numeric / link) the developer set.
 */
const validationTokens = (settings: ResourceEntry["settings"]): string[] => {
	if (!settings || Array.isArray(settings)) {
		return [];
	}

	const raw = (settings as Record<string, unknown>).validation;

	return typeof raw === "string" ? raw.split(/\s+/).filter(Boolean) : [];
};

const readRequired = (settings: ResourceEntry["settings"]): boolean =>
	validationTokens(settings).includes("required");

const writeRequired = (
	settings: ResourceEntry["settings"],
	required: boolean
): Record<string, unknown> => {
	const base: Record<string, unknown> =
		settings && !Array.isArray(settings) ? { ...(settings as Record<string, unknown>) } : {};

	const tokens = validationTokens(settings).filter((token) => token !== "required");

	if (required) {
		tokens.unshift("required");
	}

	const validation = tokens.join(" ");

	if (validation) {
		base.validation = validation;
	} else {
		delete base.validation;
	}

	return base;
};

/**
 * Convenience adapter: surface a ResourceEntry[] from a ModuleFormField[]
 * with no field-type changes — just rename `column` ↔ `id`. Used by callers
 * that already work with the ModuleFormField shape internally.
 */
export const toModuleFormFields = (resources: ResourceEntry[]): ModuleFormField[] =>
	resources.map((r) => ({
		column: String(r.column ?? r.id ?? ""),
		title: String(r.title ?? ""),
		subtitle: r.subtitle ? String(r.subtitle) : undefined,
		type: String(r.type ?? "text"),
		settings: (r.settings ?? {}) as Record<string, unknown>,
	}));
