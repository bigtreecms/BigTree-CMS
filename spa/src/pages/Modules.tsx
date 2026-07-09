import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Plus } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { SearchInput } from "@/components/ui/SearchInput";
import { EmptyState } from "@/components/ui/EmptyState";
import { Loading } from "@/components/ui/Loading";
import { iconFor } from "@/lib/legacyIcons";
import { modulePath } from "@/lib/moduleActions";
import { pluralize } from "@/lib/number";
import { isDeveloper } from "@/lib/permissions";

import { modulesApi, type ModuleGroup, type ModuleSummary } from "@/api/endpoints/modules";
import { queryKeys } from "@/lib/queryKeys";

/**
 * Modules landing — modelled on bigtree-redesign/project/modules-screen.jsx.
 *
 * Each group renders as its own surface card with a small uppercase title +
 * a tabular count chip. Inside, modules render as 2-column tile cards: an
 * accent-tinted square icon, the module name, and a trailing "+" affordance.
 * The "+" deep-links to `/modules/:id?action=add`; ModuleEntry can later
 * resolve that to the actual add action when we add an `?action=add` branch.
 *
 * A single search box filters by module name OR group name (matches the
 * design); empty groups disappear from the grid while a query is active.
 *
 * The "+ New module" primary action is gated behind developer level since
 * module creation lives under the Developer section (Phase 8).
 */

interface GroupedModules {
	group: ModuleGroup | null;
	modules: ModuleSummary[];
}

const sortByPositionDesc = <T extends { position?: number }>(rows: T[]) =>
	[...rows].sort((a, b) => (b.position ?? 0) - (a.position ?? 0));

export const Modules = () => {
	const user = useAuthStore((s) => s.user);
	const canCreate = isDeveloper(user);

	const [query, setQuery] = useState("");

	const modulesQuery = useQuery({
		queryKey: queryKeys.modules.list(),
		queryFn: modulesApi.list,
	});

	const groupsQuery = useQuery({
		queryKey: queryKeys.moduleGroups.list(),
		queryFn: modulesApi.listGroups,
	});

	const isLoading = modulesQuery.isLoading || groupsQuery.isLoading;

	const grouped: GroupedModules[] = useMemo(() => {
		const modules = modulesQuery.data ?? [];
		const groups = groupsQuery.data ?? [];
		const byGroupId = new Map<string, ModuleSummary[]>();
		const ungrouped: ModuleSummary[] = [];

		for (const m of modules) {
			if (m.group) {
				const bucket = byGroupId.get(m.group) ?? [];
				bucket.push(m);
				byGroupId.set(m.group, bucket);

				continue;
			}

			ungrouped.push(m);
		}

		const out: GroupedModules[] = [];

		for (const g of groups) {
			const bucket = byGroupId.get(g.id);

			if (bucket && bucket.length > 0) {
				out.push({ group: g, modules: sortByPositionDesc(bucket) });
			}
		}

		if (ungrouped.length > 0) {
			out.push({ group: null, modules: sortByPositionDesc(ungrouped) });
		}

		return out;
	}, [modulesQuery.data, groupsQuery.data]);

	const trimmedQuery = query.trim().toLowerCase();

	const filtered = useMemo(() => {
		if (!trimmedQuery) {
			return grouped;
		}

		return grouped
			.map((g) => {
				const groupName = (g.group?.name ?? "Ungrouped").toLowerCase();
				const groupMatches = groupName.includes(trimmedQuery);

				const items = g.modules.filter((m) => {
					if (groupMatches) {
						return true;
					}

					return m.name.toLowerCase().includes(trimmedQuery);
				});

				return { ...g, modules: items };
			})
			.filter((g) => g.modules.length > 0);
	}, [grouped, trimmedQuery]);

	const totalModules = modulesQuery.data?.length ?? 0;
	const matchedModules = filtered.reduce((s, g) => s + g.modules.length, 0);

	const subText = isLoading
		? "Loading…"
		: trimmedQuery
			? pluralize(matchedModules, "match", "matches")
			: `${pluralize(grouped.length, "group")} · ${pluralize(totalModules, "module")}`;

	return (
		<PageContainer width="wide">
			<Breadcrumb items={[{ label: "Modules" }]} />

			<PageHead
				title="Modules"
				sub={subText}
				actions={
					canCreate ? (
						<Button
							variant="primary"
							icon={<Plus size={13} />}
							to="/developer/modules/add"
						>
							New module
						</Button>
					) : undefined
				}
			/>

			<SearchInput
				value={query}
				onChange={setQuery}
				placeholder="Search modules…"
				className="mb-5 max-w-md"
			/>

			{isLoading ? (
				<Loading variant="card" label="Loading modules…" />
			) : filtered.length === 0 ? (
				<EmptyState dashed>
					{trimmedQuery ? `No modules match “${query}”.` : "No modules available."}
				</EmptyState>
			) : (
				<div className="flex flex-col gap-[22px]">
					{filtered.map((g) => (
						<ModuleGroupCard
							key={g.group ? `g-${g.group.id}` : "ungrouped"}
							title={g.group?.name ?? "Ungrouped"}
							modules={g.modules}
						/>
					))}
				</div>
			)}
		</PageContainer>
	);
};

interface ModuleGroupCardProps {
	title: string;
	modules: ModuleSummary[];
}

const ModuleGroupCard = ({ title, modules }: ModuleGroupCardProps) => {
	return (
		<Card className="overflow-hidden">
			<CardHeader className="flex items-baseline gap-2">
				<h2 className="text-[11px] font-semibold uppercase tracking-[0.08em] text-text">
					{title}
				</h2>
				<span className="text-[11px] tabular-nums text-text-3">{modules.length}</span>
			</CardHeader>
			<div className="grid grid-cols-1 gap-2.5 p-3.5 sm:grid-cols-2">
				{modules.map((module) => (
					<ModuleTile key={module.id} module={module} />
				))}
			</div>
		</Card>
	);
};

interface ModuleTileProps {
	module: ModuleSummary;
}

const ModuleTile = ({ module }: ModuleTileProps) => {
	const Icon = iconFor(module.icon);

	return (
		<div className="group relative">
			<Link
				to={modulePath(module)}
				className="flex w-full items-center gap-2.5 rounded-md border border-border bg-surface py-2.5 pl-3 pr-12 text-[13.5px] font-medium text-text transition-colors hover:border-border-strong hover:bg-surface-2 focus-visible:border-accent focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-accent-ring"
				title={`Manage ${module.name}`}
			>
				<span className="grid size-[26px] shrink-0 place-items-center rounded-md bg-accent-soft text-accent">
					<Icon size={15} />
				</span>
				<span className="min-w-0 flex-1 truncate">{module.name}</span>
			</Link>
			<Link
				to={`${modulePath(module)}/add`}
				className="absolute right-1.5 top-1/2 grid size-7 -translate-y-1/2 place-items-center rounded-md text-text-3 transition-colors hover:bg-accent hover:text-accent-fg"
				title={`Add to ${module.name}`}
				aria-label={`Add to ${module.name}`}
				onClick={(e) => e.stopPropagation()}
			>
				<Plus size={13} />
			</Link>
		</div>
	);
};
