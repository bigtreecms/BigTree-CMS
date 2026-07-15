import { Check, FileText, X } from "lucide-react";

import { Card, CardHeader } from "@/components/ui/Card";
import { IconButton } from "@/components/ui/IconButton";
import type { PendingChange } from "@/api/endpoints/dashboard";
import type { PendingChangeGroup as Group } from "@/lib/pendingChanges";
import { isPageChange } from "@/lib/pendingChanges";
import { pluralize } from "@/lib/number";
import { relativeTime } from "@/lib/time";

interface PendingChangeGroupProps {
	/** Id of the change currently being approved/rejected, if any. */
	busyId: number | null;
	group: Group;
	onApprove: (change: PendingChange) => void;
	onOpen: (change: PendingChange) => void;
	onReject: (change: PendingChange) => void;
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
		<Card className="overflow-hidden">
			<CardHeader className="flex items-center justify-between">
				<h2 className="flex items-center gap-2 text-[13px] font-semibold text-text">
					<FileText className="text-text-3" size={14} />
					{group.label}
				</h2>
				<span className="text-[11.5px] tabular-nums text-text-3">
					{pluralize(group.changes.length, "change")}
				</span>
			</CardHeader>

			<ul className="m-0 flex list-none flex-col p-0">
				{group.changes.map((change) => {
					const busy = busyId === change.id;
					const isNew = change.item_id === null;
					const badgeCls = isNew
						? "bg-accent-soft text-accent"
						: "bg-surface-3 text-text-2";
					const when = relativeTime(change.date);

					return (
						<li
							className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 border-b border-border px-4 py-2.5 text-[13px] transition-colors last:border-b-0 hover:bg-surface-2 sm:grid-cols-[minmax(0,1fr)_90px_140px_auto]"
							key={change.id}
						>
							<button
								className="min-w-0 text-left"
								type="button"
								onClick={() => onOpen(change)}
							>
								<div className="flex items-center gap-2">
									<span className="min-w-0 truncate font-medium text-text hover:text-accent">
										{change.title || `Change #${change.id}`}
									</span>
									{/* Badge sits inline with the title on mobile, where the
									    dedicated badge column is hidden. */}
									<span
										className={`shrink-0 rounded px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide sm:hidden ${badgeCls}`}
									>
										{isNew ? "New" : "Edit"}
									</span>
								</div>
								<div className="truncate text-[11px] text-text-3">
									User #{change.user}
									<span className="sm:hidden"> · {when}</span>
								</div>
							</button>

							<span
								className={`hidden justify-self-start rounded px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide sm:inline-block ${badgeCls}`}
							>
								{isNew ? "New" : "Edit"}
							</span>

							<span className="hidden text-[11.5px] tabular-nums text-text-3 sm:block">
								{when}
							</span>

							<div className="flex items-center gap-1 justify-self-end">
								<IconButton
									className="disabled:opacity-40"
									disabled={busy}
									label="Reject"
									title="Reject"
									tone="danger"
									onClick={() => onReject(change)}
								>
									<X size={14} />
								</IconButton>
								<IconButton
									className="disabled:opacity-40"
									disabled={busy}
									label="Approve"
									title={isPageChange(change) ? "Open to approve" : "Approve"}
									tone="success"
									onClick={() => onApprove(change)}
								>
									<Check size={14} />
								</IconButton>
							</div>
						</li>
					);
				})}
			</ul>
		</Card>
	);
};
