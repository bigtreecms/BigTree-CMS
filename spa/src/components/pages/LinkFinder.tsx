import { File as FileIcon, Newspaper, Package, Search } from "lucide-react";

import { PopoverPanel } from "@/components/ui/Popover";

import { useCopyToClipboard } from "@/hooks/useCopyToClipboard";
import { useLinkSearch } from "@/hooks/useLinkSearch";

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
	groupLabel: string;
	kind: ResultKind;
	label: string;
	value: string;
}

export const LinkFinder = () => {
	const copyToClipboard = useCopyToClipboard();
	const {
		query: q,
		setQuery: setQ,
		setOpen,
		containerRef,
		shouldSearch,
		pages,
		modules,
		files,
		isFetching,
	} = useLinkSearch({ types: ["pages", "modules"], limit: 10 });

	const hits: Hit[] = [
		...pages.map(
			(p): Hit => ({
				kind: "page",
				label: p.nav_title,
				groupLabel: "Pages",
				value: `ipl://0/${p.id}`,
			})
		),
		...modules.map(
			(m): Hit => ({
				kind: "module",
				label: m.name,
				groupLabel: "Modules",
				value: `/modules/${m.route}`,
			})
		),
		...files.map(
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
		<div className="relative my-1.5 self-center" ref={containerRef}>
			<div className="flex items-center rounded-md border border-border bg-surface px-2 py-1 transition-colors focus-within:border-accent focus-within:ring-1 focus-within:ring-accent-ring">
				<Search className="mr-1.5 text-text-3" size={13} />
				<input
					aria-label="Link Finder"
					className="w-44 bg-transparent text-[12.5px] text-text outline-none placeholder:text-text-3 md:w-56"
					placeholder="Link Finder"
					value={q}
					onChange={(e) => {
						setQ(e.target.value);
						setOpen(true);
					}}
					onFocus={() => setOpen(true)}
				/>
			</div>

			{shouldSearch && (
				<PopoverPanel className="right-0 top-full w-[min(420px,90vw)] overflow-hidden">
					<div className="border-b border-border bg-surface-2 px-3 py-1 text-[10.5px] uppercase tracking-wider text-text-3">
						Pick an item to copy its reference
					</div>

					{isFetching && hits.length === 0 ? (
						<div className="px-3 py-2 text-[12px] text-text-3">Searching…</div>
					) : hits.length === 0 ? (
						<div className="px-3 py-2 text-[12px] text-text-3">No results.</div>
					) : (
						<ul className="max-h-80 overflow-y-auto">
							{hits.map((hit, index) => (
								<li key={`${hit.kind}-${index}-${hit.value}`}>
									<button
										className="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-hover"
										type="button"
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
		return <Newspaper className="shrink-0 text-accent" size={12} />;
	}

	if (kind === "module") {
		return <Package className="shrink-0 text-accent" size={12} />;
	}

	return <FileIcon className="shrink-0 text-accent" size={12} />;
};
