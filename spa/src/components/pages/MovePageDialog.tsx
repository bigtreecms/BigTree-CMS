import { useEffect, useState } from "react";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { useQuery, type QueryKey } from "@tanstack/react-query";
import { ChevronRight, Folder, Home, Search, X } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { SlideOver } from "@/components/ui/SlideOver";

import { pagesApi, type PageListRow, type PageSearchHit } from "@/api/endpoints/pages";
import { queryKeys } from "@/lib/queryKeys";
import { useToastMutation } from "@/hooks/useToastMutation";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";

interface MovePageDialogProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	/** Page being moved (null hides the dialog). */
	page: { id: number; nav_title: string; parent: number } | null;
	/** Query key invalidated on success (typically the parent list query). */
	invalidateKey: readonly unknown[];
}

/**
 * Slide-over for re-parenting a page. Two input methods, both backed by the
 * existing /pages endpoints:
 *
 *   - "Browse" path: traverses the page tree level-by-level (uses
 *     pagesApi.list at each depth).
 *   - "Search" path: types a query into /pages/search and picks from the hits.
 *
 * Refuses to set a page as its own descendant by filtering out the page being
 * moved (and its own id) from the candidate lists. Server-side validation in
 * PageService is the final safety net.
 */
export const MovePageDialog = ({
	open,
	onOpenChange,
	page,
	invalidateKey,
}: MovePageDialogProps) => {
	const [browseParent, setBrowseParent] = useState(0);
	const [crumbs, setCrumbs] = useState<Array<{ id: number; nav_title: string }>>([]);
	const [search, setSearch] = useState("");
	const debounced = useDebouncedValue(search.trim(), 200);
	const [target, setTarget] = useState<{ id: number; nav_title: string } | null>(null);

	// Reset state when the dialog opens for a new page.
	useEffect(() => {
		if (open && page) {
			setBrowseParent(page.parent ?? 0);
			setCrumbs([]);
			setSearch("");
			setTarget(null);
		}
	}, [open, page]);

	const listQuery = useQuery({
		queryKey: queryKeys.pages.list(browseParent),
		queryFn: () => pagesApi.list(browseParent),
		enabled: open && page !== null && debounced.length < 2,
	});

	const searchQuery = useQuery({
		queryKey: queryKeys.pages.search(debounced),
		queryFn: () => pagesApi.search(debounced),
		enabled: open && page !== null && debounced.length >= 2,
	});

	const pageInvalidateKeys: QueryKey[] = page
		? [[...invalidateKey], ["pages", "detail", page.id]]
		: [[...invalidateKey]];

	const moveMutation = useToastMutation({
		mutationFn: ({ parent }: { parent: number }) => {
			if (!page) {
				throw new Error("No page selected");
			}

			return pagesApi.move(page.id, parent);
		},
		invalidate: pageInvalidateKeys,
		successMessage: "Page moved",
		errorMessage: "Could not move page",
		onSuccess: () => {
			onOpenChange(false);
		},
	});

	const drillInto = (folder: PageListRow) => {
		setCrumbs((prev) => [...prev, { id: folder.id, nav_title: folder.nav_title }]);
		setBrowseParent(folder.id);
		setTarget({ id: folder.id, nav_title: folder.nav_title });
	};

	const navigateCrumb = (index: number) => {
		// -1 means jump to root
		const next = index < 0 ? [] : crumbs.slice(0, index + 1);
		setCrumbs(next);
		const newParent = next.length === 0 ? 0 : next[next.length - 1]!.id;
		setBrowseParent(newParent);
		setTarget(
			next.length === 0
				? { id: 0, nav_title: "Top level" }
				: { id: newParent, nav_title: next[next.length - 1]!.nav_title }
		);
	};

	const isSearching = debounced.length >= 2;
	const candidates = isSearching
		? (searchQuery.data ?? []).filter((hit) => page && hit.id !== page.id)
		: (listQuery.data ?? []).filter((row) => page && row.id !== page.id && !row.archived);

	return (
		<SlideOver
			open={open && page !== null}
			onOpenChange={onOpenChange}
			title={page ? `Move “${page.nav_title}”` : "Move page"}
			description="Pick a new parent. Top-level pages live at the site root."
			width="md"
			footer={
				<div className="flex items-center justify-between gap-2">
					<div className="min-w-0 truncate text-[12px] text-text-3">
						{target ? (
							<>
								New parent:{" "}
								<span className="font-medium text-text-2">
									{target.id === 0 ? "Top level" : target.nav_title}
								</span>
							</>
						) : (
							"Choose a destination above."
						)}
					</div>
					<div className="flex gap-2">
						<button
							type="button"
							className="rounded-md border border-border px-3 py-1.5 text-[12.5px] hover:bg-hover"
							onClick={() => onOpenChange(false)}
						>
							Cancel
						</button>
						<Button
							variant="primary"
							onClick={() => target && moveMutation.mutate({ parent: target.id })}
							disabled={!target}
							loading={moveMutation.isPending}
							loadingLabel="Moving…"
						>
							Move page
						</Button>
					</div>
				</div>
			}
		>
			<div className="space-y-3">
				<div className="relative">
					<Search
						size={14}
						className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
					/>
					<input
						className="w-full rounded-md border border-border bg-surface py-1.5 px-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
						placeholder="Search pages…"
						aria-label="Search pages"
						value={search}
						onChange={(e) => setSearch(e.target.value)}
					/>
					{search && (
						<IconButton
							className="absolute right-2 top-1/2 -translate-y-1/2"
							onClick={() => setSearch("")}
							label="Clear search"
						>
							<X size={14} />
						</IconButton>
					)}
				</div>

				{!isSearching && (
					<nav className="flex flex-wrap items-center gap-1 text-[12px] text-text-3">
						<button
							type="button"
							className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-hover hover:text-text"
							onClick={() => navigateCrumb(-1)}
						>
							<Home size={12} />
							Top level
						</button>
						{crumbs.map((c, index) => (
							<span key={c.id} className="flex items-center gap-1">
								<ChevronRight size={12} />
								<button
									type="button"
									className="rounded px-1.5 py-0.5 hover:bg-hover hover:text-text disabled:hover:bg-transparent"
									onClick={() => navigateCrumb(index)}
									disabled={index === crumbs.length - 1}
								>
									{c.nav_title}
								</button>
							</span>
						))}
					</nav>
				)}

				{!isSearching && (
					<button
						type="button"
						className="flex w-full items-center justify-between rounded-md border border-border bg-surface px-3 py-2 text-left text-[12.5px] hover:bg-hover"
						onClick={() => {
							setTarget({ id: 0, nav_title: "Top level" });
						}}
					>
						<span className="flex items-center gap-2">
							<Home size={14} className="text-accent" />
							Top level
						</span>
						{target?.id === 0 && (
							<span className="text-[11px] font-medium text-accent">selected</span>
						)}
					</button>
				)}

				{(listQuery.isFetching && !listQuery.data && !isSearching) ||
				(searchQuery.isFetching && !searchQuery.data && isSearching) ? (
					<InlineEmpty align="center" pad="md">
						Loading…
					</InlineEmpty>
				) : candidates.length === 0 ? (
					<InlineEmpty align="center">
						{isSearching ? `No pages match “${debounced}”.` : "No subpages here."}
					</InlineEmpty>
				) : (
					<ul className="overflow-hidden rounded-md border border-border">
						{candidates.map((row) => {
							const id = row.id;
							const title =
								"nav_title" in row
									? row.nav_title
									: (row as PageSearchHit).nav_title;
							const isTarget = target?.id === id;
							const hasChildren =
								"has_children" in row && (row as PageListRow).has_children;

							return (
								<li key={id} className="border-b border-border last:border-b-0">
									<div className="flex items-center justify-between gap-2 px-3 py-2 text-[12.5px] hover:bg-hover">
										<button
											type="button"
											className="flex min-w-0 flex-1 items-center gap-2 text-left"
											onClick={() => {
												setTarget({ id, nav_title: title });

												if (!isSearching && hasChildren) {
													drillInto(row as PageListRow);
												}
											}}
										>
											<Folder size={14} className="text-accent" />
											<span className="truncate text-text-2">{title}</span>
										</button>

										<div className="flex items-center gap-2">
											{isTarget && (
												<span className="text-[11px] font-medium text-accent">
													selected
												</span>
											)}
											{!isSearching && hasChildren && (
												<IconButton
													onClick={() => drillInto(row as PageListRow)}
													title="Open subpages"
													label="Open subpages"
												>
													<ChevronRight size={13} />
												</IconButton>
											)}
										</div>
									</div>
								</li>
							);
						})}
					</ul>
				)}
			</div>
		</SlideOver>
	);
};
