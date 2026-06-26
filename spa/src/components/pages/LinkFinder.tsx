import { useEffect, useRef, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { File as FileIcon, Newspaper, Package, Search } from "lucide-react";

import { searchApi } from "@/api/endpoints/search";
import { resourcesApi } from "@/api/endpoints/resources";

import { PopoverPanel } from "@/components/ui/Popover";

import { useOnClickOutside } from "@/hooks/useOnClickOutside";
import { useCopyToClipboard } from "@/hooks/useCopyToClipboard";

/**
 * Typeahead lifted from the design's `.link-finder` block. Sits in the upper-
 * right of the wizard tabs and lets editors search pages / modules / files
 * without leaving the page editor.
 *
 * Pick action: copies the canonical reference (IPL / IRL / external URL) to
 * the clipboard with a toast. The design hints at drag-into-content but
 * that requires TinyMCE drop-zone integration — deferred. Clipboard-copy is
 * the V1 affordance.
 */
type ResultKind = "page" | "module" | "file";

interface Hit {
	kind: ResultKind;
	label: string;
	groupLabel: string;
	value: string;
}

export const LinkFinder = () => {
	const [q, setQ] = useState("");
	const [debounced, setDebounced] = useState("");
	const [open, setOpen] = useState(false);
	const containerRef = useRef<HTMLDivElement>(null);
	const copyToClipboard = useCopyToClipboard();

	useEffect(() => {
		const handle = setTimeout(() => setDebounced(q.trim()), 200);

		return () => clearTimeout(handle);
	}, [q]);

	useOnClickOutside(containerRef, () => setOpen(false), open);

	const enabled = open && debounced.length >= 2;

	const generalQuery = useQuery({
		queryKey: ["link-finder", "general", debounced],
		queryFn: () => searchApi.search(debounced, { types: ["pages", "modules"], limit: 10 }),
		enabled,
		placeholderData: keepPreviousData,
	});

	const filesQuery = useQuery({
		queryKey: ["link-finder", "files", debounced],
		queryFn: () => resourcesApi.search(debounced),
		enabled,
		placeholderData: keepPreviousData,
	});

	const hits: Hit[] = [
		...(generalQuery.data?.pages ?? []).map(
			(p): Hit => ({
				kind: "page",
				label: p.nav_title,
				groupLabel: "Pages",
				value: `ipl://0/${p.id}`,
			})
		),
		...(generalQuery.data?.modules ?? []).map(
			(m): Hit => ({
				kind: "module",
				label: m.name,
				groupLabel: "Modules",
				value: `/modules/${m.route}`,
			})
		),
		...(filesQuery.data ?? []).slice(0, 10).map(
			(r): Hit => ({
				kind: "file",
				label: r.name,
				groupLabel: "Files",
				value: `irl://0/${r.id}`,
			})
		),
	];

	const pick = async (hit: Hit) => {
		await copyToClipboard(hit.value, "Reference copied");
		setOpen(false);
		setQ("");
	};

	return (
		<div ref={containerRef} className="relative my-1.5 self-center">
			<div className="flex items-center rounded-md border border-border bg-surface px-2 py-1 transition-colors focus-within:border-accent focus-within:ring-1 focus-within:ring-accent-ring">
				<Search size={13} className="mr-1.5 text-text-3" />
				<input
					className="w-44 bg-transparent text-[12.5px] text-text outline-none placeholder:text-text-3 md:w-56"
					value={q}
					placeholder="Link Finder"
					aria-label="Link Finder"
					onChange={(e) => {
						setQ(e.target.value);
						setOpen(true);
					}}
					onFocus={() => setOpen(true)}
				/>
			</div>

			{open && debounced.length >= 2 && (
				<PopoverPanel className="right-0 top-full w-[min(420px,90vw)] overflow-hidden">
					<div className="border-b border-border bg-surface-2 px-3 py-1 text-[10.5px] uppercase tracking-wider text-text-3">
						Pick an item to copy its reference
					</div>

					{generalQuery.isFetching && filesQuery.isFetching && hits.length === 0 ? (
						<div className="px-3 py-2 text-[12px] text-text-3">Searching…</div>
					) : hits.length === 0 ? (
						<div className="px-3 py-2 text-[12px] text-text-3">No results.</div>
					) : (
						<ul className="max-h-80 overflow-y-auto">
							{hits.map((hit, index) => (
								<li key={`${hit.kind}-${index}-${hit.value}`}>
									<button
										type="button"
										className="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-hover"
										onClick={() => pick(hit)}
									>
										<KindIcon kind={hit.kind} />
										<span className="min-w-0 flex-1 truncate text-[12.5px] text-text-2">
											{hit.label}
										</span>
										<span className="text-[10.5px] uppercase tracking-wider text-text-3">
											{hit.groupLabel}
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

const KindIcon = ({ kind }: { kind: ResultKind }) => {
	if (kind === "page") {
		return <Newspaper size={12} className="shrink-0 text-accent" />;
	}

	if (kind === "module") {
		return <Package size={12} className="shrink-0 text-accent" />;
	}

	return <FileIcon size={12} className="shrink-0 text-accent" />;
};
