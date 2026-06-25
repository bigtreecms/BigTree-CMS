import { useEffect, useMemo, useRef, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, Plus, RotateCcw, Search, Trash, X } from "lucide-react";

import { EmptyState } from "@/components/ui/EmptyState";
import { IconButton } from "@/components/ui/IconButton";
import { LoadingText } from "@/components/ui/LoadingText";
import { PopoverPanel } from "@/components/ui/Popover";
import { useOnClickOutside } from "@/hooks/useOnClickOutside";
import {
	modulesApi,
	type RelationOption,
	type RelationOptionsResponse,
} from "@/api/endpoints/modules";

import { useFormRenderContext } from "@/renderer/forms/FormContext";

import { isTruthyFlag, toInt } from "./fieldHelpers";
import { settingsOf, type FieldComponentProps } from "./types";

export type RelationKind = "one-to-many" | "many-to-many";

interface RelationFieldProps extends FieldComponentProps {
	kind: RelationKind;
}

interface RelationFieldSettings {
	max?: number | string;
	show_add_all?: boolean | string | number;
	show_reset?: boolean | string | number;
}

const toIds = (raw: unknown): number[] => {
	if (!Array.isArray(raw)) {
		return [];
	}

	const out: number[] = [];

	for (const entry of raw) {
		const n = typeof entry === "number" ? entry : Number(entry);

		if (Number.isFinite(n) && n > 0) {
			out.push(Math.floor(n));
		}
	}

	return out;
};

const OPTIONS_KEY = (
	moduleId: string,
	formId: string,
	column: string,
	mode: string,
	suffix: string
) => ["relation-options", moduleId, formId, column, mode, suffix] as const;

/**
 * Shared core for OneToManyField + ManyToManyField. The two field types share
 * essentially identical UX — list of selected items with reorder + delete plus
 * a typeahead picker for adding more — but differ in:
 *
 *   - one-to-many → value is an array of ids stored directly on the entry's
 *     column. The form's `initialValues` already contains them.
 *   - many-to-many → value lives in a connecting table; we load the entry's
 *     current selections via /relation-options?entry= on first paint and call
 *     onChange to write them into the form's value bucket. FormRenderer then
 *     re-packs the value into the `__mtm__` array on submit.
 *
 * Both depend on the surrounding FormRenderContext (moduleId, formId, entryId)
 * to know which form-field this is. If the context isn't available the field
 * renders a clear stub explaining what's missing.
 */
