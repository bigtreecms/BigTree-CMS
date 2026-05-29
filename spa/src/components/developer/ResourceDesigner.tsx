import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, ChevronDown, ChevronRight, Plus, Trash } from "lucide-react";

import type { ModuleFormField } from "@/api/endpoints/modules";
import { fieldTypesApi, type FieldUseCase } from "@/api/endpoints/field-types";

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
 * Per-field settings are edited as raw JSON for V1 — running a full type-
 * specific settings editor here would duplicate the legacy field-type
 * settings.php files. JSON is honest about what's stored and works for every
 * field type without a per-type implementation.
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
	/** Optional `display_field` callback so the host can render an inline "Use as title" pill. */
	displayFieldId?: string;
	onSetDisplayField?: (id: string) => void;
}

export const ResourceDesigner = ({
	resources,
	onChange,
	keyField,
	useCase,
	displayFieldId,
	onSetDisplayField,
}: ResourceDesignerProps) => {
	const [expanded, setExpanded] = useState<Set<number>>(new Set());

	const fieldTypesQ = useQuery({
		queryKey: ["field-types", "list"],
		queryFn: () => fieldTypesApi.list(),
	});

	const types = useMemo(() => {
		const all = Object.values(fieldTypesQ.data ?? {});

		return all
			.filter((t) =>
				Array.isArray(t.use_cases) ? (t.use_cases as string[]).includes(useCase) : true
			)
			.sort((a, b) => (a.name ?? a.id ?? "").localeCompare(b.name ?? b.id ?? ""));
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

	const move = (index: number, direction: "up" | "down") => {
		const swap = direction === "up" ? index - 1 : index + 1;

		if (swap < 0 || swap >= resources.length) {
			return;
		}

		const next = [...resources];
		const removed = next.splice(index, 1)[0];

		if (removed) {
			next.splice(swap, 0, removed);
			onChange(next);
		}
	};

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
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-4 text-center text-[12.5px] text-text-3">
					No fields configured yet. Click <strong>Add field</strong> below to start.
				</div>
			) : (
				<ul className="space-y-1.5">
					{resources.map((entry, index) => {
						const id = String(entry[keyField] ?? "");
						const isOpen = expanded.has(index);
						const isDisplay = displayFieldId && id && displayFieldId === id;

						return (
							<li
								key={`${index}-${id}`}
								className="rounded-md border border-border bg-surface"
							>
								<div className="flex items-center gap-2 px-2 py-1.5">
									<div className="flex flex-col">
										<button
											type="button"
											className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text disabled:opacity-30"
											onClick={() => move(index, "up")}
											disabled={index === 0}
											aria-label="Move up"
										>
											<ArrowUp size={11} />
										</button>
										<button
											type="button"
											className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text disabled:opacity-30"
											onClick={() => move(index, "down")}
											disabled={index === resources.length - 1}
											aria-label="Move down"
										>
											<ArrowDown size={11} />
										</button>
									</div>

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
											{entry.type || "text"}
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
											<LabelledSelect
												label="Type"
												value={entry.type || "text"}
												onChange={(v) => updateEntry(index, { type: v })}
												options={types.map((t) => ({
													value: t.id,
													label: `${t.name} (${t.id})`,
												}))}
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

										<SettingsJsonEditor
											value={
												entry.settings as
													| Record<string, unknown>
													| undefined
											}
											onChange={(v) => updateEntry(index, { settings: v })}
										/>

										{onSetDisplayField && (
											<label className="mt-2 flex items-center gap-2 text-[12px] text-text-2">
												<input
													type="checkbox"
													className="h-4 w-4 accent-accent"
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

			<button
				type="button"
				className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
				onClick={addEntry}
			>
				<Plus size={13} />
				Add field
			</button>
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

interface LabelledSelectProps {
	label: string;
	value: string;
	onChange: (next: string) => void;
	options: Array<{ value: string; label: string }>;
	loading?: boolean;
}

const LabelledSelect = ({ label, value, onChange, options, loading }: LabelledSelectProps) => (
	<label className="block">
		<span className="mb-1 block text-[11.5px] font-medium text-text-2">{label}</span>
		<select
			className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:opacity-50"
			value={value}
			onChange={(e) => onChange(e.target.value)}
			disabled={loading}
		>
			{loading && <option value={value}>Loading field types…</option>}
			{!loading && options.find((o) => o.value === value) === undefined && (
				<option value={value}>{value}</option>
			)}
			{options.map((o) => (
				<option key={o.value} value={o.value}>
					{o.label}
				</option>
			))}
		</select>
	</label>
);

interface SettingsJsonEditorProps {
	value: Record<string, unknown> | unknown[] | undefined;
	onChange: (next: Record<string, unknown>) => void;
}

const SettingsJsonEditor = ({ value, onChange }: SettingsJsonEditorProps) => {
	const initial = useMemo(() => safeStringify(value), [value]);
	const [draft, setDraft] = useState(initial);
	const [error, setError] = useState<string | null>(null);

	// Reset draft when the parent value identity changes (e.g. row reorder).
	useMemo(() => {
		setDraft(initial);
		setError(null);
	}, [initial]);

	const commit = () => {
		try {
			const parsed = draft.trim() === "" ? {} : JSON.parse(draft);

			if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
				setError(null);
				onChange(parsed as Record<string, unknown>);
			} else {
				setError("Field settings must be a JSON object.");
			}
		} catch (err) {
			setError(err instanceof Error ? err.message : "Invalid JSON");
		}
	};

	return (
		<div className="mt-3">
			<label className="block">
				<span className="mb-1 block text-[11.5px] font-medium text-text-2">
					Field settings <span className="text-text-3">(JSON)</span>
				</span>
				<textarea
					rows={5}
					className="w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-[11.5px] leading-relaxed focus:outline-none focus:ring-1 focus:ring-accent-ring"
					value={draft}
					onChange={(e) => setDraft(e.target.value)}
					onBlur={commit}
					spellCheck={false}
				/>
			</label>
			{error && <div className="mt-1 text-[11.5px] text-danger">{error}</div>}
		</div>
	);
};

const safeStringify = (value: unknown): string => {
	if (value === undefined || value === null) {
		return "{}";
	}

	try {
		return JSON.stringify(value, null, 2);
	} catch {
		return "{}";
	}
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
