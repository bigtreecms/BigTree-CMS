import { Check, FileText, X } from "lucide-react";

import type { PendingChange } from "@/api/endpoints/dashboard";
import type { PendingChangeGroup as Group } from "@/lib/pendingChanges";
import { isPageChange } from "@/lib/pendingChanges";
import { relativeTime } from "@/lib/time";

interface PendingChangeGroupProps {
	group: Group;
	/** Id of the change currently being approved/rejected, if any. */
	busyId: number | null;
	onApprove: (change: PendingChange) => void;
	onReject: (change: PendingChange) => void;
	onOpen: (change: PendingChange) => void;
}

/**
 * One category section on the Pending Changes screen — a header with the
 * module/table name and count, then a row per change with Approve / Reject
 * actions. Mirrors a single `<div class="table">` block from the legacy
 * `dashboard/pending-changes` view.
 */
export const PendingChangeGroup = ({
	group,
	busyId,
	onApprove,
	onReject,
	onOpen,
}: PendingChangeGroupProps) => {
	return (
		<section className="overflow-hidden rounded-xl border border-border bg-surface">
			<header className="flex items-center justify-between border-b border-border bg-surface-2 px-4 py-2.5">
				<h2 className="flex items-center gap-2 text-[13px] font-semibold text-text">
					<FileText size={14} className="text-text-3" />
					{group.label}
				</h2>
				<span className="text-[11.5px] tabular-nums text-text-3">
					{group.changes.length} change{group.changes.length === 1 ? "" : "s"}
				</span>
			</header>

			<ul className="m-0 flex list-none flex-col p-0">
				{group.changes.map((change) => {
					const busy = busyId === change.id;
					const isNew = change.item_id === null;

					return (
						<li
							key={change.id}
							className="grid grid-cols-[minmax(0,1fr)_90px_140px_auto] items-center gap-x-3 border-b border-border px-4 py-2.5 text-[13px] transition-colors last:border-b-0 hover:bg-surface-2"
						>
							<button
								type="button"
								onClick={() => onOpen(change)}
								className="min-w-0 text-left"
							>
								<div className="truncate font-medium text-text hover:text-accent">
									{change.title || `Change #${change.id}`}
								</div>
								<div className="truncate text-[11px] text-text-3">
									User #{change.user}
								</div>
							</button>

							<span
								className={`justify-self-start rounded px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide ${
									isNew
										? "bg-accent-soft text-accent"
										: "bg-surface-3 text-text-2"
								}`}
							>
								{isNew ? "New" : "Edit"}
							</span>

							<span className="text-[11.5px] tabular-nums text-text-3">
								{relativeTime(change.date)}
							</span>

							<div className="flex items-center gap-1 justify-self-end">
								<button
									type="button"
									onClick={() => onReject(change)}
									disabled={busy}
									title="Reject"
									aria-label="Reject"
									className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger disabled:opacity-40"
								>
									<X size={14} />
								</button>
								<button
									type="button"
									onClick={() => onApprove(change)}
									disabled={busy}
									title={isPageChange(change) ? "Open to approve" : "Approve"}
									aria-label="Approve"
									className="rounded p-1 text-text-3 hover:bg-hover hover:text-success disabled:opacity-40"
								>
									<Check size={14} />
								</button>
							</div>
						</li>
					);
				})}
			</ul>
		</section>
	);
};
