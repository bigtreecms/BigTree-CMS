import { useMemo } from "react";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { Link, useNavigate } from "react-router-dom";
import { useToastMutation } from "@/hooks/useToastMutation";
import { Bell, Check, ChevronRight, FileText, X } from "lucide-react";
import { DashCard } from "./DashCard";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { Button } from "@/components/ui/Button";
import { IconTile } from "@/components/ui/IconTile";
import { NameIdCell } from "@/components/ui/NameIdCell";
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
	error: unknown;
	loading: boolean;
	pending: PendingChange[];
	summary: DashboardSummary | undefined;
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
			action={
				groups.length > 0 ? (
					<Button
						size="sm"
						variant="secondary"
						onClick={() => navigate("/pending-changes")}
					>
						{loading ? "…" : `${groups.length} categories`}
						<ChevronRight size={11} />
					</Button>
				) : null
			}
			icon={Bell}
			sub={loading ? "Loading…" : `${totalPending} awaiting review`}
			title="Pending changes"
		>
			<QueryRenderer error={error}>
				<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
					<div className="flex min-w-0 flex-col gap-2">
						<SectionLabel className="mb-0.5 border-b border-border pb-1.5" size="xs">
							Recent pending changes
						</SectionLabel>
						{recent.length === 0 ? (
							<InlineEmpty fill className="leading-[1.55]" pad="md">
								No pending changes to review right now.
							</InlineEmpty>
						) : (
							<ul className="m-0 flex list-none flex-col gap-0.5 p-0">
								{recent.map((p) => {
									const busy = busyId === p.id;
									const isPage = p.table === "bigtree_pages";

									return (
										<li
											className="flex items-center gap-2.5 rounded-md p-2 transition-colors hover:bg-surface-2"
											key={p.id}
										>
											<IconTile size="xs" tone="neutral">
												<FileText size={14} />
											</IconTile>
											<Link
												className="block min-w-0 flex-1 text-[12.5px] text-text hover:text-accent"
												to={`/pending-changes/${p.id}`}
											>
												<NameIdCell
													name={p.title || `Change #${p.id}`}
													subtitle={`${humanizeTable(p.table)} · ${p.type} · ${p.date}`}
												/>
											</Link>
											<IconButton
												className="disabled:opacity-40"
												disabled={busy}
												label="Reject"
												title="Reject"
												tone="danger"
												onClick={(e) => {
													e.stopPropagation();
													rejectDialog.open(p.id);
												}}
											>
												<X size={13} />
											</IconButton>
											<IconButton
												className="disabled:opacity-40"
												disabled={busy}
												label="Approve"
												title={isPage ? "Open to approve" : "Approve"}
												tone="success"
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
						<SectionLabel className="mb-0.5 border-b border-border pb-1.5" size="xs">
							Awaiting publisher approval
						</SectionLabel>
						{myPending === 0 ? (
							<InlineEmpty fill className="leading-[1.55]" pad="md">
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
				{...approveDialog.dialogProps}
				confirmLabel="Approve"
				description="The pending change will be published and made live on the site."
				title="Approve this change?"
				onConfirm={() => approveMutation.mutate(approveDialog.item!)}
			/>

			<ConfirmDialog
				{...rejectDialog.dialogProps}
				confirmLabel="Reject"
				description="The pending change will be discarded. The submitting user will need to redo their edits."
				title="Reject this change?"
				variant="danger"
				onConfirm={() => rejectMutation.mutate(rejectDialog.item!)}
			/>
		</DashCard>
	);
};
