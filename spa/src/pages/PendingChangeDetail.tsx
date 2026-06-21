import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { Check, ChevronLeft, X } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";

import { pendingChangesApi } from "@/api/endpoints/dashboard";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useState } from "react";

/**
 * /pending-changes/:id — view a single pending change with Approve / Reject.
 *
 * Render approach: keep it generic. The change blob is heterogeneous (every
 * module's fields differ; pages have their own shape) so rather than reach
 * for a typed renderer per resource type, we lay out four labelled JSON
 * sections (`changes` / `mtm_changes` / `tags_changes` / `open_graph_changes`)
 * and let the publisher eyeball the diff. A richer per-table diff lands once
 * the API exposes a `before` snapshot too.
 */
export const PendingChangeDetail = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const id = Number(idParam);
	const valid = Number.isFinite(id) && id > 0;
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [confirm, setConfirm] = useState<"approve" | "reject" | null>(null);

	const detailQ = useQuery({
		queryKey: ["pending-changes", "detail", id],
		queryFn: () => pendingChangesApi.get(id),
		enabled: valid,
	});

	const approveMutation = useMutation({
		mutationFn: () => pendingChangesApi.approve(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["pending-changes"] });
			queryClient.invalidateQueries({ queryKey: ["dashboard"] });
			toast.success("Change approved");
			navigate("/dashboard");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Could not approve");
		},
	});

	const rejectMutation = useMutation({
		mutationFn: () => pendingChangesApi.reject(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["pending-changes"] });
			queryClient.invalidateQueries({ queryKey: ["dashboard"] });
			toast.success("Change rejected");
			navigate("/dashboard");
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Could not reject");
		},
	});

	if (!valid) {
		return <Navigate to="/dashboard" replace />;
	}

	if (detailQ.isLoading || !detailQ.data) {
		return (
			<div className="mx-auto max-w-screen-lg px-6 py-4">
				<EmptyState>Loading…</EmptyState>
			</div>
		);
	}

	if (detailQ.error) {
		return (
			<div className="mx-auto max-w-screen-lg px-6 py-4">
				<ErrorPanel error={detailQ.error} />
			</div>
		);
	}

	const change = detailQ.data;
	const busy = approveMutation.isPending || rejectMutation.isPending;

	return (
		<div className="mx-auto max-w-screen-lg px-6 py-4">
			<Breadcrumb
				items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Pending changes" }]}
			/>

			<PageHead
				title={change.title || `Pending change #${change.id}`}
				sub={`${change.type} · ${change.table} · ${change.date}`}
				actions={
					<>
						<Button icon={<ChevronLeft size={13} />} to="/dashboard">
							Back
						</Button>
						<Button
							variant="dangerGhost"
							icon={<X size={13} />}
							onClick={() => setConfirm("reject")}
							disabled={busy}
						>
							Reject
						</Button>
						<Button
							variant="primary"
							icon={<Check size={13} />}
							onClick={() => setConfirm("approve")}
							disabled={busy}
						>
							Approve & publish
						</Button>
					</>
				}
			/>

			<div className="space-y-4">
				<MetaBlock change={change} />

				<DiffSection title="Field changes" payload={change.changes} />
				<DiffSection title="Many-to-many changes" payload={change.mtm_changes} />
				<DiffSection title="Tag changes" payload={change.tags_changes} />
				<DiffSection title="Open Graph changes" payload={change.open_graph_changes} />
			</div>

			{confirm === "approve" && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirm(null);
						}
					}}
					title="Approve this change?"
					description="The pending change will be merged into the live record."
					confirmLabel="Approve & publish"
					onConfirm={() => approveMutation.mutate()}
				/>
			)}

			{confirm === "reject" && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirm(null);
						}
					}}
					title="Reject this change?"
					description="The pending change will be discarded. The submitting user will need to redo their edits."
					confirmLabel="Reject"
					variant="danger"
					onConfirm={() => rejectMutation.mutate()}
				/>
			)}
		</div>
	);
};

interface MetaBlockProps {
	change: import("@/api/endpoints/dashboard").PendingChangeDetail;
}

const MetaBlock = ({ change }: MetaBlockProps) => (
	<dl className="grid grid-cols-[120px_minmax(0,1fr)] gap-x-3 gap-y-1.5 rounded-lg border border-border bg-surface-2 p-3 text-[12.5px]">
		<dt className="text-text-3">Submitted by</dt>
		<dd className="text-text-2">User #{change.user}</dd>
		<dt className="text-text-3">Table</dt>
		<dd className="font-mono text-[12px] text-text-2">{change.table}</dd>
		{change.module && (
			<>
				<dt className="text-text-3">Module</dt>
				<dd className="font-mono text-[12px] text-text-2">{change.module}</dd>
			</>
		)}
		{change.item_id !== null && (
			<>
				<dt className="text-text-3">Item</dt>
				<dd className="font-mono text-[12px] text-text-2">#{change.item_id}</dd>
			</>
		)}
		<dt className="text-text-3">Type</dt>
		<dd className="text-text-2">{change.type}</dd>
		<dt className="text-text-3">Date</dt>
		<dd className="text-text-2">{change.date}</dd>
	</dl>
);

interface DiffSectionProps {
	title: string;
	payload: unknown;
}

const DiffSection = ({ title, payload }: DiffSectionProps) => {
	const isEmpty =
		payload == null ||
		(Array.isArray(payload) && payload.length === 0) ||
		(typeof payload === "object" &&
			!Array.isArray(payload) &&
			Object.keys(payload as object).length === 0);

	return (
		<section className="overflow-hidden rounded-lg border border-border bg-surface">
			<header className="border-b border-border bg-surface-2 px-3 py-2 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
				{title}
			</header>
			<div className="p-3">
				{isEmpty ? (
					<div className="text-[12px] text-text-3">No changes recorded.</div>
				) : (
					<pre className="overflow-x-auto rounded-md bg-surface-2 p-3 font-mono text-[11.5px] leading-relaxed text-text-2">
						{JSON.stringify(payload, null, 2)}
					</pre>
				)}
			</div>
		</section>
	);
};
