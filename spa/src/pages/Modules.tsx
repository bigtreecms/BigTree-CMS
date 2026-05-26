import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import {
	Activity,
	Box,
	Briefcase,
	Calendar,
	Database,
	FileText,
	Folder,
	Globe,
	Image as ImageIcon,
	Layers,
	List,
	type LucideIcon,
	Mail,
	Newspaper,
	Plus,
	Search,
	Server,
	Settings as SettingsIcon,
	Tag as TagIcon,
	Truck,
	Users as UsersIcon,
	X,
} from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { isDeveloper } from "@/lib/permissions";

import {
	modulesApi,
	type ModuleGroup,
	type ModuleSummary,
} from "@/api/endpoints/modules";

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

const MODULES_LIST_KEY = ["modules", "list"] as const;
const MODULE_GROUPS_KEY = ["module-groups", "list"] as const;

interface GroupedModules {
	group: ModuleGroup | null;
	modules: ModuleSummary[];
}

/**
 * Maps the BigTree legacy `icon` slug (stored on each module) onto a Lucide
 * component. The slugs come from the original sprite-sheet keys in
 * `core/admin/css/main.less` (icon_small_*); only the ones modules typically
 * use need a mapping — everything else falls back to <Box />.
 */
const LEGACY_ICON_MAP: Record<string, LucideIcon> = {
	news: Newspaper,
	newspaper: Newspaper,
	tags: TagIcon,
	tag: TagIcon,
	folder: Folder,
	file: FileText,
	page: FileText,
	calendar: Calendar,
	calendar2: Calendar,
	users: UsersIcon,
	user: UsersIcon,
	image: ImageIcon,
	picture: ImageIcon,
	mail: Mail,
	email: Mail,
	world: Globe,
	globe: Globe,
	server: Server,
	gear: SettingsIcon,
	setup: SettingsIcon,
	settings: SettingsIcon,
	truck: Truck,
	car: Truck,
	list: List,
	modules: Layers,
	database: Database,
	briefcase: Briefcase,
	business: Briefcase,
	activity: Activity,
};

const iconFor = (slug: string | undefined): LucideIcon => {
	if (!slug) {
		return Box;
	}

	return LEGACY_ICON_MAP[slug.toLowerCase()] ?? Box;
};

const sortByPositionDesc = <T extends { position?: number }>(rows: T[]) =>
	[...rows].sort((a, b) => (b.position ?? 0) - (a.position ?? 0));

export const Modules = () => {
	const user = useAuthStore((s) => s.user);
	const canCreate = isDeveloper(user);

	const [query, setQuery] = useState("");

	const modulesQuery = useQuery({
		queryKey: MODULES_LIST_KEY,
		queryFn: modulesApi.list,
	});

	const groupsQuery = useQuery({
		queryKey: MODULE_GROUPS_KEY,
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
			? `${matchedModules} match${matchedModules === 1 ? "" : "es"}`
			: `${grouped.length} group${grouped.length === 1 ? "" : "s"} · ${totalModules} module${totalModules === 1 ? "" : "s"}`;

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={[{ label: "Modules" }]} />

			<PageHead
				title="Modules"
				sub={subText}
				actions={
					canCreate ? (
						<Link
							to="/developer/modules/add"
							className="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-accent px-2.5 py-1.5 text-[12.5px] font-medium text-accent-fg transition-colors hover:bg-accent-hover"
						>
							<Plus size={13} />
							New module
						</Link>
					) : undefined
				}
			/>

			<div className="relative mb-5 flex max-w-md items-center gap-2.5">
				<span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-3">
					<Search size={14} />
				</span>
				<input
					className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
					placeholder="Search modules…"
					value={query}
					onChange={(e) => setQuery(e.target.value)}
				/>
				{query && (
					<button
						type="button"
						className="absolute right-2 top-1/2 -translate-y-1/2 grid h-[22px] w-[22px] place-items-center rounded text-text-3 hover:bg-hover hover:text-text"
						onClick={() => setQuery("")}
						aria-label="Clear search"
					>
						<X size={13} />
					</button>
				)}
			</div>

			{isLoading ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading modules…
				</div>
			) : filtered.length === 0 ? (
				<div className="rounded-xl border border-dashed border-border bg-transparent p-9 text-center text-[13px] text-text-3">
					{trimmedQuery
						? `No modules match “${query}”.`
						: "No modules available."}
				</div>
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
		</div>
	);
};

interface ModuleGroupCardProps {
	title: string;
	modules: ModuleSummary[];
}

const ModuleGroupCard = ({ title, modules }: ModuleGroupCardProps) => {
	return (
		<section className="overflow-hidden rounded-xl border border-border bg-surface">
			<header className="flex items-baseline gap-2 border-b border-border bg-surface-2 px-4 py-2.5">
				<h2 className="text-[11px] font-semibold uppercase tracking-[0.08em] text-text">
					{title}
				</h2>
				<span className="text-[11px] tabular-nums text-text-3">{modules.length}</span>
			</header>
			<div className="grid grid-cols-1 gap-2.5 p-3.5 sm:grid-cols-2">
				{modules.map((module) => (
					<ModuleTile key={module.id} module={module} />
				))}
			</div>
		</section>
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
				to={`/modules/${module.id}`}
				className="flex w-full items-center gap-2.5 rounded-md border border-border bg-surface py-2.5 pl-3 pr-12 text-[13.5px] font-medium text-text transition-colors hover:border-border-strong hover:bg-surface-2 focus-visible:border-accent focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-accent-ring"
				title={`Manage ${module.name}`}
			>
				<span className="grid h-[26px] w-[26px] flex-shrink-0 place-items-center rounded-md bg-accent-soft text-accent">
					<Icon size={15} />
				</span>
				<span className="min-w-0 flex-1 truncate">{module.name}</span>
			</Link>
			<Link
				to={`/modules/${module.id}?action=add`}
				className="absolute right-1.5 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-md text-text-3 transition-colors hover:bg-accent hover:text-accent-fg"
				title={`Add to ${module.name}`}
				aria-label={`Add to ${module.name}`}
				onClick={(e) => e.stopPropagation()}
			>
				<Plus size={13} />
			</Link>
		</div>
	);
};
