import { formatNumber } from "@/lib/number";

interface TrafficBarsProps {
	series: Array<{ date: string; visits: number }>;
}

export const TrafficBars = ({ series }: TrafficBarsProps) => {
	const max = Math.max(...series.map((d) => d.visits), 1);

	return (
		<div
			className="grid h-[200px] gap-1.5 pt-1"
			style={{
				gridTemplateColumns: `repeat(${series.length}, minmax(0, 1fr))`,
			}}
		>
			{series.map((d) => {
				const pct = (d.visits / max) * 100;

				return (
					<div
						className="group flex min-w-0 flex-col"
						key={d.date}
						title={`${formatNumber(d.visits)} visits on ${d.date}`}
					>
						<div className="relative flex flex-1 items-end">
							<div
								className="flex w-full justify-center rounded-t-sm bg-accent pt-1 transition-[filter,background] group-hover:brightness-110"
								style={{ height: `${pct}%`, minHeight: "22px" }}
							>
								<span className="whitespace-nowrap text-[10.5px] font-semibold text-accent-fg tabular-nums">
									{formatNumber(d.visits)}
								</span>
							</div>
						</div>
						<div className="mt-1.5 text-center text-[10.5px] text-text-3 tabular-nums">
							{d.date}
						</div>
					</div>
				);
			})}
		</div>
	);
};
