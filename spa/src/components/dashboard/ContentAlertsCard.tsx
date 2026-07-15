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
	error: unknown;
	loading: boolean;
}

export const ContentAlertsCard = ({ alerts, loading, error }: ContentAlertsCardProps) => {
	return (
		<DashCard
			icon={AlertTriangle}
			sub={
				loading
					? "Loading…"
					: alerts.length === 0
						? "All tracked pages are within their freshness thresholds."
						: `${pluralize(alerts.length, "page")} need attention`
			}
			title="Content alerts"
		>
			<QueryRenderer
				empty={
					<InlineEmpty align="center">
						You haven't flagged any pages, or all flagged pages are up to date.
					</InlineEmpty>
				}
				error={error}
				isEmpty={alerts.length === 0}
			>
				<ul className="m-0 flex list-none flex-col gap-0.5 p-0">
					{alerts.map((alert) => (
						<li
							className="flex items-center gap-2.5 rounded-md p-2 transition-colors hover:bg-surface-2"
							key={alert.page_id}
						>
							<IconTile size="xs" tone="warn">
								<Clock size={14} />
							</IconTile>
							<Link
								className="block min-w-0 flex-1 text-[12.5px] text-text hover:text-accent"
								to={pageEditPath(alert.page_id)}
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
