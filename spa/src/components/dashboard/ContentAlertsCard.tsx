import { AlertTriangle, ChevronRight, Clock } from "lucide-react";
import { Link } from "react-router-dom";

import { DashCard } from "./DashCard";
import { pluralize } from "@/lib/number";
import { pageEditPath } from "@/lib/routes";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconTile } from "@/components/ui/IconTile";
import { NameIdCell } from "@/components/ui/NameIdCell";
import type { ContentAlert } from "@/api/endpoints/dashboard";

/**
 * Surfaces pages the current user has flagged as stale (configured via the
 * legacy per-page age threshold). The API only returns rows that are past
 * threshold, so a "0 stale" state means everything's within tolerance.
 */
interface ContentAlertsCardProps {
	alerts: ContentAlert[];
	loading: boolean;
	error: unknown;
}

export const ContentAlertsCard = ({ alerts, loading, error }: ContentAlertsCardProps) => {
	return (
		<DashCard
			icon={AlertTriangle}
			title="Content alerts"
			sub={
				loading
					? "Loading…"
					: alerts.length === 0
						? "All tracked pages are within their freshness thresholds."
						: `${pluralize(alerts.length, "page")} need attention`
			}
		>
			<QueryRenderer
				error={error}
				isEmpty={alerts.length === 0}
				empty={
					<InlineEmpty align="center">
						You haven't flagged any pages, or all flagged pages are up to date.
					</InlineEmpty>
				}
			>
				<ul className="m-0 flex list-none flex-col gap-0.5 p-0">
					{alerts.map((alert) => (
						<li
							key={alert.page_id}
							className="flex items-center gap-2.5 rounded-md p-2 transition-colors hover:bg-surface-2"
						>
							<IconTile size="xs" tone="warn">
								<Clock size={14} />
							</IconTile>
							<Link
								to={pageEditPath(alert.page_id)}
								className="block min-w-0 flex-1 text-[12.5px] text-text hover:text-accent"
							>
								<NameIdCell
									name={alert.nav_title}
									subtitle={`${alert.age_days} days old · threshold ${alert.threshold_days}`}
								/>
							</Link>
							<span className="text-text-3">
								<ChevronRight size={12} />
							</span>
						</li>
					))}
				</ul>
			</QueryRenderer>
		</DashCard>
	);
};
