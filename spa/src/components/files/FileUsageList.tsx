import { Link } from "react-router-dom";

import type { ResourceUsage } from "@/api/endpoints/resources";
import { Loading } from "@/components/ui/Loading";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { RESOURCE_USAGE_STATUS_VARIANTS } from "@/lib/statusMapping";
import { resourceUsagePath } from "@/lib/routes";

interface FileUsageListProps {
	isLoading: boolean;
	usages: ResourceUsage[];
}

/**
 * "Used by" panel for the file detail slide-over. Replaces the raw allocations
 * dump with resolved location / entry / status rows, dimming archived usages and
 * linking each entry to its edit screen where one exists. Driven by
 * `/resources/{id}/usage`.
 */
export const FileUsageList = ({ isLoading, usages }: FileUsageListProps) => {
	return (
		<section>
			<SectionLabel as="h3" className="mb-1.5">
				Used by
			</SectionLabel>

			{isLoading ? (
				<Loading />
			) : usages.length === 0 ? (
				<InlineEmpty pad="sm">Not currently referenced anywhere.</InlineEmpty>
			) : (
				<ul className="divide-y divide-border rounded-md border border-border bg-surface">
					{usages.map((usage, index) => {
						const path = resourceUsagePath(usage.link);
						const dimmed = usage.status === "archived" || usage.status === "none";

						return (
							<li
								className={`grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_120px] items-center gap-2 px-3 py-1.5 text-[12px] ${
									dimmed ? "opacity-60" : ""
								}`}
								key={`${usage.location}-${usage.title}-${index}`}
							>
								<span className="truncate text-text-2">{usage.location}</span>
								<span className="truncate">
									{path ? (
										<Link
											className="text-accent hover:underline"
											to={path}
											onClick={(event) => event.stopPropagation()}
										>
											{usage.title}
										</Link>
									) : (
										<span className="text-text-2">{usage.title}</span>
									)}
								</span>
								<span className="text-right">
									<StatusBadge
										label={RESOURCE_USAGE_STATUS_VARIANTS[usage.status].label}
										tone={RESOURCE_USAGE_STATUS_VARIANTS[usage.status].tone}
									/>
								</span>
							</li>
						);
					})}
				</ul>
			)}
		</section>
	);
};
