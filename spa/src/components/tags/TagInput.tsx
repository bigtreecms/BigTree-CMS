import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { RemovableChip } from "@/components/ui/RemovableChip";
import { tagsApi, type Tag } from "@/api/endpoints/tags";

/**
 * Tag combobox with create-on-blur. Single-select returns `Tag | null`;
 * multi-select returns `Tag[]`. Either way the dropdown is wired to
 * `GET /tags/search` and pressing Enter on a typed value that isn't an
 * existing match creates a new tag (`POST /tags` — server normalizes +
 * de-dupes so re-typing a known tag returns the existing row).
 *
 * The component is intentionally lightweight — no portals, no Radix
 * Popover — so consumers can drop it into any form field without
 * fighting positioning. The dropdown is rendered as an absolute child
 * of the wrapper.
 */

interface SingleProps {
	multiple?: false;
	value: Tag | null;
	onChange: (next: Tag | null) => void;
	placeholder?: string;
	disabled?: boolean;
	/** Tags to omit from the suggestion dropdown (e.g. the current target on a merge page). */
	excludeIds?: number[];
}

interface MultiProps {
	multiple: true;
	value: Tag[];
	onChange: (next: Tag[]) => void;
	placeholder?: string;
	disabled?: boolean;
	excludeIds?: number[];
}

type TagInputProps = SingleProps | MultiProps;

