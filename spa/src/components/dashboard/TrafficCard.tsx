import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { Activity, ExternalLink } from "lucide-react";
import { DashCard } from "./DashCard";
import { CardError } from "./CardError";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { Loading } from "@/components/ui/Loading";
import { Button } from "@/components/ui/Button";
import { TrafficBars } from "./TrafficBars";
import type { AnalyticsResponse } from "@/api/endpoints/dashboard";
import { buildTwoWeekSeries } from "@/lib/analytics";
import { useAuthStore } from "@/auth/store";
import { isAdmin } from "@/lib/permissions";
import { formatNumber } from "@/lib/number";

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
							<b className="font-semibold text-text">{formatNumber(total14d)}</b>{" "}
							total
						</span>
						{admin && (
							<Button
								variant="secondary"
								size="sm"
								onClick={() => navigate("/analytics")}
							>
								<ExternalLink size={12} />
								View analytics
							</Button>
						)}
					</div>
				)
			}
		>
			{loading ? (
				<Loading />
			) : error ? (
				<CardError error={error} />
			) : !series || series.length === 0 ? (
				<InlineEmpty icon={Activity}>
					No traffic data yet — check back after the next cache refresh.
				</InlineEmpty>
			) : (
				<TrafficBars series={series} />
			)}
		</DashCard>
	);
};
