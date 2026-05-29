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
		<section className="overflow-hidden rounded-xl border border-border bg-surface">
			<header className="border-b border-border bg-surface-2 px-4 py-2.5">
				<h2 className="text-[13px] font-semibold text-text">{title}</h2>
				<p className="text-[11px] text-text-3">{description}</p>
			</header>

			<div className="grid grid-cols-[minmax(0,1fr)_110px_110px] gap-x-3 border-b border-border px-4 py-2 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
				<span>{nameHeader}</span>
				<span className="text-right">Visits</span>
				<span className="text-right">Views</span>
			</div>

			{rows.length === 0 ? (
				<div className="px-4 py-6 text-center text-[12.5px] text-text-3">
					We have no data yet.
				</div>
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
								{counts.sessions.toLocaleString()}
							</span>
							<span className="text-right tabular-nums text-text-2">
								{counts.screenPageViews.toLocaleString()}
							</span>
						</li>
					))}
				</ul>
			)}
		</section>
	);
};
