import type { AnalyticsCachePeriod } from "@/api/endpoints/dashboard";

export interface TwoWeekPoint {
	date: string;
	visits: number;
}

/**
 * Turn the raw `cache.two_week` map (keyed YYYYMMDD) into an ordered, trimmed
 * 14-point series for the bar chart. Shared by the dashboard Traffic card and
 * the full Analytics screen.
 */
export const buildTwoWeekSeries = (
	twoWeek: Record<string, number> | undefined
): TwoWeekPoint[] | null => {
	if (!twoWeek) {
		return null;
	}

	const entries = Object.entries(twoWeek).sort(([a], [b]) => a.localeCompare(b));

	return entries.slice(-14).map(([yyyymmdd, visits]) => {
		const month = Number(yyyymmdd.slice(4, 6));
		const day = Number(yyyymmdd.slice(6, 8));

		return { date: `${month}/${day}`, visits: Number(visits) };
	});
};

export type GrowthTone = "up" | "down" | "neutral";

export interface GrowthResult {
	/** Display string, e.g. "12.40%" or "N/A". */
	label: string;
	/** Whether the movement is good (up), bad (down), or flat/unknown (neutral). */
	tone: GrowthTone;
}

/**
 * Percentage growth between a present and year-ago metric, matching the legacy
 * analytics dashboard: >5% is good, <-5% is bad. Used for views, visits and
 * average time on site.
 */
export const percentGrowth = (current: number, past: number): GrowthResult => {
	if (!past) {
		return { label: "N/A", tone: "neutral" };
	}

	const pct = ((current - past) / past) * 100;
	const tone: GrowthTone = pct > 5 ? "up" : pct < -5 ? "down" : "neutral";

	return { label: `${pct.toFixed(2)}%`, tone };
};

/**
 * Bounce-rate movement is a percentage-point delta where *lower is better*:
 * a drop beyond -2pt is good, a rise beyond +2pt is bad.
 */
export const bounceGrowth = (current: number, past: number): GrowthResult => {
	if (!past) {
		return { label: "N/A", tone: "neutral" };
	}

	const delta = current - past;
	const tone: GrowthTone = delta < -2 ? "up" : delta > 2 ? "down" : "neutral";

	return { label: `${delta.toFixed(2)}%`, tone };
};

/** Compact human duration from seconds, e.g. 95 → "1m 35s", 40 → "40s". */
export const formatDuration = (seconds: number): string => {
	const total = Math.floor(seconds || 0);

	if (total < 60) {
		return `${total}s`;
	}

	const minutes = Math.floor(total / 60);
	const rem = total - minutes * 60;

	return `${minutes}m ${rem}s`;
};

/** Safe accessor so missing periods render as zeroed cards rather than crashing. */
export const emptyPeriod: AnalyticsCachePeriod = {
	views: 0,
	visits: 0,
	bounces: 0,
	bounce_rate: 0,
	average_time: "0s",
	average_time_seconds: 0,
	total_duration: 0,
};