export const RelationField = ({ field, value, onChange, disabled, kind }: RelationFieldProps) => {
	const context = useFormRenderContext();
	const settings = settingsOf(field) as RelationFieldSettings;
	const max = toInt(settings.max);
	const showAddAll = isTruthyFlag(settings.show_add_all) && max === 0;
	const showReset = isTruthyFlag(settings.show_reset);

	const moduleId = context?.moduleId ?? "";
	const formId = context?.formId ?? "";
	const entryId = context?.entryId ?? null;
	const column = field.column;
	const isMtm = kind === "many-to-many";
	const enabledCtx = Boolean(context);

	// — Initial MTM load —
	// One-to-many fields draw their ids from the entry's column directly, so
	// nothing to fetch on mount. Many-to-many fields need a one-shot lookup
	// against the connecting table to discover what's already linked.
	const initialMtmQuery = useQuery({
		queryKey: OPTIONS_KEY(moduleId, formId, column, "entry", String(entryId ?? "")),
		queryFn: () =>
			modulesApi.relationOptions(moduleId, formId, {
				column,
				entry: entryId ?? undefined,
			}),
		enabled: enabledCtx && isMtm && entryId !== null,
	});

	const initialFiredRef = useRef(false);

	useEffect(() => {
		if (!isMtm || initialFiredRef.current) {
			return;
		}

		const data = initialMtmQuery.data;

		if (!data) {
			return;
		}

		initialFiredRef.current = true;
		onChange(data.items.map((item) => item.id));
	}, [isMtm, initialMtmQuery.data, onChange]);

	const selectedIds = useMemo(() => toIds(value), [value]);

	// — Title resolution —
	// We keep a local cache of {id → title} populated from each lookup so the
	// list stays stable across queries and reorders.
	const [titleCache, setTitleCache] = useState<Map<number, string>>(() => new Map());

	useEffect(() => {
		if (isMtm && initialMtmQuery.data) {
			setTitleCache((prev) => mergeTitles(prev, initialMtmQuery.data?.items ?? []));
		}
	}, [isMtm, initialMtmQuery.data]);

	const missingTitleIds = useMemo(
		() => selectedIds.filter((id) => !titleCache.has(id)),
		[selectedIds, titleCache]
	);

	const titlesQuery = useQuery({
		queryKey: OPTIONS_KEY(moduleId, formId, column, "titles", missingTitleIds.join(",")),
		queryFn: () =>
			modulesApi.relationOptions(moduleId, formId, {
				column,
				ids: missingTitleIds,
			}),
		enabled: enabledCtx && missingTitleIds.length > 0,
	});

	useEffect(() => {
		if (titlesQuery.data) {
			setTitleCache((prev) => mergeTitles(prev, titlesQuery.data?.items ?? []));
		}
	}, [titlesQuery.data]);

	// — Picker (search) —
	const [search, setSearch] = useState("");
	const [debouncedSearch, setDebouncedSearch] = useState("");

	useEffect(() => {
		const handle = setTimeout(() => setDebouncedSearch(search.trim()), 200);

		return () => clearTimeout(handle);
	}, [search]);

	const pickerQuery = useQuery({
		queryKey: OPTIONS_KEY(moduleId, formId, column, "options", debouncedSearch),
		queryFn: () =>
			modulesApi.relationOptions(moduleId, formId, {
				column,
				q: debouncedSearch || undefined,
			}),
		placeholderData: keepPreviousData,
	});

	const selectedSet = useMemo(() => new Set(selectedIds), [selectedIds]);

	const pickerItems = useMemo(() => {
		const all = pickerQuery.data?.items ?? [];

		return all.filter((item) => !selectedSet.has(item.id));
	}, [pickerQuery.data, selectedSet]);

	const sortable = isMtm
		? Boolean(pickerQuery.data?.relation.sortable ?? initialMtmQuery.data?.relation.sortable)
		: true;
	const atLimit = max > 0 && selectedIds.length >= max;

	const commit = (nextIds: number[]) => {
		onChange(nextIds);
	};

	const addId = (id: number) => {
		if (selectedSet.has(id) || atLimit) {
			return;
		}

		commit([...selectedIds, id]);
	};

	const removeId = (id: number) => {
		commit(selectedIds.filter((i) => i !== id));
	};

	const moveId = (id: number, direction: "up" | "down") => {
		const index = selectedIds.indexOf(id);

		if (index < 0) {
			return;
		}

		const swapWith = direction === "up" ? index - 1 : index + 1;

		if (swapWith < 0 || swapWith >= selectedIds.length) {
			return;
		}

		const next = [...selectedIds];
		const removed = next.splice(index, 1)[0];

		if (removed !== undefined) {
			next.splice(swapWith, 0, removed);
			commit(next);
		}
	};

	const reset = () => {
		commit([]);
		setSearch("");
	};

	const addAll = () => {
		if (max > 0) {
			return;
		}

		const all = pickerQuery.data?.items ?? [];
		const next = [...selectedIds];

		for (const item of all) {
			if (!selectedSet.has(item.id)) {
				next.push(item.id);
			}
		}

		commit(next);
	};

	if (!enabledCtx) {
		return (
			<EmptyState size="sm" dashed>
				This {isMtm ? "many-to-many" : "one-to-many"} field needs to be rendered inside a
				module form (FormRenderer was called without a moduleId).
			</EmptyState>
		);
	}

	return (
		<div className="space-y-2">
			{isMtm && entryId !== null && initialMtmQuery.isLoading && (
				<LoadingText boxed label="Loading current selections…" />
			)}

			{selectedIds.length > 0 ? (
				<SelectedList
					ids={selectedIds}
					titles={titleCache}
					sortable={sortable && !disabled}
					onRemove={removeId}
					onMove={moveId}
					disabled={disabled}
				/>
			) : (
				<EmptyState size="sm" dashed>
					Nothing selected yet — use the picker below to add items.
				</EmptyState>
			)}

			<Picker
				items={pickerItems}
				isLoading={pickerQuery.isFetching && !pickerQuery.data}
				search={search}
				onSearchChange={setSearch}
				onPick={addId}
				disabled={disabled || atLimit}
				atLimit={atLimit}
			/>

			<div className="flex flex-wrap items-center justify-between gap-2 text-[11.5px] text-text-3">
				<div className="flex flex-wrap gap-2">
					{showAddAll && (
						<button
							type="button"
							className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-1 hover:bg-hover disabled:opacity-50"
							onClick={addAll}
							disabled={disabled || pickerItems.length === 0}
						>
							<Plus size={11} />
							Add all
						</button>
					)}
					{showReset && selectedIds.length > 0 && (
						<button
							type="button"
							className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-1 text-danger hover:bg-danger/5 disabled:opacity-50"
							onClick={reset}
							disabled={disabled}
						>
							<RotateCcw size={11} />
							Reset
						</button>
					)}
				</div>
				{max > 0 && (
					<span className="tabular-nums">
						{selectedIds.length} / {max}
					</span>
				)}
			</div>
		</div>
	);
};

