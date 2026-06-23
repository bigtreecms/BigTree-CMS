import { useEffect, useId, useMemo, useRef, useState } from "react";
import { Check, ChevronsUpDown, Search, X } from "lucide-react";

export interface ComboboxOption<V extends string | number> {
	value: V;
	label: string;
	/** Optional secondary line shown under the label (e.g. an email address). */
	sublabel?: string;
}

interface ComboboxProps<V extends string | number> {
	value: ComboboxOption<V> | null;
	onChange: (option: ComboboxOption<V> | null) => void;
	options: ComboboxOption<V>[];
	/**
	 * When provided, the parent owns filtering (typically an async search) and
	 * `options` is treated as the current result set — the Combobox forwards the
	 * query and renders whatever it's given. When omitted, the Combobox filters
	 * `options` locally against the label and sublabel.
	 */
	onSearchChange?: (query: string) => void;
	isLoading?: boolean;
	/** Trigger text shown when nothing is selected. */
	placeholder?: string;
	searchPlaceholder?: string;
	emptyLabel?: string;
	clearable?: boolean;
	disabled?: boolean;
	id?: string;
	ariaLabel?: string;
	/** Applied to the outer wrapper — use it to set the width (defaults to full). */
	className?: string;
}

/**
 * Reusable searchable single-select. A button trigger opens a popover with a
 * filter input and a keyboard-navigable option list. Two modes:
 *
 *   - local (default): pass `options`; typing filters them here.
 *   - async: also pass `onSearchChange`; the parent fetches and supplies the
 *     current result set, so this component stops filtering and just renders.
 *
 * The selected option is passed in whole (`value`) rather than by key so the
 * trigger can show its label even when it isn't in the current result set —
 * important for the async case where the list changes as you type.
 */
export const Combobox = <V extends string | number>({
	value,
	onChange,
	options,
	onSearchChange,
	isLoading = false,
	placeholder = "Select…",
	searchPlaceholder = "Search…",
	emptyLabel = "No matches.",
	clearable = true,
	disabled = false,
	id,
	ariaLabel,
	className,
}: ComboboxProps<V>) => {
	const generatedId = useId();
	const listboxId = `${id ?? generatedId}-listbox`;

	const [open, setOpen] = useState(false);
	const [query, setQuery] = useState("");
	const [activeIndex, setActiveIndex] = useState(0);

	const containerRef = useRef<HTMLDivElement>(null);
	const inputRef = useRef<HTMLInputElement>(null);

	const isAsync = Boolean(onSearchChange);

	const filtered = useMemo(() => {
		if (isAsync) {
			return options;
		}

		const needle = query.trim().toLowerCase();

		if (needle === "") {
			return options;
		}

		return options.filter(
			(option) =>
				option.label.toLowerCase().includes(needle) ||
				(option.sublabel?.toLowerCase().includes(needle) ?? false)
		);
	}, [isAsync, options, query]);

	// Close on outside click.
	useEffect(() => {
		if (!open) {
			return;
		}

		const handler = (event: MouseEvent) => {
			if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
				setOpen(false);
			}
		};

		window.addEventListener("mousedown", handler);

		return () => window.removeEventListener("mousedown", handler);
	}, [open]);

	// Focus the search input and reset the highlight whenever the popover opens.
	useEffect(() => {
		if (open) {
			setActiveIndex(0);
			inputRef.current?.focus();
		}
	}, [open]);

	// Keep the highlight in range as the filtered set shrinks.
	useEffect(() => {
		setActiveIndex((index) => Math.min(index, Math.max(0, filtered.length - 1)));
	}, [filtered.length]);

	const updateQuery = (next: string) => {
		setQuery(next);
		onSearchChange?.(next);
	};

	const select = (option: ComboboxOption<V>) => {
		onChange(option);
		setOpen(false);
		updateQuery("");
	};

	const clear = (event: React.MouseEvent) => {
		event.stopPropagation();
		onChange(null);
		updateQuery("");
	};

	const onKeyDown = (event: React.KeyboardEvent) => {
		if (event.key === "ArrowDown") {
			event.preventDefault();
			setActiveIndex((index) => Math.min(index + 1, filtered.length - 1));
		} else if (event.key === "ArrowUp") {
			event.preventDefault();
			setActiveIndex((index) => Math.max(index - 1, 0));
		} else if (event.key === "Enter") {
			event.preventDefault();
			const option = filtered[activeIndex];

			if (option) {
				select(option);
			}
		} else if (event.key === "Escape") {
			event.preventDefault();
			setOpen(false);
		}
	};

	return (
		<div ref={containerRef} className={`relative ${className ?? "w-full"}`}>
			<button
				type="button"
				id={id}
				aria-label={ariaLabel}
				aria-haspopup="listbox"
				aria-expanded={open}
				disabled={disabled}
				className="flex w-full items-center rounded-md border border-border bg-surface py-1.5 pl-3 pr-8 text-left text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:opacity-60"
				onClick={() => setOpen((prev) => !prev)}
			>
				<span className={`min-w-0 flex-1 truncate ${value ? "text-text" : "text-text-3"}`}>
					{value ? value.label : placeholder}
				</span>
			</button>

			{/* Trailing control sits as a sibling, not nested inside the trigger
			    button — interactive controls must not be nested (a11y). */}
			{clearable && value && !disabled ? (
				<button
					type="button"
					className="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-text-3 hover:bg-hover hover:text-text"
					onClick={clear}
					aria-label="Clear selection"
				>
					<X size={13} />
				</button>
			) : (
				<ChevronsUpDown
					size={13}
					className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-text-3"
				/>
			)}

			{open && (
				<div className="absolute z-20 mt-1 w-full overflow-hidden rounded-md border border-border bg-surface shadow-lg">
					<div className="relative border-b border-border">
						<Search
							size={13}
							className="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-3"
						/>
						<input
							ref={inputRef}
							className="w-full bg-transparent py-2 pl-8 pr-3 text-[13px] text-text outline-none placeholder:text-text-3"
							placeholder={searchPlaceholder}
							aria-label={searchPlaceholder}
							value={query}
							onChange={(e) => updateQuery(e.target.value)}
							onKeyDown={onKeyDown}
							role="combobox"
							aria-controls={listboxId}
							aria-expanded={open}
							aria-autocomplete="list"
						/>
					</div>

					<ul id={listboxId} role="listbox" className="max-h-64 overflow-y-auto py-1">
						{isLoading ? (
							<li className="px-3 py-2 text-[12px] text-text-3">Loading…</li>
						) : filtered.length === 0 ? (
							<li className="px-3 py-2 text-[12px] text-text-3">{emptyLabel}</li>
						) : (
							filtered.map((option, index) => {
								const selected = value?.value === option.value;
								const active = index === activeIndex;

								return (
									<li
										key={String(option.value)}
										role="option"
										aria-selected={selected}
									>
										<button
											type="button"
											className={`flex w-full items-center gap-2 px-3 py-1.5 text-left ${active ? "bg-hover" : ""}`}
											onMouseEnter={() => setActiveIndex(index)}
											onClick={() => select(option)}
										>
											<Check
												size={13}
												className={`shrink-0 ${selected ? "text-accent" : "invisible"}`}
											/>
											<span className="min-w-0 flex-1">
												<span className="block truncate text-[13px] text-text">
													{option.label}
												</span>
												{option.sublabel && (
													<span className="block truncate font-mono text-[11px] text-text-3">
														{option.sublabel}
													</span>
												)}
											</span>
										</button>
									</li>
								);
							})
						)}
					</ul>
				</div>
			)}
		</div>
	);
};
