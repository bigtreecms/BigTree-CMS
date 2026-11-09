import { useEffect, useMemo, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "react-router-dom";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageContainer } from "@/components/shell/PageContainer";
import { PageHead } from "@/components/shell/PageHead";
import { SearchResultRow } from "@/components/search/SearchResultRow";
import { SearchResultSection } from "@/components/search/SearchResultSection";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Loading } from "@/components/ui/Loading";
import { SearchInput } from "@/components/ui/SearchInput";
import { searchApi, type SearchModuleEntryGroup } from "@/api/endpoints/search";
import { useAuthStore } from "@/auth/store";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { modulePath } from "@/lib/moduleActions";
import { formatNumber } from "@/lib/number";
import { isAdmin } from "@/lib/permissions";
import { queryKeys } from "@/lib/queryKeys";
import { pageEditPath, settingEditPath } from "@/lib/routes";
import {
	asList,
	displayText,
	entryItemLabel,
	flattenEntryRows,
	normalizeGroups,
} from "@/lib/searchResults";

/** Results per group; the API's own maximum. */
const LIMIT = 50;

/**
 * /search?q= — every match for a query, like the legacy admin's search
 * results page: pages (title, description and content, with a pasted URL
 * matched as the internal link it's stored as), module entries grouped by
 * module, modules, and for administrators settings, tags and users. The quick
 * search overlay links here with "See all results".
 */
export const SearchResults = () => {
	const [params, setParams] = useSearchParams();
	const q = (params.get("q") ?? "").trim();
	const [input, setInput] = useState(q);
	const debounced = useDebouncedValue(input, 300).trim();
	const admin = isAdmin(useAuthStore((s) => s.user));

	const lastDebounced = useRef(debounced);

	// Keep the URL in step with what's typed, so results are linkable — only
	// when the typed value has actually settled on something new.
	useEffect(() => {
		if (debounced === lastDebounced.current) {
			return;
		}

		lastDebounced.current = debounced;

		if (debounced !== q) {
			setParams(debounced ? { q: debounced } : {}, { replace: true });
		}
	}, [debounced, q, setParams]);

	// And the other way: a new ?q= from outside (quick search) fills the box.
	useEffect(() => {
		setInput((current) => (current.trim() === q ? current : q));
	}, [q]);

	const resultsQ = useQuery({
		queryKey: queryKeys.search.results(q, { limit: LIMIT, full: true }),
		queryFn: () => searchApi.search(q, { limit: LIMIT, full: true }),
		enabled: q.length >= 2,
	});

	const groups = useMemo(() => normalizeGroups(resultsQ.data) ?? {}, [resultsQ.data]);
	const entryGroups = asList(groups.entries) as SearchModuleEntryGroup[];
	const entryRows = useMemo(() => flattenEntryRows(groups), [groups]);
	const pages = groups.pages ?? [];
	const modules = groups.modules ?? [];
	const settings = admin ? (groups.settings ?? []) : [];
	const tags = admin ? (groups.tags ?? []) : [];
	const users = admin ? (groups.users ?? []) : [];
	const total =
		pages.length +
		entryRows.length +
		modules.length +
		settings.length +
		tags.length +
		users.length;

	return (
		<PageContainer width="wide">
			<Breadcrumb items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Search" }]} />

			<PageHead
				sub={
					q.length < 2
						? "Search pages, module entries, modules and more"
						: resultsQ.isLoading
							? "Searching…"
							: `${formatNumber(total)} result${total === 1 ? "" : "s"} for “${q}”`
				}
				title="Search results"
			/>

			<SearchInput
				autoFocus
				aria-label="Search"
				className="mb-4 w-full"
				placeholder="Search for text, or paste a URL to find pages that link to it…"
				value={input}
				onChange={setInput}
			/>

			{q.length < 2 ? (
				<EmptyState dashed>Type at least two characters to search.</EmptyState>
			) : resultsQ.error ? (
				<ErrorPanel error={resultsQ.error} />
			) : resultsQ.isLoading ? (
				<Loading variant="card" />
			) : total === 0 ? (
				<EmptyState dashed>No results for “{q}”.</EmptyState>
			) : (
				<div className="flex flex-col gap-4">
					{pages.length > 0 && (
						<SearchResultSection count={pages.length} noun="page" title="Pages">
							{pages.map((page) => (
								<SearchResultRow
									badge={
										page.archived ? (
											<span className="rounded bg-warn-bg px-1.5 py-px text-[10px] text-warn">
												archived
											</span>
										) : undefined
									}
									excerpt={page.snippet || undefined}
									key={page.id}
									subtitle={`/${page.path}`}
									title={displayText(page.nav_title) || "Untitled"}
									to={pageEditPath(page.id)}
								/>
							))}
						</SearchResultSection>
					)}

					{entryGroups.map((group) => {
						const rows = entryRows.filter((row) => row.module.id === group.module.id);

						if (rows.length === 0) {
							return null;
						}

						return (
							<SearchResultSection
								count={rows.length}
								key={String(group.module.id)}
								noun="entry"
								title={displayText(group.module.name) || "Module entries"}
							>
								{rows.map((row) => (
									<SearchResultRow
										key={String(row.entryId)}
										title={entryItemLabel(row.item)}
										to={row.path}
									/>
								))}
							</SearchResultSection>
						);
					})}

					{modules.length > 0 && (
						<SearchResultSection count={modules.length} noun="module" title="Modules">
							{modules.map((module) => (
								<SearchResultRow
									key={String(module.id)}
									subtitle={module.route}
									title={displayText(module.name) || "Module"}
									to={modulePath({ route: module.route })}
								/>
							))}
						</SearchResultSection>
					)}

					{settings.length > 0 && (
						<SearchResultSection
							count={settings.length}
							noun="setting"
							title="Settings"
						>
							{settings.map((setting) => (
								<SearchResultRow
									key={setting.id}
									subtitle={setting.id}
									title={displayText(setting.name) || setting.id}
									to={settingEditPath(setting.id)}
								/>
							))}
						</SearchResultSection>
					)}

					{tags.length > 0 && (
						<SearchResultSection count={tags.length} noun="tag" title="Tags">
							{tags.map((tag) => (
								<SearchResultRow
									key={tag.id}
									subtitle={`used ${tag.usage_count} times`}
									title={displayText(tag.tag)}
									to="/tags"
								/>
							))}
						</SearchResultSection>
					)}

					{users.length > 0 && (
						<SearchResultSection count={users.length} noun="user" title="Users">
							{users.map((user) => (
								<SearchResultRow
									key={user.id}
									subtitle={user.email}
									title={displayText(user.name)}
									to={`/users/${encodeURIComponent(String(user.id))}/edit`}
								/>
							))}
						</SearchResultSection>
					)}
				</div>
			)}
		</PageContainer>
	);
};
