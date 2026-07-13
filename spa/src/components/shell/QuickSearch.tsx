import { useEffect, useMemo, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import * as Dialog from "@radix-ui/react-dialog";
import { FileText, LayoutGrid, Search, Sparkles, Tag, Users, X } from "lucide-react";

import { IconButton } from "@/components/ui/IconButton";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { QuickSearchResultRow } from "@/components/shell/QuickSearchResultRow";
import {
	searchApi,
	type AiSearchResponse,
	type SearchModuleEntryGroup,
	type SearchResultGroups,
} from "@/api/endpoints/search";
import { queryKeys } from "@/lib/queryKeys";
import { useAuthStore } from "@/auth/store";
import { isAdmin } from "@/lib/permissions";
import { describeApiError } from "@/lib/errorHandling";
import { decodeHtmlEntitiesDom } from "@/lib/html";
import { modulePath } from "@/lib/moduleActions";
import { moduleEntryEditPath, pageEditPath } from "@/lib/routes";

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

const AI_LOADING_MESSAGES = [
	"Understanding your question…",
	"Searching pages and modules…",
	"Checking content entries…",
	"Gathering the best matches…",
];

/**
 * Coerce the AI/classic payload into a safe { group: array } map.
 * Empty PHP arrays encode as JSON `[]`, which is truthy but not keyed — that used
 * to crash `for…of` / `.map` when the SPA treated it as a result object.
 */
const normalizeGroups = (raw: unknown): SearchResultGroups | undefined => {
	if (raw == null) {
		return undefined;
	}

	// JSON `[]` from an empty PHP list
	if (Array.isArray(raw)) {
		return {};
	}

	if (typeof raw !== "object") {
		return {};
	}

	const src = raw as Record<string, unknown>;
	const out: SearchResultGroups = {};

	for (const key of GROUP_ORDER) {
		const val = src[key];

		if (Array.isArray(val)) {
			out[key] = val as never;
		}
	}

	return out;
};

const asList = (value: unknown): unknown[] => (Array.isArray(value) ? value : []);

/**
 * Titles from the search/view cache often pass through htmlspecialchars (and
 * sometimes twice). Decode so text nodes show "Tom & Jerry", not "Tom &amp; Jerry".
 */
const displayText = (value: unknown): string => {
	if (value == null) {
		return "";
	}

	const raw = String(value);

	if (raw.trim() === "") {
		return "";
	}

	return decodeHtmlEntitiesDom(raw, 5);
};

/** Best human label from a module-view cache row (column2 is often the title). */
const entryItemLabel = (item: Record<string, unknown>): string => {
	for (const key of ["column2", "column1", "title", "name", "id"]) {
		const v = item[key];
		const label = displayText(v);

		if (label !== "") {
			return label;
		}
	}

	const first = Object.values(item).find((v) => displayText(v) !== "");

	return first != null ? displayText(first) : "Entry";
};

/**
 * Flatten entry groups into one row per item so each result can deep-link to
 * `/modules/{route}/edit/{id}` instead of dumping the user on /modules.
 */
const flattenEntryRows = (
	groups: SearchResultGroups | undefined
): Array<{
	module: SearchModuleEntryGroup["module"];
	item: Record<string, unknown>;
	entryId: string | number;
	path: string;
}> => {
	const out: Array<{
		module: SearchModuleEntryGroup["module"];
		item: Record<string, unknown>;
		entryId: string | number;
		path: string;
	}> = [];

	for (const group of asList(groups?.entries) as SearchModuleEntryGroup[]) {
		const route = group?.module?.route;

		if (!route) {
			continue;
		}

		for (const item of asList(group.items) as Record<string, unknown>[]) {
			const entryId = (item.id as string | number | undefined) ?? "";

			if (entryId === "" || entryId == null) {
				continue;
			}

			out.push({
				module: group.module,
				item,
				entryId,
				path: moduleEntryEditPath(route, "edit", entryId),
			});
		}
	}

	return out;
};

const AiSearchLoading = () => {
	const [msgIndex, setMsgIndex] = useState(0);

	useEffect(() => {
		const t = setInterval(() => {
			setMsgIndex((i) => (i + 1) % AI_LOADING_MESSAGES.length);
		}, 1800);

		return () => clearInterval(t);
	}, []);

	return (
		<div
			className="flex flex-col items-center gap-4 px-4 py-10"
			role="status"
			aria-live="polite"
		>
			<div className="relative flex size-12 items-center justify-center">
				<span className="absolute inset-0 animate-ping rounded-full bg-accent/20 [animation-duration:1.6s]" />
				<span className="absolute inset-1 animate-pulse rounded-full bg-accent/15" />
				<span className="relative flex size-10 items-center justify-center rounded-full border border-accent/30 bg-accent/10 text-accent">
					<Sparkles size={18} className="animate-pulse" />
				</span>
			</div>

			<div className="text-center">
				<div className="text-[13px] font-medium text-text">Thinking…</div>
				<div className="mt-1 min-h-5 text-[12px] text-text-3 transition-opacity">
					{AI_LOADING_MESSAGES[msgIndex]}
				</div>
			</div>

			{/* Indeterminate progress bar */}
			<div className="h-1 w-40 overflow-hidden rounded-full bg-border">
				<div className="h-full w-1/2 animate-[ai-search-slide_1.2s_ease-in-out_infinite] rounded-full bg-accent" />
			</div>

			<style>{`
				@keyframes ai-search-slide {
					0% { transform: translateX(-100%); }
					50% { transform: translateX(100%); }
					100% { transform: translateX(250%); }
				}
			`}</style>
		</div>
	);
};

export const QuickSearch = ({ open, onClose }: QuickSearchProps) => {
	const navigate = useNavigate();
	const user = useAuthStore((s) => s.user);
	const admin = isAdmin(user);
	const aiSearchEnabled = !!user?.features?.ai_search;
	const visibleGroups = useMemo(
		() => GROUP_ORDER.filter((g) => admin || !ADMIN_ONLY_GROUPS.has(g)),
		[admin]
	);
	const inputRef = useRef<HTMLInputElement>(null);
	const [rawQuery, setRawQuery] = useState("");
	/** Classic search: debounced. AI search: only set on Enter submit. */
	const [debouncedQuery, setDebouncedQuery] = useState("");
	const [submittedQuery, setSubmittedQuery] = useState("");
	const [activeIndex, setActiveIndex] = useState(0);

	// Debounce the typed query for classic search only.
	useEffect(() => {
		if (aiSearchEnabled) {
			return;
		}

		const t = setTimeout(() => {
			setDebouncedQuery(rawQuery.trim());
			setActiveIndex(0);
		}, 250);

		return () => clearTimeout(t);
	}, [rawQuery, aiSearchEnabled]);

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
			setSubmittedQuery("");
			setActiveIndex(0);
		}
	}

	const classicQuery = useQuery({
		queryKey: queryKeys.search.results(debouncedQuery),
		queryFn: () => searchApi.search(debouncedQuery, { limit: 8 }),
		enabled: open && !aiSearchEnabled && debouncedQuery.length >= 2,
	});

	const aiQuery = useQuery({
		queryKey: [...queryKeys.search.results(submittedQuery), "ai"] as const,
		queryFn: () => searchApi.aiSearch(submittedQuery, { limit: 8 }),
		enabled: open && aiSearchEnabled && submittedQuery.length >= 2,
		// Agent calls are expensive — don't refetch on window focus.
		staleTime: 60_000,
		retry: false,
	});

	const groups: SearchResultGroups | undefined = aiSearchEnabled
		? normalizeGroups((aiQuery.data as AiSearchResponse | undefined)?.results)
		: normalizeGroups(classicQuery.data);
	const answer = aiSearchEnabled
		? ((aiQuery.data as AiSearchResponse | undefined)?.answer ?? "")
		: "";
	const isLoading = aiSearchEnabled ? aiQuery.isFetching : classicQuery.isLoading;
	const activeQueryText = aiSearchEnabled ? submittedQuery : debouncedQuery;
	const aiError =
		aiSearchEnabled && aiQuery.isError
			? describeApiError(aiQuery.error, "AI search failed")
			: null;

	const runAiSearch = () => {
		const q = rawQuery.trim();

		if (q.length < 2) {
			return;
		}

		setSubmittedQuery(q);
		setActiveIndex(0);
	};

	const entryRows = useMemo(() => flattenEntryRows(groups), [groups]);

	// Build a flat list of actionable results for keyboard navigation — order
	// must match the visual groups below (pages → modules → entries → tags → users).
	const actionable = useMemo<ActionableItem[]>(() => {
		if (!groups) return [];
		const list: ActionableItem[] = [];

		const push = (group: string, item: unknown, path: string) => {
			list.push({
				group,
				item,
				action: () => {
					navigate(path);
					onClose();
				},
			});
		};

		for (const g of visibleGroups) {
			if (g === "entries") {
				for (const row of entryRows) {
					push("entries", row, row.path);
				}

				continue;
			}

			for (const it of asList((groups as Record<string, unknown>)[g])) {
				const row = it as Record<string, unknown>;

				if (g === "pages" && row.id != null) {
					push(g, it, pageEditPath(row.id as string | number));
				} else if (g === "modules" && typeof row.route === "string" && row.route) {
					push(g, it, modulePath({ route: row.route }));
				} else if (g === "tags") {
					push(g, it, "/tags");
				} else if (g === "users" && row.id != null) {
					push(g, it, `/users/${encodeURIComponent(String(row.id))}/edit`);
				}
			}
		}

		return list;
	}, [groups, entryRows, navigate, onClose, visibleGroups]);

	// Keyboard nav (arrows + enter) only while open
	useEffect(() => {
		if (!open) return;

		const handler = (e: KeyboardEvent) => {
			if (e.key === "Escape") {
				e.preventDefault();
				onClose();
				return;
			}

			if (
				aiSearchEnabled &&
				e.key === "Enter" &&
				document.activeElement === inputRef.current
			) {
				// If there are no results yet (or user is still editing), run search.
				// When results exist and an item is highlighted, Enter selects that row
				// unless the user is still focused in the input with a changed query.
				if (!actionable.length || rawQuery.trim() !== submittedQuery) {
					e.preventDefault();
					runAiSearch();
					return;
				}
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
		// eslint-disable-next-line react-hooks/exhaustive-deps -- runAiSearch closes over rawQuery
	}, [open, actionable, activeIndex, onClose, aiSearchEnabled, rawQuery, submittedQuery]);

	// Scroll active result into view
	useEffect(() => {
		if (!open) return;
		const el = document.querySelector(
			`[data-search-idx="${activeIndex}"]`
		) as HTMLElement | null;
		el?.scrollIntoView({ block: "nearest", behavior: "smooth" });
	}, [activeIndex, open]);

	const selectAndClose = (path: string) => {
		navigate(path);
		onClose();
	};

	const renderPage = (p: any, idx: number, isActive: boolean) => (
		<QuickSearchResultRow
			key={`p-${p.id}`}
			icon={FileText}
			idx={idx}
			isActive={isActive}
			title={displayText(p.nav_title) || "Untitled"}
			subtitle={displayText(p.path) || "/"}
			badge={
				p.archived ? (
					<span className="rounded bg-warn-bg px-1.5 py-px text-[10px] text-warn">
						archived
					</span>
				) : undefined
			}
			onSelect={() => selectAndClose(pageEditPath(p.id))}
		/>
	);

	const renderModule = (m: any, idx: number, isActive: boolean) => (
		<QuickSearchResultRow
			key={`m-${m.id}`}
			icon={LayoutGrid}
			idx={idx}
			isActive={isActive}
			title={displayText(m.name) || "Module"}
			subtitle={displayText(m.route)}
			onSelect={() => selectAndClose(modulePath({ route: m.route }))}
		/>
	);

	const renderEntry = (
		row: {
			module: SearchModuleEntryGroup["module"];
			item: Record<string, unknown>;
			entryId: string | number;
			path: string;
		},
		idx: number,
		isActive: boolean
	) => (
		<QuickSearchResultRow
			key={`e-${row.module.id}-${row.entryId}`}
			icon={LayoutGrid}
			idx={idx}
			isActive={isActive}
			title={entryItemLabel(row.item)}
			subtitle={displayText(row.module.name || row.module.route)}
			onSelect={() => selectAndClose(row.path)}
		/>
	);

	const renderTag = (t: any, idx: number, isActive: boolean) => (
		<QuickSearchResultRow
			key={`t-${t.id}`}
			icon={Tag}
			idx={idx}
			isActive={isActive}
			title={displayText(t.tag)}
			subtitle={`used ${t.usage_count} times`}
			onSelect={() => selectAndClose("/tags")}
		/>
	);

	const renderUser = (u: any, idx: number, isActive: boolean) => (
		<QuickSearchResultRow
			key={`u-${u.id}`}
			icon={Users}
			idx={idx}
			isActive={isActive}
			title={displayText(u.name)}
			subtitle={displayText(u.email)}
			onSelect={() => selectAndClose(`/users/${encodeURIComponent(String(u.id))}/edit`)}
		/>
	);

	// Visual grouped rendering (with running idx for keyboard highlight)
	let runningIdx = 0;

	const emptyGroups =
		!!groups &&
		visibleGroups.every((g) => {
			if (g === "entries") {
				return entryRows.length === 0;
			}

			return asList((groups as Record<string, unknown>)[g]).length === 0;
		});

	const hasRenderableResults = !!groups && !emptyGroups;

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
						{aiSearchEnabled ? (
							<Sparkles size={18} className="text-accent" />
						) : (
							<Search size={18} className="text-text-3" />
						)}
						<input
							ref={inputRef}
							value={rawQuery}
							onChange={(e) => setRawQuery(e.target.value)}
							aria-label={
								aiSearchEnabled
									? "Ask about pages, modules, tags, users"
									: "Search pages, modules, tags, users"
							}
							placeholder={
								aiSearchEnabled
									? "Ask about pages, modules, tags, users…"
									: "Search pages, modules, tags, users…"
							}
							className="flex-1 bg-transparent text-[15px] text-text placeholder:text-text-3 focus:outline-none"
							spellCheck={false}
						/>
						<IconButton label="Close" title="Close (Esc)" onClick={onClose}>
							<X size={16} />
						</IconButton>
					</div>

					<div className="max-h-[460px] overflow-auto px-1 py-3 text-[13px]">
						{!activeQueryText && (
							<InlineEmpty
								variant="plain"
								align="center"
								pad="xl"
								className="px-4 py-10"
							>
								{aiSearchEnabled
									? "Describe what you're looking for, then press Enter."
									: "Type at least two characters to search the site."}
							</InlineEmpty>
						)}

						{activeQueryText &&
							isLoading &&
							(aiSearchEnabled ? (
								<AiSearchLoading />
							) : (
								<InlineEmpty variant="plain" align="center" className="px-4">
									Searching…
								</InlineEmpty>
							))}

						{activeQueryText && !isLoading && aiError && (
							<InlineEmpty
								variant="plain"
								align="center"
								pad="xl"
								className="px-4 py-8 text-danger"
							>
								{aiError}
							</InlineEmpty>
						)}

						{activeQueryText && !isLoading && !aiError && (groups || answer) && (
							<>
								{aiSearchEnabled && answer && (
									<div className="mb-2 mx-1 rounded-lg border border-border bg-surface-2/50 px-3 py-2.5 text-[13px] leading-snug text-text-2">
										<div className="mb-1 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-text-3">
											<Sparkles size={11} className="text-accent" />
											Answer
										</div>
										{answer}
									</div>
								)}

								{hasRenderableResults &&
									visibleGroups.map((gKey) => {
										if (gKey === "entries") {
											if (entryRows.length === 0) {
												return null;
											}

											return (
												<div key={gKey} className="mb-1 last:mb-0">
													<div className="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-text-3">
														{GROUP_LABELS[gKey]}
													</div>
													<div className="flex flex-col gap-px">
														{entryRows.map((row) => {
															const currentIdx = runningIdx++;
															const isActive =
																currentIdx === activeIndex;

															return renderEntry(
																row,
																currentIdx,
																isActive
															);
														})}
													</div>
												</div>
											);
										}

										const items = asList(
											(groups as Record<string, unknown>)[gKey]
										);

										if (items.length === 0) {
											return null;
										}

										return (
											<div key={gKey} className="mb-1 last:mb-0">
												<div className="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-text-3">
													{GROUP_LABELS[gKey]}
												</div>
												<div className="flex flex-col gap-px">
													{items.map((it) => {
														const currentIdx = runningIdx++;
														const isActive = currentIdx === activeIndex;

														if (gKey === "pages")
															return renderPage(
																it,
																currentIdx,
																isActive
															);
														if (gKey === "modules")
															return renderModule(
																it,
																currentIdx,
																isActive
															);
														if (gKey === "tags")
															return renderTag(
																it,
																currentIdx,
																isActive
															);
														if (gKey === "users")
															return renderUser(
																it,
																currentIdx,
																isActive
															);
														return null;
													})}
												</div>
											</div>
										);
									})}

								{emptyGroups && !answer && (
									<InlineEmpty
										variant="plain"
										align="center"
										pad="xl"
										className="px-4 py-8"
									>
										No results for “{activeQueryText}”.
									</InlineEmpty>
								)}
							</>
						)}
					</div>

					{/* Footer hints */}
					<div className="flex items-center justify-between border-t border-border px-4 py-2 text-[11px] text-text-3">
						<span>
							{aiSearchEnabled
								? "↵ search · ↑↓ navigate · esc close"
								: "↑↓ navigate · ↵ open · esc close"}
						</span>
						<span className="inline-flex items-center gap-1.5">
							{aiSearchEnabled && (
								<span className="inline-flex items-center gap-1 rounded bg-accent/10 px-1.5 py-px text-[10px] font-medium text-accent">
									<Sparkles size={10} />
									AI
								</span>
							)}
							⌘K
						</span>
					</div>
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
