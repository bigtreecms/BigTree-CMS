import { useQuery } from "@tanstack/react-query";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { Check, ChevronLeft, X } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { DescriptionList } from "@/components/ui/DescriptionList";
import { Loading } from "@/components/ui/Loading";
import { SectionLabel } from "@/components/ui/SectionLabel";

import { pendingChangesApi } from "@/api/endpoints/dashboard";

import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";
import { queryKeys } from "@/lib/queryKeys";

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

	const confirmDialog = useConfirmDialog<"approve" | "reject">();

	const detailQ = useQuery({
		queryKey: queryKeys.pendingChanges.detail(id),
		queryFn: () => pendingChangesApi.get(id),
		enabled: valid,
	});

	const approveMutation = useToastMutation({
		mutationFn: () => pendingChangesApi.approve(id),
		invalidate: [["pending-changes"], ["dashboard"]],
		successMessage: "Change approved",
		errorMessage: "Could not approve",
		onSuccess: () => navigate("/dashboard"),
	});

	const rejectMutation = useToastMutation({
		mutationFn: () => pendingChangesApi.reject(id),
		invalidate: [["pending-changes"], ["dashboard"]],
		successMessage: "Change rejected",
		errorMessage: "Could not reject",
		onSuccess: () => navigate("/dashboard"),
	});

	if (!valid) {
		return <Navigate replace to="/dashboard" />;
	}

	if (detailQ.isLoading || !detailQ.data) {
		return (
			<PageContainer width="medium">
				<Loading variant="card" />
			</PageContainer>
		);
	}

	if (detailQ.error) {
		return (
			<PageContainer width="medium">
				<ErrorPanel error={detailQ.error} />
			</PageContainer>
		);
	}

	const change = detailQ.data;
	const busy = approveMutation.isPending || rejectMutation.isPending;

	return (
		<PageContainer width="medium">
			<Breadcrumb
				items={[{ label: "Dashboard", to: "/dashboard" }, { label: "Pending changes" }]}
			/>

			<PageHead
				actions={
					<>
						<Button icon={<ChevronLeft size={13} />} to="/dashboard">
							Back
						</Button>
						<Button
							disabled={busy}
							icon={<X size={13} />}
							variant="dangerGhost"
							onClick={() => confirmDialog.open("reject")}
						>
							Reject
						</Button>
						<Button
							disabled={busy}
							icon={<Check size={13} />}
							variant="primary"
							onClick={() => confirmDialog.open("approve")}
						>
							Approve & publish
						</Button>
					</>
				}
				sub={`${change.type} · ${change.table} · ${change.date}`}
				title={change.title || `Pending change #${change.id}`}
			/>

			<div className="space-y-4">
				<MetaBlock change={change} />

				<DiffSection payload={change.changes} title="Field changes" />
				<DiffSection payload={change.mtm_changes} title="Many-to-many changes" />
				<DiffSection payload={change.tags_changes} title="Tag changes" />
				<DiffSection payload={change.open_graph_changes} title="Open Graph changes" />
			</div>

			{confirmDialog.item === "approve" && (
				<ConfirmDialog
					{...confirmDialog.dialogProps}
					confirmLabel="Approve & publish"
					description="The pending change will be merged into the live record."
					title="Approve this change?"
					onConfirm={() => approveMutation.mutate()}
				/>
			)}

			{confirmDialog.item === "reject" && (
				<ConfirmDialog
					{...confirmDialog.dialogProps}
					confirmLabel="Reject"
					description="The pending change will be discarded. The submitting user will need to redo their edits."
					title="Reject this change?"
					variant="danger"
					onConfirm={() => rejectMutation.mutate()}
				/>
			)}
		</PageContainer>
	);
};

interface MetaBlockProps {
	change: import("@/api/endpoints/dashboard").PendingChangeDetail;
}

const MetaBlock = ({ change }: MetaBlockProps) => (
	<DescriptionList
		boxed
		items={[
			{ label: "Submitted by", value: `User #${change.user}` },
			{ label: "Table", value: change.table, valueClassName: "font-mono text-[12px]" },
			...(change.module
				? [
						{
							label: "Module",
							value: change.module,
							valueClassName: "font-mono text-[12px]",
						},
					]
				: []),
			...(change.item_id !== null
				? [
						{
							label: "Item",
							value: `#${change.item_id}`,
							valueClassName: "font-mono text-[12px]",
						},
					]
				: []),
			{ label: "Type", value: change.type },
			{ label: "Date", value: change.date },
		]}
	/>
);

interface DiffSectionProps {
	payload: unknown;
	title: string;
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
			<SectionLabel
				as="header"
				className="border-b border-border bg-surface-2 px-3 py-2"
				size="sm"
			>
				{title}
			</SectionLabel>
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
