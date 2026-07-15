import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { Activity, ExternalLink } from "lucide-react";
import { DashCard } from "./DashCard";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { Button } from "@/components/ui/Button";
import { TrafficBars } from "./TrafficBars";
import type { AnalyticsResponse } from "@/api/endpoints/dashboard";
import { buildTwoWeekSeries } from "@/lib/analytics";
import { useAuthStore } from "@/auth/store";
import { isAdmin } from "@/lib/permissions";
import { formatNumber } from "@/lib/number";

interface TrafficCardProps {
	data: AnalyticsResponse | undefined;
	error: unknown;
	loading: boolean;
}

export const TrafficCard = ({ data, loading, error }: TrafficCardProps) => {
	const navigate = useNavigate();
	const admin = isAdmin(useAuthStore((s) => s.user));

	// Build the 14-day series from cache.two_week (keyed YYYYMMDD).
	const series = useMemo(() => buildTwoWeekSeries(data?.cache?.two_week), [data]);

	const total14d = useMemo(
		() => (series ? series.reduce((s, d) => s + Number(d.visits), 0) : 0),
		[series]
	);

	return (
		<DashCard
			action={
				series && (
					<div className="flex items-center gap-3">
						<span className="text-[12px] text-text-3 tabular-nums">
							<b className="font-semibold text-text">{formatNumber(total14d)}</b>{" "}
							total
						</span>
						{admin && (
							<Button
								size="sm"
								variant="secondary"
								onClick={() => navigate("/analytics")}
							>
								<ExternalLink size={12} />
								View analytics
							</Button>
						)}
					</div>
				)
			}
			icon={Activity}
			sub="Visits in the past two weeks"
			title="Recent traffic"
		>
			<QueryRenderer
				empty={
					<InlineEmpty icon={Activity}>
						No traffic data yet — check back after the next cache refresh.
					</InlineEmpty>
				}
				error={error}
				isEmpty={!series || series.length === 0}
				isLoading={loading}
			>
				<TrafficBars series={series!} />
			</QueryRenderer>
		</DashCard>
	);
};
