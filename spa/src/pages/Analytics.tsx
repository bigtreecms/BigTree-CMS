import { useMemo } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Settings as SettingsIcon } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { TrafficBars } from "@/components/dashboard/TrafficBars";
import { MetricComparison } from "@/components/analytics/MetricComparison";
import { TrafficSourceTable } from "@/components/analytics/TrafficSourceTable";

import { dashboardApi } from "@/api/endpoints/dashboard";
import { buildTwoWeekSeries } from "@/lib/analytics";
import { relativeTime } from "@/lib/time";

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
	<Link
		to="/developer/configure/analytics"
		className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
	>
		<SettingsIcon size={13} />
		Analytics settings
	</Link>
);

export const Analytics = () => {
	const analyticsQ = useQuery({
		queryKey: ["dashboard", "analytics"],
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
		<div className="mx-auto max-w-screen-xl px-6 py-4">
			<Breadcrumb
				items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Analytics" }]}
			/>

			<PageHead
				title="Analytics"
				sub={analyticsQ.isLoading ? "Loading…" : sub}
				actions={<ConfigLink />}
			/>

			{analyticsQ.error ? (
				<ErrorPanel error={analyticsQ.error} />
			) : analyticsQ.isLoading ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading…
				</div>
			) : !data?.configured ? (
				<div className="rounded-xl border border-dashed border-border bg-surface-2 p-9 text-center text-[13px] leading-[1.6] text-text-3">
					Google Analytics isn't connected yet. Connect it from{" "}
					<Link
						to="/developer/configure/analytics"
						className="font-medium text-accent hover:underline"
					>
						Developer → Analytics
					</Link>{" "}
					to start collecting traffic data.
				</div>
			) : !data.has_cache || !cache ? (
				<div className="rounded-xl border border-dashed border-border bg-surface-2 p-9 text-center text-[13px] text-text-3">
					No traffic data has been cached yet — check back after the next analytics
					refresh.
				</div>
			) : (
				<div className="flex flex-col gap-4">
					<section className="overflow-hidden rounded-xl border border-border bg-surface">
						<header className="border-b border-border bg-surface-2 px-4 py-2.5">
							<h2 className="text-[13px] font-semibold text-text">
								Two-week heads-up
							</h2>
							<p className="text-[11px] text-text-3">Visits over the past 14 days</p>
						</header>
						<div className="px-4 py-4">
							{series && series.length > 0 ? (
								<TrafficBars series={series} />
							) : (
								<p className="py-6 text-center text-[12.5px] text-text-3">
									No recent daily data.
								</p>
							)}
						</div>
					</section>

					<MetricComparison
						title="Current month"
						rangeLabel={ranges.month}
						current={cache.month}
						yearAgo={cache.year_ago_month}
					/>
					<MetricComparison
						title="Current quarter"
						rangeLabel={ranges.quarter}
						current={cache.quarter}
						yearAgo={cache.year_ago_quarter}
					/>
					<MetricComparison
						title="Current year"
						rangeLabel={ranges.year}
						current={cache.year}
						yearAgo={cache.year_ago_year}
					/>

					<div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
						<TrafficSourceTable
							title="Top referrers"
							description="Domains referring the most traffic over the past month."
							nameHeader="Referrer"
							data={cache.referrers}
						/>
						<TrafficSourceTable
							title="Browsers"
							description="Browsers your visitors used over the past month."
							nameHeader="Browser"
							data={cache.browsers}
						/>
					</div>
				</div>
			)}
		</div>
	);
};
