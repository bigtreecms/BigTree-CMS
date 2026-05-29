import { AlertTriangle, ChevronRight, Clock } from "lucide-react";
import { Link } from "react-router-dom";

import { DashCard } from "./DashCard";
import { CardError } from "./CardError";
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
						: `${alerts.length} page${alerts.length === 1 ? "" : "s"} need attention`
			}
		>
			{error ? (
				<CardError error={error} />
			) : alerts.length === 0 ? (
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-4 text-center text-[12.5px] text-text-3">
					You haven't flagged any pages, or all flagged pages are up to date.
				</div>
			) : (
				<ul className="m-0 flex list-none flex-col gap-0.5 p-0">
					{alerts.map((alert) => (
						<li
							key={alert.page_id}
							className="flex items-center gap-2.5 rounded-[7px] p-2 transition-colors hover:bg-surface-2"
						>
							<span className="grid h-[26px] w-[26px] place-items-center rounded-md bg-warn-bg text-warn">
								<Clock size={14} />
							</span>
							<Link
								to={`/pages/${alert.page_id}/edit`}
								className="block min-w-0 flex-1 text-[12.5px] text-text hover:text-accent"
							>
								<div className="truncate font-medium">{alert.nav_title}</div>
								<div className="truncate text-[11px] text-text-3">
									{alert.age_days} days old · threshold {alert.threshold_days}
								</div>
							</Link>
							<span className="text-text-3">
								<ChevronRight size={12} />
							</span>
						</li>
					))}
				</ul>
			)}
		</DashCard>
	);
};
