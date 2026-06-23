import { useEffect, useMemo, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import * as Dialog from "@radix-ui/react-dialog";
import { FileText, LayoutGrid, Search, Tag, Users, X } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";
import { searchApi, type SearchResultGroups } from "@/api/endpoints/search";
import { useAuthStore } from "@/auth/store";
import { isAdmin } from "@/lib/permissions";

/* eslint-disable @typescript-eslint/no-explicit-any -- search results are heterogeneous (raw module cache rows, etc.) and intentionally rendered generically */

interface QuickSearchProps {
	open: boolean;
	onClose: () => void;
}

interface ActionableItem {
	group: string;
	item: unknown;
	action: () => void;
}

const GROUP_ORDER = ["pages", "modules", "entries", "tags", "users"] as const;
/** Result groups only Administrators may navigate to (Tags / Users are level 1). */
const ADMIN_ONLY_GROUPS = new Set<string>(["tags", "users"]);
const GROUP_LABELS: Record<string, string> = {
	pages: "Pages",
	modules: "Modules",
	entries: "Module entries",
	tags: "Tags",
	users: "Users",
};

export const QuickSearch = ({ open, onClose }: QuickSearchProps) => {
	const navigate = useNavigate();
	const admin = isAdmin(useAuthStore((s) => s.user));
	const visibleGroups = useMemo(
		() => GROUP_ORDER.filter((g) => admin || !ADMIN_ONLY_GROUPS.has(g)),
		[admin]
	);
	const inputRef = useRef<HTMLInputElement>(null);
	const [rawQuery, setRawQuery] = useState("");
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [activeIndex, setActiveIndex] = useState(0);

	// Debounce the typed query (250ms feels responsive but not too chatty) and
	// reset the highlight in the same pass — folding the reset in here avoids an
	// extra render hop from a separate "debouncedQuery changed" effect.
	useEffect(() => {
		const t = setTimeout(() => {
			setDebouncedQuery(rawQuery.trim());
			setActiveIndex(0);
		}, 250);
		return () => clearTimeout(t);
	}, [rawQuery]);

	// Auto-focus the input when the palette opens
	useEffect(() => {
		if (open) {
			const t = setTimeout(() => {
				inputRef.current?.focus();
			}, 60);
			return () => clearTimeout(t);
		}
	}, [open]);

	// Clear local state when the palette is dismissed so the next open feels
	// fresh. Done during the open→closed render transition rather than in an
	// effect, so it doesn't cascade into the scroll-into-view effect below.
	const [prevOpen, setPrevOpen] = useState(open);

	if (prevOpen !== open) {
		setPrevOpen(open);

		if (!open) {
			setRawQuery("");
			setDebouncedQuery("");
			setActiveIndex(0);
		}
	}

	const { data: groups, isLoading } = useQuery({
		queryKey: ["search", debouncedQuery],
		queryFn: () => searchApi.search(debouncedQuery, { limit: 8 }),
		enabled: open && debouncedQuery.length >= 2,
	});

	// Build a flat list of actionable results for keyboard navigation
	const actionable = useMemo<ActionableItem[]>(() => {
		if (!groups) return [];
		const list: ActionableItem[] = [];

		for (const g of visibleGroups) {
			const arr = (groups as Record<string, unknown[]>)[g] ?? [];
			for (const it of arr) {
				list.push({
					group: g,
					item: it,
					action: () => {
						if (g === "pages") {
							navigate("/pages");
						} else if (g === "modules" || g === "entries") {
							navigate("/modules");
						} else if (g === "tags") {
							navigate("/tags");
						} else if (g === "users") {
							navigate("/users");
						}
						onClose();
					},
				});
			}
		}
		return list;
	}, [groups, navigate, onClose, visibleGroups]);

	// Keyboard nav (arrows + enter) only while open
	useEffect(() => {
		if (!open) return;

		const handler = (e: KeyboardEvent) => {
			if (e.key === "Escape") {
				e.preventDefault();
				onClose();
				return;
			}
			if (!actionable.length) return;

			if (e.key === "ArrowDown") {
				e.preventDefault();
				setActiveIndex((i) => Math.min(i + 1, actionable.length - 1));
			} else if (e.key === "ArrowUp") {
				e.preventDefault();
				setActiveIndex((i) => Math.max(i - 1, 0));
			} else if (e.key === "Enter") {
				e.preventDefault();
				const current = actionable[activeIndex];
				if (current) current.action();
			}
		};

		document.addEventListener("keydown", handler);
		return () => document.removeEventListener("keydown", handler);
	}, [open, actionable, activeIndex, onClose]);

	// Scroll active result into view
	useEffect(() => {
		if (!open) return;
		const el = document.querySelector(
			`[data-search-idx="${activeIndex}"]`
		) as HTMLElement | null;
		el?.scrollIntoView({ block: "nearest", behavior: "smooth" });
	}, [activeIndex, open]);

	// Helpers to render individual result rows (keeps the big render readable)
	const renderPage = (p: any, idx: number, isActive: boolean) => (
		<button
			key={`p-${p.id}`}
			type="button"
			data-search-idx={idx}
			onClick={() => {
				navigate("/pages");
				onClose();
			}}
			className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors ${
				isActive ? "bg-hover" : "hover:bg-hover"
			}`}
		>
			<FileText size={15} className="shrink-0 text-text-3" />
			<div className="min-w-0 flex-1">
				<div className="truncate font-medium">{p.nav_title}</div>
				<div className="truncate text-[11px] text-text-3">{p.path || "/"}</div>
			</div>
			{p.archived && (
				<span className="rounded bg-warn-bg px-1.5 py-px text-[10px] text-warn">
					archived
				</span>
			)}
		</button>
	);

	const renderModule = (m: any, idx: number, isActive: boolean) => (
		<button
			key={`m-${m.id}`}
			type="button"
			data-search-idx={idx}
			onClick={() => {
				navigate("/modules");
				onClose();
			}}
			className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors ${
				isActive ? "bg-hover" : "hover:bg-hover"
			}`}
		>
			<LayoutGrid size={15} className="shrink-0 text-text-3" />
			<div className="min-w-0 flex-1">
				<div className="truncate font-medium">{m.name}</div>
				<div className="truncate text-[11px] text-text-3">{m.route}</div>
			</div>
		</button>
	);

	const renderEntryGroup = (g: any, idx: number, isActive: boolean) => (
		<button
			key={`e-${g.module.id}-${idx}`}
			type="button"
			data-search-idx={idx}
			onClick={() => {
				navigate("/modules");
				onClose();
			}}
			className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors ${
				isActive ? "bg-hover" : "hover:bg-hover"
			}`}
		>
			<LayoutGrid size={15} className="shrink-0 text-text-3" />
			<div className="min-w-0 flex-1">
				<div className="truncate font-medium">{g.module.name} entry</div>
				<div className="truncate text-[11px] text-text-3">
					{Array.isArray(g.items) && g.items[0]
						? String(
								(g.items[0] as any).id ?? Object.values(g.items[0] as any)[0] ?? ""
							)
						: ""}
				</div>
			</div>
		</button>
	);

	const renderTag = (t: any, idx: number, isActive: boolean) => (
		<button
			key={`t-${t.id}`}
			type="button"
			data-search-idx={idx}
			onClick={() => {
				navigate("/tags");
				onClose();
			}}
			className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors ${
				isActive ? "bg-hover" : "hover:bg-hover"
			}`}
		>
			<Tag size={15} className="shrink-0 text-text-3" />
			<div className="min-w-0 flex-1">
				<div className="truncate font-medium">{t.tag}</div>
				<div className="truncate text-[11px] text-text-3">used {t.usage_count} times</div>
			</div>
		</button>
	);

	const renderUser = (u: any, idx: number, isActive: boolean) => (
		<button
			key={`u-${u.id}`}
			type="button"
			data-search-idx={idx}
			onClick={() => {
				navigate("/users");
				onClose();
			}}
			className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors ${
				isActive ? "bg-hover" : "hover:bg-hover"
			}`}
		>
			<Users size={15} className="shrink-0 text-text-3" />
			<div className="min-w-0 flex-1">
				<div className="truncate font-medium">{u.name}</div>
				<div className="truncate text-[11px] text-text-3">{u.email}</div>
			</div>
		</button>
	);

	// Visual grouped rendering (with running idx for keyboard highlight)
	let runningIdx = 0;

	return (
		<Dialog.Root
			open={open}
			onOpenChange={(isOpen) => {
				if (!isOpen) onClose();
			}}
		>
			<Dialog.Portal>
				<Dialog.Overlay className="fixed inset-0 z-60 bg-black/35 backdrop-blur-[1px]" />
				<Dialog.Content className="fixed left-1/2 top-[10vh] z-70 w-[min(620px,94vw)] -translate-x-1/2 rounded-xl border border-border bg-surface shadow-lg focus:outline-none">
					{/* Search input row */}
					<div className="flex items-center gap-3 border-b border-border px-4 py-3">
						<Search size={18} className="text-text-3" />
						<input
							ref={inputRef}
							value={rawQuery}
							onChange={(e) => setRawQuery(e.target.value)}
							aria-label="Search pages, modules, tags, users"
							placeholder="Search pages, modules, tags, users…"
							className="flex-1 bg-transparent text-[15px] text-text placeholder:text-text-3 focus:outline-none"
							spellCheck={false}
						/>
						<IconButton label="Close" title="Close (Esc)" onClick={onClose}>
							<X size={16} />
						</IconButton>
					</div>

					{/* Results */}
					<div className="max-h-[460px] overflow-auto p-1 text-[13px]">
						{!debouncedQuery && (
							<div className="px-4 py-10 text-center text-[12.5px] text-text-3">
								Type at least two characters to search the site.
							</div>
						)}

						{debouncedQuery && isLoading && (
							<div className="px-4 py-6 text-center text-[12.5px] text-text-3">
								Searching…
							</div>
						)}

						{debouncedQuery && !isLoading && groups && (
							<>
								{visibleGroups.map((gKey) => {
									const items = (groups as SearchResultGroups)[
										gKey as keyof SearchResultGroups
									];
									if (!items || (Array.isArray(items) && items.length === 0))
										return null;

									return (
										<div key={gKey} className="mb-1 last:mb-0">
											<div className="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-text-3">
												{GROUP_LABELS[gKey]}
											</div>
											<div className="flex flex-col gap-px">
												{(items as any[]).map((it) => {
													const currentIdx = runningIdx++;
													const isActive = currentIdx === activeIndex;

													if (gKey === "pages")
														return renderPage(it, currentIdx, isActive);
													if (gKey === "modules")
														return renderModule(
															it,
															currentIdx,
															isActive
														);
													if (gKey === "entries")
														return renderEntryGroup(
															it,
															currentIdx,
															isActive
														);
													if (gKey === "tags")
														return renderTag(it, currentIdx, isActive);
													if (gKey === "users")
														return renderUser(it, currentIdx, isActive);
													return null;
												})}
											</div>
										</div>
									);
								})}

								{Object.keys(groups).length === 0 && (
									<div className="px-4 py-8 text-center text-[12.5px] text-text-3">
										No results for “{debouncedQuery}”.
									</div>
								)}
							</>
						)}
					</div>

					{/* Footer hints */}
					<div className="flex items-center justify-between border-t border-border px-4 py-2 text-[11px] text-text-3">
						<span>↑↓ navigate · ↵ open · esc close</span>
						<span>⌘K</span>
					</div>
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
