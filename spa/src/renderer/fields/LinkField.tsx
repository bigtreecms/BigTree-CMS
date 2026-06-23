import { useEffect, useMemo, useRef, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { ExternalLink, File as FileIcon, Newspaper, Search, X } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";
import { searchApi, type SearchPage } from "@/api/endpoints/search";
import { resourcesApi } from "@/api/endpoints/resources";
import type { ResourceSummary } from "@/api/endpoints/resource-folders";

import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * Link field — mirrors `core/admin/field-types/link/draw.php`.
 *
 * Stored value forms:
 *   - external URL    "https://example.com"
 *   - IPL (internal)  "ipl://0/{page_id}"
 *   - IRL (resource)  "irl://0/{resource_id}"
 *
 * The legacy server resolves IPL/IRL → URL at read time via
 * `BigTreeCMS::replaceInternalPageLinks`, so the value loaded into edit forms
 * is usually a real URL — we render it as-is in the input. When the user picks
 * something from the typeahead dropdown we store the IPL/IRL form so the
 * legacy self-healing (page route changes, resource moves) keeps working on
 * the next save.
 */
const IPL_PREFIX = "ipl://";
const IRL_PREFIX = "irl://";

const isIpl = (value: string) => value.startsWith(IPL_PREFIX);
const isIrl = (value: string) => value.startsWith(IRL_PREFIX);

interface LinkFieldSettings {
	/** When truthy the search dropdown is suppressed entirely (URL-only entry). */
	disable_search?: boolean | string | number;
}

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

export const LinkField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as LinkFieldSettings;
	const showSearch = !isTruthyFlag(settings.disable_search);

	const stored = typeof value === "string" ? value : value == null ? "" : String(value);

	const [search, setSearch] = useState("");
	const [debouncedSearch, setDebouncedSearch] = useState("");
	const [open, setOpen] = useState(false);
	const containerRef = useRef<HTMLDivElement>(null);

	useEffect(() => {
		const handle = setTimeout(() => setDebouncedSearch(search.trim()), 200);

		return () => clearTimeout(handle);
	}, [search]);

	// Close dropdown on outside click.
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

	const shouldSearch = open && showSearch && debouncedSearch.length >= 2;

	const pagesQuery = useQuery({
		queryKey: ["link-field", "pages", debouncedSearch],
		queryFn: () => searchApi.search(debouncedSearch, { types: ["pages"], limit: 20 }),
		enabled: shouldSearch,
		placeholderData: keepPreviousData,
	});

	const resourcesQuery = useQuery({
		queryKey: ["link-field", "resources", debouncedSearch],
		queryFn: () => resourcesApi.search(debouncedSearch),
		enabled: shouldSearch,
		placeholderData: keepPreviousData,
	});

	const pages = pagesQuery.data?.pages ?? [];
	const resources = (resourcesQuery.data ?? []).slice(0, 20);

	const pickPage = (page: SearchPage) => {
		onChange(`${IPL_PREFIX}0/${page.id}`);
		setSearch("");
		setOpen(false);
	};

	const pickResource = (resource: ResourceSummary) => {
		onChange(`${IRL_PREFIX}0/${resource.id}`);
		setSearch("");
		setOpen(false);
	};

	// Detect IPL/IRL on the stored value and resolve a friendly placeholder for
	// the input. The legacy server normally converts these to URLs at read time
	// so we mostly hit the URL branch — the IPL branches kick in only when the
	// value was set this session (we wrote the IPL ourselves).
	const summary = useMemo(() => summarizeStored(stored), [stored]);

	const clear = () => {
		onChange("");
		setSearch("");
	};

	const displayedInputValue = summary.urlForInput;
	const placeholder = summary.placeholder ?? "URL, or search pages and files…";

	return (
		<div ref={containerRef} className="relative">
			<div className="relative">
				{summary.kind === "external" && stored ? (
					<ExternalLink
						size={13}
						className="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-3"
					/>
				) : summary.kind === "ipl" ? (
					<Newspaper
						size={13}
						className="absolute left-2.5 top-1/2 -translate-y-1/2 text-accent"
					/>
				) : summary.kind === "irl" ? (
					<FileIcon
						size={13}
						className="absolute left-2.5 top-1/2 -translate-y-1/2 text-accent"
					/>
				) : (
					<Search
						size={13}
						className="absolute left-2.5 top-1/2 -translate-y-1/2 text-text-3"
					/>
				)}

				<input
					type="text"
					aria-label={field.title}
					className={`${INPUT_CLASS} px-8 `}
					value={search || displayedInputValue}
					placeholder={placeholder}
					disabled={disabled}
					onChange={(e) => {
						setSearch(e.target.value);
						setOpen(true);
						// If the user is editing the value (not searching for an
						// internal pick), commit the typed text as the URL.
						onChange(e.target.value);
					}}
					onFocus={() => showSearch && setOpen(true)}
				/>

				{stored && !disabled && (
					<IconButton
						label="Clear"
						className="absolute right-1.5 top-1/2 -translate-y-1/2"
						onClick={clear}
					>
						<X size={12} />
					</IconButton>
				)}
			</div>

			{open && showSearch && shouldSearch && (
				<div className="absolute z-10 mt-1 max-h-72 w-full overflow-y-auto rounded-md border border-border bg-surface shadow-lg">
					{pagesQuery.isFetching &&
					resourcesQuery.isFetching &&
					!pagesQuery.data &&
					!resourcesQuery.data ? (
						<div className="px-3 py-2 text-[12px] text-text-3">Searching…</div>
					) : pages.length === 0 && resources.length === 0 ? (
						<div className="px-3 py-2 text-[12px] text-text-3">
							No matches. Press Enter to keep what you typed as a plain URL.
						</div>
					) : (
						<>
							{pages.length > 0 && (
								<>
									<div className="border-b border-border bg-surface-2 px-3 py-1 text-[10.5px] font-semibold uppercase tracking-wider text-text-3">
										Pages
									</div>
									<ul>
										{pages.map((page) => (
											<li key={`p-${page.id}`}>
												<button
													type="button"
													className="flex w-full items-start gap-2 px-3 py-1.5 text-left hover:bg-hover"
													onClick={() => pickPage(page)}
												>
													<Newspaper
														size={13}
														className="mt-0.5 shrink-0 text-accent"
													/>
													<span className="min-w-0">
														<span className="block truncate text-[12.5px] text-text-2">
															{page.nav_title}
														</span>
														<span className="block truncate text-[11px] text-text-3">
															/{page.path}
														</span>
													</span>
												</button>
											</li>
										))}
									</ul>
								</>
							)}

							{resources.length > 0 && (
								<>
									<div className="border-y border-border bg-surface-2 px-3 py-1 text-[10.5px] font-semibold uppercase tracking-wider text-text-3">
										Files
									</div>
									<ul>
										{resources.map((resource) => (
											<li key={`r-${resource.id}`}>
												<button
													type="button"
													className="flex w-full items-start gap-2 px-3 py-1.5 text-left hover:bg-hover"
													onClick={() => pickResource(resource)}
												>
													<FileIcon
														size={13}
														className="mt-0.5 shrink-0 text-accent"
													/>
													<span className="min-w-0">
														<span className="block truncate text-[12.5px] text-text-2">
															{resource.name}
														</span>
														<span className="block truncate text-[11px] text-text-3">
															{resource.mimetype ||
																resource.type ||
																"file"}
														</span>
													</span>
												</button>
											</li>
										))}
									</ul>
								</>
							)}
						</>
					)}
				</div>
			)}
		</div>
	);
};

interface StoredSummary {
	kind: "empty" | "external" | "ipl" | "irl";
	/** What to render in the text input when the user isn't actively searching. */
	urlForInput: string;
	/** Optional placeholder hint (e.g. "Internal page #123 — search to replace"). */
	placeholder?: string;
}

const summarizeStored = (raw: string): StoredSummary => {
	if (!raw) {
		return { kind: "empty", urlForInput: "" };
	}

	if (isIpl(raw)) {
		const idPart = raw.slice(IPL_PREFIX.length).split("/").pop() ?? "";

		return {
			kind: "ipl",
			urlForInput: raw,
			placeholder: idPart ? `Internal page #${idPart} — type to replace` : undefined,
		};
	}

	if (isIrl(raw)) {
		const idPart = raw.slice(IRL_PREFIX.length).split("/").pop() ?? "";

		return {
			kind: "irl",
			urlForInput: raw,
			placeholder: idPart ? `Resource #${idPart} — type to replace` : undefined,
		};
	}

	return { kind: "external", urlForInput: raw };
};
