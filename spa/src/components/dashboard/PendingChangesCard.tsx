import { useMemo } from "react";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { Link, useNavigate } from "react-router-dom";
import { useToastMutation } from "@/hooks/useToastMutation";
import { Bell, Check, ChevronRight, FileText, X } from "lucide-react";
import { DashCard } from "./DashCard";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { Button } from "@/components/ui/Button";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { IconButton } from "@/components/ui/IconButton";
import {
	pendingChangesApi,
	type DashboardSummary,
	type PendingChange,
} from "@/api/endpoints/dashboard";
import { groupPendingByCategory, humanizeTable } from "@/lib/pendingChanges";

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
	const navigate = useNavigate();
	const rejectDialog = useConfirmDialog<number>();
	const approveDialog = useConfirmDialog<number>();
	const groups = useMemo(() => groupPendingByCategory(pending), [pending]);
	const totalPending =
		summary?.pending_changes.publishable ?? groups.reduce((s, g) => s + g.changes.length, 0);
	const myPending = summary?.pending_changes.mine ?? 0;
	const recent = pending.slice(0, 5);

	const approveMutation = useToastMutation({
		mutationFn: (id: number) => pendingChangesApi.approve(id),
		invalidate: [["pending-changes"], ["dashboard"]],
		successMessage: "Change approved",
		errorMessage: "Could not approve",
	});

	const rejectMutation = useToastMutation({
		mutationFn: (id: number) => pendingChangesApi.reject(id),
		invalidate: [["pending-changes"], ["dashboard"]],
		successMessage: "Change rejected",
		errorMessage: "Could not reject",
	});

	const busyId =
		(approveMutation.isPending && approveMutation.variables) ||
		(rejectMutation.isPending && rejectMutation.variables) ||
		null;


	return (
		<DashCard
			icon={Bell}
			title="Pending changes"
			sub={loading ? "Loading…" : `${totalPending} awaiting review`}
			action={
				groups.length > 0 ? (
					<Button
						variant="secondary"
						size="sm"
						onClick={() => navigate("/pending-changes")}
					>
						{loading ? "…" : `${groups.length} categories`}
						<ChevronRight size={11} />
					</Button>
				) : null
			}
		>
			<QueryRenderer error={error}>
				<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
					<div className="flex min-w-0 flex-col gap-2">
						<SectionLabel size="xs" className="mb-0.5 border-b border-border pb-1.5">
							Recent pending changes
						</SectionLabel>
						{recent.length === 0 ? (
							<InlineEmpty fill pad="md" className="leading-[1.55]">
								No pending changes to review right now.
							</InlineEmpty>
						) : (
							<ul className="m-0 flex list-none flex-col gap-0.5 p-0">
								{recent.map((p) => {
									const busy = busyId === p.id;
									const isPage = p.table === "bigtree_pages";

									return (
										<li
											key={p.id}
											className="flex items-center gap-2.5 rounded-md p-2 transition-colors hover:bg-surface-2"
										>
											<span className="grid size-[26px] place-items-center rounded-md bg-surface-3 text-text-2">
												<FileText size={14} />
											</span>
											<Link
												to={`/pending-changes/${p.id}`}
												className="block min-w-0 flex-1 text-[12.5px] text-text hover:text-accent"
											>
												<div className="truncate font-medium">
													{p.title || `Change #${p.id}`}
												</div>
												<div className="truncate text-[11px] text-text-3">
													{humanizeTable(p.table)} · {p.type} · {p.date}
												</div>
											</Link>
											<IconButton
												tone="danger"
												className="disabled:opacity-40"
												onClick={(e) => {
													e.stopPropagation();
													rejectDialog.open(p.id);
												}}
												disabled={busy}
												title="Reject"
												label="Reject"
											>
												<X size={13} />
											</IconButton>
											<IconButton
												tone="success"
												className="disabled:opacity-40"
												onClick={(e) => {
													e.stopPropagation();

													if (isPage) {
														// Page approvals aren't yet wired in PendingChangeService — push the user
														// into the detail page where they can see the error inline.
														navigate(`/pending-changes/${p.id}`);

														return;
													}

													approveDialog.open(p.id);
												}}
												disabled={busy}
												title={isPage ? "Open to approve" : "Approve"}
												label="Approve"
											>
												<Check size={13} />
											</IconButton>
										</li>
									);
								})}
							</ul>
						)}
					</div>

					<div className="flex min-w-0 flex-col gap-2">
						<SectionLabel size="xs" className="mb-0.5 border-b border-border pb-1.5">
							Awaiting publisher approval
						</SectionLabel>
						{myPending === 0 ? (
							<InlineEmpty fill pad="md" className="leading-[1.55]">
								You have no changes awaiting a publisher's approval.
							</InlineEmpty>
						) : (
							<div className="rounded-md border border-border bg-surface px-3.5 py-3 text-[13px] text-text">
								<b className="font-semibold">{myPending}</b> of your change
								{myPending === 1 ? "" : "s"} {myPending === 1 ? "is" : "are"}{" "}
								waiting for a publisher to review.
							</div>
						)}
					</div>
				</div>
			</QueryRenderer>

			<ConfirmDialog
				open={approveDialog.isOpen}
				onOpenChange={(v) => { if (!v) approveDialog.close(); }}
				title="Approve this change?"
				description="The pending change will be published and made live on the site."
				confirmLabel="Approve"
				onConfirm={() => approveMutation.mutate(approveDialog.item!)}
			/>

			<ConfirmDialog
				open={rejectDialog.isOpen}
				onOpenChange={(v) => { if (!v) rejectDialog.close(); }}
				title="Reject this change?"
				description="The pending change will be discarded. The submitting user will need to redo their edits."
				confirmLabel="Reject"
				variant="danger"
				onConfirm={() => rejectMutation.mutate(rejectDialog.item!)}
			/>
		</DashCard>
	);
};
