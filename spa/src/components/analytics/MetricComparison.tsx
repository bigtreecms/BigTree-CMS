import { Card, CardHeader } from "@/components/ui/Card";
import { SectionLabel } from "@/components/ui/SectionLabel";

import type { AnalyticsCachePeriod } from "@/api/endpoints/dashboard";
import {
	bounceGrowth,
	emptyPeriod,
	formatDuration,
	percentGrowth,
	type GrowthResult,
} from "@/lib/analytics";

interface MetricComparisonProps {
	title: string;
	rangeLabel: string;
	current: AnalyticsCachePeriod | undefined;
	yearAgo: AnalyticsCachePeriod | undefined;
}

interface Metric {
	label: string;
	present: string;
	past: string;
	growth: GrowthResult;
}

const TONE_CLASS: Record<GrowthResult["tone"], string> = {
	up: "text-success",
	down: "text-danger",
	neutral: "text-text-3",
};

/**
 * One period panel (month / quarter / year) on the Analytics screen: four
 * metric cards comparing the present range against the same range a year ago,
 * each with a growth indicator. Mirrors the legacy `_local_compareData` block.
 */
export const MetricComparison = ({
	title,
	rangeLabel,
	current,
	yearAgo,
}: MetricComparisonProps) => {
	const c = current ?? emptyPeriod;
	const p = yearAgo ?? emptyPeriod;

	const metrics: Metric[] = [
		{
			label: "Views",
			present: c.views.toLocaleString(),
			past: p.views.toLocaleString(),
			growth: percentGrowth(c.views, p.views),
		},
		{
			label: "Visits",
			present: c.visits.toLocaleString(),
			past: p.visits.toLocaleString(),
			growth: percentGrowth(c.visits, p.visits),
		},
		{
			label: "Avg. time on site",
			present: formatDuration(c.average_time_seconds),
			past: formatDuration(p.average_time_seconds),
			growth: percentGrowth(c.average_time_seconds, p.average_time_seconds),
		},
		{
			label: "Bounce rate",
			present: `${c.bounce_rate.toFixed(2)}%`,
			past: `${p.bounce_rate.toFixed(2)}%`,
			growth: bounceGrowth(c.bounce_rate, p.bounce_rate),
		},
	];

	return (
		<Card className="overflow-hidden">
			<CardHeader title={title} description={rangeLabel} />

			<div className="grid grid-cols-2 gap-px bg-border md:grid-cols-4">
				{metrics.map((m) => (
					<div key={m.label} className="bg-surface px-4 py-3">
						<div className="flex items-center justify-between">
							<SectionLabel as="span" size="sm">
								{m.label}
							</SectionLabel>
							<span
								className={`text-[11px] font-medium tabular-nums ${TONE_CLASS[m.growth.tone]}`}
							>
								{m.growth.label}
							</span>
						</div>
						<div className="mt-2 text-[18px] font-semibold tabular-nums text-text">
							{m.present}
						</div>
						<div className="mt-0.5 text-[11px] text-text-3 tabular-nums">
							Year-ago: {m.past}
						</div>
					</div>
				))}
			</div>
		</Card>
	);
};