const mergeTitles = (prev: Map<number, string>, items: RelationOption[]): Map<number, string> => {
	if (items.length === 0) {
		return prev;
	}

	const next = new Map(prev);

	for (const item of items) {
		next.set(item.id, item.title);
	}

	return next;
};

interface SelectedListProps {
	ids: number[];
	titles: Map<number, string>;
	sortable: boolean;
	onRemove: (id: number) => void;
	onMove: (id: number, direction: "up" | "down") => void;
	disabled?: boolean;
}

const SelectedList = ({ ids, titles, sortable, onRemove, onMove, disabled }: SelectedListProps) => {
	return (
		<ul className="space-y-1">
			{ids.map((id, index) => {
				const title = titles.get(id) ?? `Item #${id}`;

				return (
					<li
						key={id}
						className="flex items-center gap-2 rounded-md border border-border bg-surface px-2 py-1.5"
					>
						{sortable && (
							<div className="flex flex-col">
								<IconButton
									label="Move up"
									size="sm"
									onClick={() => onMove(id, "up")}
									disabled={disabled || index === 0}
									title="Move up"
								>
									<ArrowUp size={11} />
								</IconButton>
								<IconButton
									label="Move down"
									size="sm"
									onClick={() => onMove(id, "down")}
									disabled={disabled || index === ids.length - 1}
									title="Move down"
								>
									<ArrowDown size={11} />
								</IconButton>
							</div>
						)}
						<span className="min-w-0 flex-1 truncate text-[12.5px] text-text-2">
							{title}
						</span>
						{!disabled && (
							<IconButton label="Remove" tone="danger" onClick={() => onRemove(id)}>
								<Trash size={12} />
							</IconButton>
						)}
					</li>
				);
			})}
		</ul>
	);
};

interface PickerProps {
	items: RelationOption[];
	isLoading: boolean;
	search: string;
	onSearchChange: (next: string) => void;
	onPick: (id: number) => void;
	disabled: boolean;
	atLimit: boolean;
}

const Picker = ({
	items,
	isLoading,
	search,
	onSearchChange,
	onPick,
	disabled,
	atLimit,
}: PickerProps) => {
	const [open, setOpen] = useState(false);
	const containerRef = useRef<HTMLDivElement>(null);

	// Close the dropdown on outside click.
	useOnClickOutside(containerRef, () => setOpen(false), open);

	const placeholder = atLimit ? "Limit reached" : "Search items…";

	return (
		<div ref={containerRef} className="relative">
			<div className="relative">
				<Search
					size={13}
					className="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-3"
				/>
				<input
					type="text"
					aria-label="Search items"
					className="w-full rounded-md border border-border bg-surface py-1.5 px-8 text-[12.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:opacity-60"
					placeholder={placeholder}
					value={search}
					onChange={(e) => {
						onSearchChange(e.target.value);
						setOpen(true);
					}}
					onFocus={() => setOpen(true)}
					disabled={disabled}
				/>
				{search && (
					<IconButton
						label="Clear"
						className="absolute right-1.5 top-1/2 -translate-y-1/2"
						onClick={() => {
							onSearchChange("");
							setOpen(false);
						}}
					>
						<X size={12} />
					</IconButton>
				)}
			</div>

			{open && (
				<PopoverPanel className="max-h-64 w-full overflow-y-auto">
					{isLoading ? (
						<LoadingText className="block px-3 py-2" />
					) : items.length === 0 ? (
						<div className="px-3 py-2 text-[12px] text-text-3">
							{search ? `No items match “${search}”.` : "No items available."}
						</div>
					) : (
						<ul>
							{items.map((item) => (
								<li key={item.id}>
									<button
										type="button"
										className="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-[12.5px] text-text-2 hover:bg-hover"
										onClick={() => {
											onPick(item.id);
											onSearchChange("");
											setOpen(false);
										}}
									>
										<span className="min-w-0 truncate">{item.title}</span>
										<span className="text-[10.5px] text-text-3 tabular-nums">
											#{item.id}
										</span>
									</button>
								</li>
							))}
						</ul>
					)}
				</PopoverPanel>
			)}
		</div>
	);
};

// Defensive: silence the "unused" warning for the typed import in case the
// runtime tree-shakes the response interface — keep it referenced.
export type { RelationOptionsResponse };
