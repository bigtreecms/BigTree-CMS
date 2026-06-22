import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { EmptyState } from "@/components/ui/EmptyState";
import { Loading } from "@/components/ui/Loading";
import { PendingChangeGroup } from "@/components/pending-changes/PendingChangeGroup";

import { pendingChangesApi, type PendingChange } from "@/api/endpoints/dashboard";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
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
	const queryClient = useQueryClient();

	const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

	const listQ = useQuery({
		queryKey: ["pending-changes", "list", { mine: false }],
		queryFn: () => pendingChangesApi.list(),
	});

	const changes = useMemo(() => listQ.data ?? [], [listQ.data]);
	const groups = useMemo(() => groupPendingByCategory(changes), [changes]);

	const invalidate = () => {
		queryClient.invalidateQueries({ queryKey: ["pending-changes"] });
		queryClient.invalidateQueries({ queryKey: ["dashboard"] });
	};

	const approveMutation = useMutation({
		mutationFn: (id: number) => pendingChangesApi.approve(id),
		onSuccess: () => {
			invalidate();
			toast.success("Change approved");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Could not approve");
		},
	});

	const rejectMutation = useMutation({
		mutationFn: (id: number) => pendingChangesApi.reject(id),
		onSuccess: () => {
			invalidate();
			toast.success("Change rejected");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Could not reject");
		},
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

		setPendingAction({ kind: "approve", change });
	};

	const handleReject = (change: PendingChange) => {
		setPendingAction({ kind: "reject", change });
	};

	const confirmAction = () => {
		if (!pendingAction) {
			return;
		}

		if (pendingAction.kind === "approve") {
			approveMutation.mutate(pendingAction.change.id);
		} else {
			rejectMutation.mutate(pendingAction.change.id);
		}
	};

	const total = changes.length;

	return (
		<div className="mx-auto max-w-7xl px-6 py-4">
			<Breadcrumb
				items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Pending changes" }]}
			/>

			<PageHead
				title="Pending changes"
				sub={
					listQ.isLoading
						? "Loading…"
						: total === 0
							? "Nothing awaiting review"
							: `${total.toLocaleString()} awaiting review across ${groups.length} categor${
									groups.length === 1 ? "y" : "ies"
								}`
				}
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
							key={group.key}
							group={group}
							busyId={busyId}
							onApprove={handleApprove}
							onReject={handleReject}
							onOpen={openDetail}
						/>
					))}
				</div>
			)}

			{pendingAction && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setPendingAction(null);
						}
					}}
					title={
						pendingAction.kind === "approve"
							? "Approve this change?"
							: "Reject this change?"
					}
					description={
						pendingAction.kind === "approve"
							? "The pending change will be merged into the live record."
							: "The pending change will be discarded. The submitting user will need to redo their edits."
					}
					confirmLabel={pendingAction.kind === "approve" ? "Approve & publish" : "Reject"}
					variant={pendingAction.kind === "reject" ? "danger" : "default"}
					onConfirm={confirmAction}
				/>
			)}
		</div>
	);
};
