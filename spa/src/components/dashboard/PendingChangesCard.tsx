import { useMemo } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Bell, Check, ChevronRight, FileText, X } from "lucide-react";
import { DashCard } from "./DashCard";
import { CardError } from "./CardError";
import { EmptyPending } from "./EmptyPending";
import { SmallBtn } from "./SmallBtn";
import {
	pendingChangesApi,
	type DashboardSummary,
	type PendingChange,
} from "@/api/endpoints/dashboard";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
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
	const queryClient = useQueryClient();
	const groups = useMemo(() => groupPendingByCategory(pending), [pending]);
	const totalPending =
		summary?.pending_changes.publishable ?? groups.reduce((s, g) => s + g.changes.length, 0);
	const myPending = summary?.pending_changes.mine ?? 0;
	const recent = pending.slice(0, 5);

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
			const message =
				err instanceof ApiError && err.message ? err.message : "Could not approve";
			toast.error(message);
		},
	});

	const rejectMutation = useMutation({
		mutationFn: (id: number) => pendingChangesApi.reject(id),
		onSuccess: () => {
			invalidate();
			toast.success("Change rejected");
		},
		onError: (err) => {
			const message =
				err instanceof ApiError && err.message ? err.message : "Could not reject";
			toast.error(message);
		},
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
					<SmallBtn onClick={() => navigate("/pending-changes")}>
						{loading ? "…" : `${groups.length} categories`}
						<ChevronRight size={11} />
					</SmallBtn>
				) : null
			}
		>
			{error ? (
				<CardError error={error} />
			) : (
				<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
					<div className="flex min-w-0 flex-col gap-2">
						<div className="mb-0.5 border-b border-border pb-1.5 text-[10.5px] font-semibold uppercase tracking-[0.07em] text-text-3">
							Recent pending changes
						</div>
						{recent.length === 0 ? (
							<EmptyPending label="No pending changes to review right now." />
						) : (
							<ul className="m-0 flex list-none flex-col gap-0.5 p-0">
								{recent.map((p) => {
									const busy = busyId === p.id;
									const isPage = p.table === "bigtree_pages";

									return (
										<li
											key={p.id}
											className="flex items-center gap-2.5 rounded-[7px] p-2 transition-colors hover:bg-surface-2"
										>
											<span className="grid h-[26px] w-[26px] place-items-center rounded-md bg-surface-3 text-text-2">
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
											<button
												type="button"
												className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger disabled:opacity-40"
												onClick={(e) => {
													e.stopPropagation();
													rejectMutation.mutate(p.id);
												}}
												disabled={busy}
												title="Reject"
												aria-label="Reject"
											>
												<X size={13} />
											</button>
											<button
												type="button"
												className="rounded p-1 text-text-3 hover:bg-hover hover:text-success disabled:opacity-40"
												onClick={(e) => {
													e.stopPropagation();

													if (isPage) {
														// Page approvals aren't yet wired in PendingChangeService — push the user
														// into the detail page where they can see the error inline.
														navigate(`/pending-changes/${p.id}`);

														return;
													}

													approveMutation.mutate(p.id);
												}}
												disabled={busy}
												title={isPage ? "Open to approve" : "Approve"}
												aria-label="Approve"
											>
												<Check size={13} />
											</button>
										</li>
									);
								})}
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