export const TagInput = (props: TagInputProps) => {
	const queryClient = useQueryClient();
	const wrapperRef = useRef<HTMLDivElement>(null);
	const inputRef = useRef<HTMLInputElement>(null);

	const [text, setText] = useState("");
	const [open, setOpen] = useState(false);
	const [activeIndex, setActiveIndex] = useState(0);

	const trimmed = text.trim();

	// Debounce the search so we don't hit the API on every keystroke.
	const [debounced, setDebounced] = useState("");

	useEffect(() => {
		const h = setTimeout(() => setDebounced(trimmed.toLowerCase()), 150);

		return () => clearTimeout(h);
	}, [trimmed]);

	const searchQuery = useQuery({
		queryKey: ["tags", "search", debounced],
		queryFn: () => tagsApi.search(debounced),
		enabled: open && debounced.length > 0,
	});

	const createMutation = useMutation({
		mutationFn: (tag: string) => tagsApi.create(tag),
		onSuccess: (created) => {
			queryClient.invalidateQueries({ queryKey: ["tags"] });
			pickTag(created);
		},
	});

	// Resolve the suggestion list against the current value + exclusion list.
	const selectedIds = props.multiple
		? new Set(props.value.map((t) => t.id))
		: new Set(props.value ? [props.value.id] : []);
	const excludeIds = new Set([...selectedIds, ...(props.excludeIds ?? [])]);

	const suggestions = (searchQuery.data ?? []).filter((t) => !excludeIds.has(t.id));
	const exactMatch = suggestions.find((t) => t.tag.toLowerCase() === debounced);
	const canCreate = trimmed.length > 0 && !exactMatch && !createMutation.isPending;

	// Reset active index whenever the suggestion list changes.
	useEffect(() => {
		setActiveIndex(0);
	}, [suggestions.length, canCreate]);

	useEffect(() => {
		const handler = (event: MouseEvent) => {
			if (!wrapperRef.current?.contains(event.target as Node)) {
				setOpen(false);
			}
		};

		document.addEventListener("mousedown", handler);

		return () => document.removeEventListener("mousedown", handler);
	}, []);

	const pickTag = (tag: Tag) => {
		if (props.multiple) {
			props.onChange([...props.value, tag]);
		} else {
			props.onChange(tag);
		}

		setText("");
		setDebounced("");
		setOpen(props.multiple ?? false);

		// Refocus the input so the user can keep typing in multi mode.
		if (props.multiple) {
			inputRef.current?.focus();
		}
	};

	const removeTag = (id: number) => {
		if (props.multiple) {
			props.onChange(props.value.filter((t) => t.id !== id));

			return;
		}
		props.onChange(null);
	};

	const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
		const optionCount = suggestions.length + (canCreate ? 1 : 0);

		if (event.key === "ArrowDown") {
			event.preventDefault();
			setOpen(true);
			setActiveIndex((i) => (optionCount === 0 ? 0 : (i + 1) % optionCount));

			return;
		}

		if (event.key === "ArrowUp") {
			event.preventDefault();
			setActiveIndex((i) => (optionCount === 0 ? 0 : (i - 1 + optionCount) % optionCount));

			return;
		}

		if (event.key === "Enter") {
			event.preventDefault();

			// Pick a suggestion if the cursor is on one.
			const candidate = suggestions[activeIndex];

			if (candidate) {
				pickTag(candidate);

				return;
			}

			// "Create" row is rendered as the last option when canCreate.
			if (canCreate) {
				createMutation.mutate(trimmed);
			}

			return;
		}

		if (event.key === "Escape") {
			setOpen(false);

			return;
		}

		if (event.key === "Backspace" && text === "" && props.multiple && props.value.length > 0) {
			event.preventDefault();
			const last = props.value[props.value.length - 1];

			if (last) {
				removeTag(last.id);
			}
		}
	};

	const handleBlur = () => {
		// Create-on-blur: if the user typed a tag that doesn't match anything
		// and didn't pick a suggestion, materialize it before losing focus.
		if (trimmed && !exactMatch && !createMutation.isPending) {
			createMutation.mutate(trimmed);
		}
	};

	return (
		<div ref={wrapperRef} className="relative">
			<div
				className={`flex flex-wrap items-center gap-1.5 rounded-md border border-border bg-surface px-2 py-1.5 text-[13.5px] focus-within:ring-1 focus-within:ring-accent-ring ${
					props.disabled ? "opacity-60" : ""
				}`}
			>
				{props.multiple &&
					props.value.map((tag) => (
						<RemovableChip
							key={tag.id}
							label={tag.tag}
							onRemove={() => removeTag(tag.id)}
							disabled={props.disabled}
						/>
					))}

				{!props.multiple && props.value
					? (() => {
							const single = props.value;

							return (
								<RemovableChip
									label={single.tag}
									onRemove={() => removeTag(single.id)}
									disabled={props.disabled}
								/>
							);
						})()
					: null}

				{(props.multiple || !props.value) && (
					<input
						ref={inputRef}
						type="text"
						className="flex-1 min-w-[120px] bg-transparent outline-none placeholder:text-text-3"
						placeholder={props.placeholder ?? "Add tag…"}
						aria-label={props.placeholder ?? "Add tag"}
						value={text}
						onChange={(e) => {
							setText(e.target.value);
							setOpen(true);
						}}
						onFocus={() => setOpen(true)}
						onBlur={handleBlur}
						onKeyDown={handleKeyDown}
						disabled={props.disabled}
					/>
				)}
			</div>

			{open && trimmed.length > 0 && (
				<div className="absolute inset-x-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded-md border border-border bg-surface shadow-md">
					{searchQuery.isLoading ? (
						<div className="px-3 py-2 text-[12.5px] text-text-3">Searching…</div>
					) : (
						<>
							{suggestions.length === 0 && !canCreate && (
								<div className="px-3 py-2 text-[12.5px] text-text-3">
									No matches.
								</div>
							)}

							{suggestions.map((tag, i) => (
								<button
									key={tag.id}
									type="button"
									className={`flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-[13px] ${
										activeIndex === i
											? "bg-accent-soft text-text"
											: "hover:bg-hover"
									}`}
									onMouseEnter={() => setActiveIndex(i)}
									onMouseDown={(e) => {
										// Prevent input blur from firing first.
										e.preventDefault();
										pickTag(tag);
									}}
								>
									<span>{tag.tag}</span>
									<span className="text-[11px] tabular-nums text-text-3">
										{tag.usage_count}
									</span>
								</button>
							))}

							{canCreate && (
								<button
									type="button"
									className={`flex w-full items-center gap-2 border-t border-border px-3 py-1.5 text-left text-[13px] ${
										activeIndex === suggestions.length
											? "bg-accent-soft text-text"
											: "hover:bg-hover"
									}`}
									onMouseEnter={() => setActiveIndex(suggestions.length)}
									onMouseDown={(e) => {
										e.preventDefault();
										createMutation.mutate(trimmed);
									}}
								>
									<span className="text-text-3">Create</span>
									<span className="font-medium">“{trimmed}”</span>
								</button>
							)}
						</>
					)}
				</div>
			)}
		</div>
	);
};
