import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { DisclosureToggle } from "@/components/ui/DisclosureToggle";
import { Select } from "@/components/ui/Select";
import { TextInput } from "@/components/ui/TextInput";
import type { ModuleFormField } from "@/api/endpoints/modules";
import {
	fieldTypesApi,
	fieldTypesForUseCase,
	fieldTypeName,
	type FieldUseCase,
} from "@/api/endpoints/field-types";
import { useDbColumns } from "@/hooks/useDbColumns";
import { useDragReorder } from "@/hooks/useDragReorder";
import { useListEditor } from "@/hooks/useListEditor";
import { Combobox } from "@/components/ui/Combobox";

import { FieldSettingsEditor } from "./FieldSettingsEditor";
import { queryKeys } from "@/lib/queryKeys";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import type { LabeledOption } from "@/types/labeled-option";
import { Field, FieldLabel } from "@/components/ui/Field";
import { DragHandle } from "@/components/ui/DragHandle";

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
	/** Either `id` (templates / callouts / feeds) or `column` (module forms). */
	[k: string]: unknown;
	settings?: Record<string, unknown> | unknown[];
	subtitle?: string;
	title: string;
	type: string;
}

interface ResourceDesignerProps {
	/**
	 * When set (and `keyField` is "column"), each field's ID becomes a searchable
	 * select of this data table's columns instead of a free-text input. Used by
	 * the module / embeddable form designers, whose fields map to real columns.
	 */
	columnsTable?: string;
	/** Optional `display_field` callback so the host can render an inline "Use as title" pill. */
	displayFieldId?: string;
	/** Which key on each entry holds the field id. */
	keyField: ResourceShape;
	onChange: (next: ResourceEntry[]) => void;
	onSetDisplayField?: (id: string) => void;
	resources: ResourceEntry[];
	/**
	 * Required field-setting errors keyed by entry index, then descriptor id
	 * (from `useResourceSettingsValidation`). Entries with errors auto-expand so
	 * the inline messages are visible; passed through to each field's
	 * `FieldSettingsEditor`.
	 */
	settingsErrors?: Record<number, Record<string, string>>;
	/** Filter the field-type catalog to types whose `use_cases` include this slug. */
	useCase: FieldUseCase;
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
		queryKey: queryKeys.fieldTypes.list(),
		queryFn: () => fieldTypesApi.list(),
	});

	// When bound to a real table, fields must map to columns: pick the ID from a
	// searchable list and block adding fields until a table is chosen.
	const columnBound = keyField === "column";
	const useColumnSelect = columnBound && !!columnsTable;
	const addDisabled = columnBound && !columnsTable;

	const columnsQ = useDbColumns(columnsTable as string, { enabled: useColumnSelect });

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

	// add/remove also remap the `expanded` set, so only `update` comes from the
	// shared editor; the rest stay bespoke.
	const { update: updateEntry } = useListEditor<ResourceEntry>(resources, onChange);

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
								className={`rounded-md border border-border bg-surface transition-colors ${
									isDragging ? "bg-accent-soft shadow-md" : ""
								} ${isDropTarget ? "shadow-[inset_0_2px_0_0_var(--color-accent)]" : ""}`}
								key={index}
								onDragOver={(e) => drag.onDragOver(e, index)}
								onDrop={drag.onDrop}
							>
								<div
									draggable
									className="flex items-center gap-2 px-2 py-1.5"
									onDragEnd={drag.onDragEnd}
									onDragStart={(e) => drag.onDragStart(e, index)}
								>
									<DragHandle />

									<DisclosureToggle
										className="min-w-0 flex-1 gap-2 rounded px-1.5 py-1 hover:bg-hover"
										label={
											<span className="truncate text-[12.5px] text-text-2">
												{entry.title || id || `Field ${index + 1}`}
											</span>
										}
										open={isOpen}
										onToggle={() => toggle(index)}
									>
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
									</DisclosureToggle>

									<IconButton
										label="Delete field"
										title="Delete field"
										tone="danger"
										onClick={() => removeEntry(index)}
									>
										<Trash size={13} />
									</IconButton>
								</div>

								{isOpen && (
									<div className="border-t border-border p-3">
										<div className="grid grid-cols-1 gap-3 md:grid-cols-2">
											{useColumnSelect ? (
												<LabelledCombobox
													emptyLabel="No columns found."
													hint="The data table column this field reads and writes."
													label="Column"
													loading={columnsQ.isLoading}
													options={columnOptions}
													placeholder="Select a column…"
													searchPlaceholder="Search columns…"
													value={id}
													onChange={(v) =>
														updateEntry(index, { [keyField]: v })
													}
												/>
											) : (
												<LabelledInput
													hint={
														keyField === "column"
															? "Database column name (no spaces, lowercase)"
															: "Field key (used in storage). Stable across edits."
													}
													label="ID"
													value={id}
													onChange={(v) =>
														updateEntry(index, { [keyField]: v })
													}
												/>
											)}
											<LabelledSelect
												groups={typeGroups}
												label="Type"
												loading={fieldTypesQ.isLoading}
												value={entry.type || "text"}
												onChange={(v) => updateEntry(index, { type: v })}
											/>
											<LabelledInput
												label="Label"
												value={entry.title ?? ""}
												onChange={(v) => updateEntry(index, { title: v })}
											/>
											<LabelledInput
												hint="Shown in parens next to the label."
												label="Subtitle / hint"
												value={entry.subtitle ?? ""}
												onChange={(v) =>
													updateEntry(index, { subtitle: v })
												}
											/>
										</div>

										<Checkbox
											checked={readRequired(entry.settings)}
											className="mt-3"
											label="Required field"
											onChange={(next) =>
												updateEntry(index, {
													settings: writeRequired(entry.settings, next),
												})
											}
										/>

										<div className="mt-3">
											<FieldLabel>Field settings</FieldLabel>
											<div className="rounded-md border border-border bg-surface-2 p-3">
												<FieldSettingsEditor
													hideLabel
													errors={settingsErrors?.[index]}
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
												/>
											</div>
										</div>

										{onSetDisplayField && (
											<Checkbox
												checked={!!isDisplay}
												className="mt-2"
												disabled={!id}
												label="Use this field's value as the row title"
												onChange={() => onSetDisplayField(id)}
											/>
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
					disabled={addDisabled}
					icon={<Plus size={13} />}
					variant="secondary"
					onClick={addEntry}
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
	hint?: string;
	label: string;
	onChange: (next: string) => void;
	value: string;
}

const LabelledInput = ({ label, value, onChange, hint }: LabelledInputProps) => (
	<Field hint={hint} label={label} size="sm">
		<TextInput dense value={value} onChange={(e) => onChange(e.target.value)} />
	</Field>
);

interface SelectOption {
	label: string;
	value: string;
}

interface SelectGroup {
	label: string;
	options: SelectOption[];
}

interface LabelledSelectProps {
	/** Grouped options rendered as <optgroup>s (e.g. Default / Custom). */
	groups: SelectGroup[];
	label: string;
	loading?: boolean;
	onChange: (next: string) => void;
	value: string;
}

const LabelledSelect = ({ label, value, onChange, groups, loading }: LabelledSelectProps) => {
	const known = groups.some((g) => g.options.some((o) => o.value === value));

	return (
		<Field label={label} size="sm">
			<Select
				dense
				disabled={loading}
				value={value}
				onChange={(e) => onChange(e.target.value)}
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
			</Select>
		</Field>
	);
};

interface LabelledComboboxProps {
	emptyLabel?: string;
	hint?: string;
	label: string;
	loading?: boolean;
	onChange: (next: string) => void;
	options: LabeledOption[];
	placeholder?: string;
	searchPlaceholder?: string;
	value: string;
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
		<Field hint={hint} label={label} size="sm">
			<Combobox<string>
				ariaLabel={label}
				emptyLabel={emptyLabel}
				isLoading={loading}
				options={options}
				placeholder={placeholder}
				searchPlaceholder={searchPlaceholder}
				value={selected}
				onChange={(option) => onChange(option ? option.value : "")}
			/>
		</Field>
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
