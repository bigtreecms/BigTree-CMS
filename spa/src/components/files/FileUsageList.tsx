import { Link } from "react-router-dom";

import type {
	ResourceUsage,
	ResourceUsageLink,
	ResourceUsageStatus,
} from "@/api/endpoints/resources";
import { Badge, type BadgeTone } from "@/components/ui/Badge";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

interface FileUsageListProps {
	isLoading: boolean;
	usages: ResourceUsage[];
}

const STATUS_LABEL: Record<ResourceUsageStatus, string> = {
	published: "Published",
	archived: "Archived",
	pending: "Pending Draft",
	none: "—",
};

const STATUS_TONE: Record<ResourceUsageStatus, BadgeTone> = {
	published: "success",
	archived: "warn",
	pending: "info",
	none: "neutral",
};

/**
 * Resolve a usage link descriptor into an in-app route. Pages distinguish live
 * (`/pages/{id}/edit`) from pending drafts (`/pages/draft/{id}/edit`); module
 * entries follow `/modules/{route}/{edit_route}/{entry}` (mirroring how
 * moduleActions builds entry URLs). Returns null when there's nowhere to go.
 */
const usageLinkPath = (link: ResourceUsageLink | null): string | null => {
	if (!link) {
		return null;
	}

	if (link.kind === "page") {
		if (link.entry.startsWith("p")) {
			return `/pages/draft/${link.entry.slice(1)}/edit`;
		}

		return `/pages/${link.entry}/edit`;
	}

	if (link.kind === "setting") {
		return `/settings/${link.entry}/edit`;
	}

	const segments = ["modules", link.route, link.edit_route ?? "", link.entry].filter(
		(segment) => segment !== "" && segment !== null
	);

	return `/${segments.join("/")}`;
};

interface StatusBadgeProps {
	status: ResourceUsageStatus;
}

const StatusBadge = ({ status }: StatusBadgeProps) => {
	return <Badge tone={STATUS_TONE[status]}>{STATUS_LABEL[status]}</Badge>;
};

/**
 * "Used by" panel for the file detail slide-over. Replaces the raw allocations
 * dump with resolved location / entry / status rows, dimming archived usages and
 * linking each entry to its edit screen where one exists. Driven by
 * `/resources/{id}/usage`.
 */
export const FileUsageList = ({ isLoading, usages }: FileUsageListProps) => {
	return (
		<section>
			<h3 className="mb-1.5 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
				Used by
			</h3>

			{isLoading ? (
				<div className="text-[12.5px] text-text-3">Loading…</div>
			) : usages.length === 0 ? (
				<InlineEmpty pad="sm">Not currently referenced anywhere.</InlineEmpty>
			) : (
				<ul className="divide-y divide-border rounded-md border border-border bg-surface">
					{usages.map((usage, index) => {
						const path = usageLinkPath(usage.link);
						const dimmed = usage.status === "archived" || usage.status === "none";

						return (
							<li
								key={`${usage.location}-${usage.title}-${index}`}
								className={`grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_120px] items-center gap-2 px-3 py-1.5 text-[12px] ${
									dimmed ? "opacity-60" : ""
								}`}
							>
								<span className="truncate text-text-2">{usage.location}</span>
								<span className="truncate">
									{path ? (
										<Link
											to={path}
											className="text-accent hover:underline"
											onClick={(event) => event.stopPropagation()}
										>
											{usage.title}
										</Link>
									) : (
										<span className="text-text-2">{usage.title}</span>
									)}
								</span>
								<span className="text-right">
									<StatusBadge status={usage.status} />
								</span>
							</li>
						);
					})}
				</ul>
			)}
		</section>
	);
};
