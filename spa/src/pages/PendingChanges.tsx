import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { EmptyState } from "@/components/ui/EmptyState";
import { Loading } from "@/components/ui/Loading";
import { PendingChangeGroup } from "@/components/pending-changes/PendingChangeGroup";

import { pendingChangesApi, type PendingChange } from "@/api/endpoints/dashboard";
import { formatNumber } from "@/lib/number";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";
import { queryKeys } from "@/lib/queryKeys";
import { groupPendingByCategory, isPageChange } from "@/lib/pendingChanges";

/**
 * /pending-changes — the publisher's review queue. Lists every change awaiting
 * approval, grouped by module/table (one section per category), with inline
 * Approve / Reject. Mirrors the legacy `dashboard/pending-changes` screen.
 *
 * Page changes are approved from the detail view (the server-side
 * PendingChangeService doesn't yet wire page approvals), so the Approve action
 * on a page row opens the detail page instead of mutating in place — matching
 * the behaviour of the dashboard card.
 */

type PendingAction = { kind: "approve" | "reject"; change: PendingChange };

export const PendingChanges = () => {
	const navigate = useNavigate();

	const actionDialog = useConfirmDialog<PendingAction>();

	const listQ = useQuery({
		queryKey: queryKeys.pendingChanges.list({ mine: false }),
		queryFn: () => pendingChangesApi.list(),
	});

	const changes = useMemo(() => listQ.data ?? [], [listQ.data]);
	const groups = useMemo(() => groupPendingByCategory(changes), [changes]);

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

	const openDetail = (change: PendingChange) => {
		navigate(`/pending-changes/${change.id}`);
	};

	const handleApprove = (change: PendingChange) => {
		if (isPageChange(change)) {
			// Page approvals aren't wired server-side yet — send the publisher to
			// the detail view where the error surfaces inline.
			openDetail(change);

			return;
		}

		actionDialog.open({ kind: "approve", change });
	};

	const handleReject = (change: PendingChange) => {
		actionDialog.open({ kind: "reject", change });
	};

	const confirmAction = () => {
		if (!actionDialog.item) {
			return;
		}

		if (actionDialog.item.kind === "approve") {
			approveMutation.mutate(actionDialog.item.change.id);
		} else {
			rejectMutation.mutate(actionDialog.item.change.id);
		}
	};

	const total = changes.length;

	return (
		<PageContainer width="xwide">
			<Breadcrumb
				items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Pending changes" }]}
			/>

			<PageHead
				sub={
					listQ.isLoading
						? "Loading…"
						: total === 0
							? "Nothing awaiting review"
							: `${formatNumber(total)} awaiting review across ${groups.length} categor${
									groups.length === 1 ? "y" : "ies"
								}`
				}
				title="Pending changes"
			/>

			{listQ.error ? (
				<ErrorPanel error={listQ.error} />
			) : listQ.isLoading ? (
				<Loading variant="card" />
			) : total === 0 ? (
				<EmptyState dashed>
					There are no changes awaiting your approval right now.
				</EmptyState>
			) : (
				<div className="flex flex-col gap-4">
					{groups.map((group) => (
						<PendingChangeGroup
							busyId={busyId}
							group={group}
							key={group.key}
							onApprove={handleApprove}
							onOpen={openDetail}
							onReject={handleReject}
						/>
					))}
				</div>
			)}

			{actionDialog.item && (
				<ConfirmDialog
					{...actionDialog.dialogProps}
					confirmLabel={
						actionDialog.item.kind === "approve" ? "Approve & publish" : "Reject"
					}
					description={
						actionDialog.item.kind === "approve"
							? "The pending change will be merged into the live record."
							: "The pending change will be discarded. The submitting user will need to redo their edits."
					}
					title={
						actionDialog.item.kind === "approve"
							? "Approve this change?"
							: "Reject this change?"
					}
					variant={actionDialog.item.kind === "reject" ? "danger" : "default"}
					onConfirm={confirmAction}
				/>
			)}
		</PageContainer>
	);
};
