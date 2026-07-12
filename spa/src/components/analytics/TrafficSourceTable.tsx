import { Card, CardHeader } from "@/components/ui/Card";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { formatNumber } from "@/lib/number";

interface TrafficSourceTableProps {
	title: string;
	description: string;
	nameHeader: string;
	/** Raw map keyed by source/browser name → session & view counts. */
	data: Record<string, { sessions: number; screenPageViews: number }> | undefined;
}

/**
 * Generic ranked table for the analytics referrers / browsers breakdowns —
 * name, visit count, view count — sorted by visits. Mirrors the legacy
 * `referrers.php` / `browsers.php` tables.
 */
export const TrafficSourceTable = ({
	title,
	description,
	nameHeader,
	data,
}: TrafficSourceTableProps) => {
	const rows = Object.entries(data ?? {}).sort(([, a], [, b]) => b.sessions - a.sessions);

	return (
		<Card className="overflow-hidden">
			<CardHeader title={title} description={description} />

			<div className="grid grid-cols-[minmax(0,1fr)_110px_110px] gap-x-3 border-b border-border px-4 py-2 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
				<span>{nameHeader}</span>
				<span className="text-right">Visits</span>
				<span className="text-right">Views</span>
			</div>

			{rows.length === 0 ? (
				<InlineEmpty variant="plain" align="center" className="px-4">
					We have no data yet.
				</InlineEmpty>
			) : (
				<ul className="m-0 flex list-none flex-col p-0">
					{rows.map(([name, counts]) => (
						<li
							key={name}
							className="grid grid-cols-[minmax(0,1fr)_110px_110px] gap-x-3 border-b border-border px-4 py-2 text-[13px] last:border-b-0"
						>
							<span className="truncate text-text-2" title={name}>
								{name || "(direct)"}
							</span>
							<span className="text-right tabular-nums text-text-2">
								{formatNumber(counts.sessions)}
							</span>
							<span className="text-right tabular-nums text-text-2">
								{formatNumber(counts.screenPageViews)}
							</span>
						</li>
					))}
				</ul>
			)}
		</Card>
	);
};
