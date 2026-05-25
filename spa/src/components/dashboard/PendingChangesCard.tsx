import { useMemo } from "react";
import { Bell, ChevronRight, FileText } from "lucide-react";
import { DashCard } from "./DashCard";
import { CardError } from "./CardError";
import { EmptyPending } from "./EmptyPending";
import { LinkBtn } from "./LinkBtn";
import { SmallBtn } from "./SmallBtn";
import type { DashboardSummary, PendingChange } from "@/api/endpoints/dashboard";

interface PendingChangesCardProps {
	summary: DashboardSummary | undefined;
	pending: PendingChange[];
	loading: boolean;
	error: unknown;
}

export const PendingChangesCard = ({
	summary,
	pending,
	loading,
	error,
}: PendingChangesCardProps) => {
	const groups = useMemo(() => groupPendingByTable(pending), [pending]);
	const totalPending =
		summary?.pending_changes.publishable ?? groups.reduce((s, g) => s + g.count, 0);
	const myPending = summary?.pending_changes.mine ?? 0;

	return (
		<DashCard
			icon={Bell}
			title="Pending changes"
			sub={loading ? "Loading…" : `${totalPending} awaiting review`}
			action={
				<SmallBtn>
					View all pending changes
					<ChevronRight size={11} />
				</SmallBtn>
			}
		>
			{error ? (
				<CardError error={error} />
			) : (
				<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
					<div className="flex min-w-0 flex-col gap-2">
						<div className="mb-0.5 border-b border-border pb-1.5 text-[10.5px] font-semibold uppercase tracking-[0.07em] text-text-3">
							Changes pending your approval
						</div>
						{groups.length === 0 ? (
							<EmptyPending label="No pending changes to review right now." />
						) : (
							<ul className="m-0 flex list-none flex-col gap-0.5 p-0">
								{groups.map((g) => (
									<li
										key={g.key}
										className="flex items-center gap-2.5 rounded-[7px] p-2.5 transition-colors hover:bg-surface-2"
									>
										<span className="grid h-[26px] w-[26px] place-items-center rounded-md bg-surface-3 text-text-2">
											<FileText size={14} />
										</span>
										<span className="min-w-0 flex-1 text-[13px] text-text">
											<b className="font-semibold">{g.count}</b> change
											{g.count === 1 ? "" : "s"} for {g.label}
										</span>
										<LinkBtn>
											View changes <ChevronRight size={11} />
										</LinkBtn>
									</li>
								))}
							</ul>
						)}
					</div>

					<div className="flex min-w-0 flex-col gap-2">
						<div className="mb-0.5 border-b border-border pb-1.5 text-[10.5px] font-semibold uppercase tracking-[0.07em] text-text-3">
							Awaiting publisher approval
						</div>
						{myPending === 0 ? (
							<EmptyPending label="You have no changes awaiting a publisher's approval." />
						) : (
							<div className="rounded-md border border-border bg-surface px-3.5 py-3 text-[13px] text-text">
								<b className="font-semibold">{myPending}</b> of your change
								{myPending === 1 ? "" : "s"} {myPending === 1 ? "is" : "are"}{" "}
								waiting for a publisher to review.
							</div>
						)}
					</div>
				</div>
			)}
		</DashCard>
	);
};

/** Group pending changes by table/module so we can show counts per category. */
const groupPendingByTable = (pending: PendingChange[]) => {
	const map = new Map<string, { key: string; label: string; count: number }>();

	for (const p of pending) {
		const key = p.module ? `module:${p.module}` : `table:${p.table}`;
		const label = humanizeTable(p.table);
		const cur = map.get(key);

		if (cur) {
			cur.count++;
		} else {
			map.set(key, { key, label, count: 1 });
		}
	}

	return [...map.values()].sort((a, b) => b.count - a.count);
};

const humanizeTable = (table: string): string => {
	if (table === "bigtree_pages") {
		return "Pages";
	}

	if (table.startsWith("bigtree_")) {
		return table
			.slice("bigtree_".length)
			.replace(/_/g, " ")
			.replace(/\b\w/g, (c) => c.toUpperCase());
	}

	return table.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
};
