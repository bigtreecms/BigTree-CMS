import { useMemo } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Settings as SettingsIcon } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { Loading } from "@/components/ui/Loading";
import { TrafficBars } from "@/components/dashboard/TrafficBars";
import { MetricComparison } from "@/components/analytics/MetricComparison";
import { TrafficSourceTable } from "@/components/analytics/TrafficSourceTable";

import { dashboardApi } from "@/api/endpoints/dashboard";
import { buildTwoWeekSeries } from "@/lib/analytics";
import { relativeTime } from "@/lib/time";
import { queryKeys } from "@/lib/queryKeys";

/**
 * /analytics — the full traffic dashboard, a port of the legacy
 * `dashboard/vitals-statistics/analytics` screen. Reads the cached Google
 * Analytics snapshot from /dashboard/analytics and renders:
 *
 *   1. A two-week visits bar chart.
 *   2. Month / quarter / year panels comparing each range to a year ago.
 *   3. Top referrers and browsers tables.
 *
 * Not-configured / no-cache states point the user at the Developer → Analytics
 * config page, since this screen only reads — connecting GA happens there.
 */

const MONTHS = [
	"January",
	"February",
	"March",
	"April",
	"May",
	"June",
	"July",
	"August",
	"September",
	"October",
	"November",
	"December",
];

const buildRangeLabels = () => {
	const now = new Date();
	const year = now.getFullYear();
	const month = now.getMonth(); // 0-indexed
	const dayLabel = `${MONTHS[month]} ${now.getDate()}`;
	const quarterStartMonth = month - (month % 3);

	return {
		month: `${MONTHS[month]} 1 – ${dayLabel}, ${year}`,
		quarter: `${MONTHS[quarterStartMonth]} 1 – ${dayLabel}, ${year}`,
		year: `January 1 – ${dayLabel}, ${year}`,
	};
};

const ConfigLink = () => (
	<Button icon={<SettingsIcon size={13} />} to="/developer/configure/analytics">
		Analytics settings
	</Button>
);

export const Analytics = () => {
	const analyticsQ = useQuery({
		queryKey: queryKeys.dashboard.analytics(),
		queryFn: dashboardApi.analytics,
	});

	const data = analyticsQ.data;
	const cache = data?.cache;
	const series = useMemo(() => buildTwoWeekSeries(cache?.two_week), [cache]);
	const ranges = useMemo(buildRangeLabels, []);

	const sub = data?.cache_at
		? `Cached ${relativeTime(data.cache_at)}`
		: "Visits, views and engagement from Google Analytics";

	return (
		<PageContainer width="xwide">
			<Breadcrumb
				items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Analytics" }]}
			/>

			<PageHead
				actions={<ConfigLink />}
				sub={analyticsQ.isLoading ? "Loading…" : sub}
				title="Analytics"
			/>

			{analyticsQ.error ? (
				<ErrorPanel error={analyticsQ.error} />
			) : analyticsQ.isLoading ? (
				<Loading variant="card" />
			) : !data?.configured ? (
				<EmptyState dashed className="leading-[1.6]">
					Google Analytics isn't connected yet. Connect it from{" "}
					<Link
						className="font-medium text-accent hover:underline"
						to="/developer/configure/analytics"
					>
						Developer → Analytics
					</Link>{" "}
					to start collecting traffic data.
				</EmptyState>
			) : !data.has_cache || !cache ? (
				<EmptyState dashed>
					No traffic data has been cached yet — check back after the next analytics
					refresh.
				</EmptyState>
			) : (
				<div className="flex flex-col gap-4">
					<Card className="overflow-hidden">
						<CardHeader
							description="Visits over the past 14 days"
							title="Two-week heads-up"
						/>
						<div className="p-4">
							{series && series.length > 0 ? (
								<TrafficBars series={series} />
							) : (
								<InlineEmpty align="center" variant="plain">
									No recent daily data.
								</InlineEmpty>
							)}
						</div>
					</Card>

					<MetricComparison
						current={cache.month}
						rangeLabel={ranges.month}
						title="Current month"
						yearAgo={cache.year_ago_month}
					/>
					<MetricComparison
						current={cache.quarter}
						rangeLabel={ranges.quarter}
						title="Current quarter"
						yearAgo={cache.year_ago_quarter}
					/>
					<MetricComparison
						current={cache.year}
						rangeLabel={ranges.year}
						title="Current year"
						yearAgo={cache.year_ago_year}
					/>

					<div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
						<TrafficSourceTable
							data={cache.referrers}
							description="Domains referring the most traffic over the past month."
							nameHeader="Referrer"
							title="Top referrers"
						/>
						<TrafficSourceTable
							data={cache.browsers}
							description="Browsers your visitors used over the past month."
							nameHeader="Browser"
							title="Browsers"
						/>
					</div>
				</div>
			)}
		</PageContainer>
	);
};
