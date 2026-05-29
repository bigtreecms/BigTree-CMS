import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { Activity, ExternalLink } from "lucide-react";
import { DashCard } from "./DashCard";
import { CardError } from "./CardError";
import { CardEmpty } from "./CardEmpty";
import { SmallBtn } from "./SmallBtn";
import { TrafficBars } from "./TrafficBars";
import type { AnalyticsResponse } from "@/api/endpoints/dashboard";
import { buildTwoWeekSeries } from "@/lib/analytics";
import { useAuthStore } from "@/auth/store";
import { isAdmin } from "@/lib/permissions";

interface TrafficCardProps {
	data: AnalyticsResponse | undefined;
	loading: boolean;
	error: unknown;
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
			icon={Activity}
			title="Recent traffic"
			sub="Visits in the past two weeks"
			action={
				series && (
					<div className="flex items-center gap-3">
						<span className="text-[12px] text-text-3 tabular-nums">
							<b className="font-semibold text-text">{total14d.toLocaleString()}</b>{" "}
							total
						</span>
						{admin && (
							<SmallBtn onClick={() => navigate("/analytics")}>
								<ExternalLink size={12} />
								View analytics
							</SmallBtn>
						)}
					</div>
				)
			}
		>
			{loading ? (
				<div className="text-[12.5px] text-text-3">Loading…</div>
			) : error ? (
				<CardError error={error} />
			) : !data?.configured ? (
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3.5 py-[14px] text-[12.5px] leading-[1.55] text-text-3">
					Google Analytics isn't connected yet. Connect it in{" "}
					<span className="font-mono text-text-2">Developer → Analytics</span> to see
					traffic.
				</div>
			) : !series || series.length === 0 ? (
				<CardEmpty
					icon={Activity}
					label="No traffic data yet — check back after the next cache refresh."
				/>
			) : (
				<TrafficBars series={series} />
			)}
		</DashCard>
	);
};
