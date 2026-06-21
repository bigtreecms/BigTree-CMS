import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Navigate, useParams } from "react-router-dom";
import { ChevronLeft, RotateCcw, Save, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/TextInput";
import { EmptyState } from "@/components/ui/EmptyState";
import { Card } from "@/components/ui/Card";

import { pagesApi, type PageRevision } from "@/api/endpoints/pages";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

/**
 * Revisions list for a single page.
 *
 *   GET    /pages/{id}/revisions                  list
 *   POST   /pages/{id}/revisions                  save a new snapshot
 *   DELETE /pages/{id}/revisions/{revision}       delete
 *   POST   /pages/{id}/revisions/{revision}/restore  restore onto the live page
 *
 * Restore overwrites the live page with the revision's content. The server
 * first snapshots the current published state as an auto-revision, so a restore
 * can itself be undone by restoring that snapshot.
 */
export const PageRevisions = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const id = Number(idParam);
	const queryClient = useQueryClient();

	const valid = Number.isFinite(id) && id > 0;

	const pageQuery = useQuery({
		queryKey: ["pages", "detail", id, { lineage: true }],
		queryFn: () => pagesApi.get(id, { lineage: true }),
		enabled: valid,
	});

	const revisionsQuery = useQuery({
		queryKey: ["pages", "revisions", id],
		queryFn: () => pagesApi.revisions.list(id),
		enabled: valid,
	});

	const [description, setDescription] = useState("");
	const [confirmDelete, setConfirmDelete] = useState<PageRevision | null>(null);
	const [confirmRestore, setConfirmRestore] = useState<PageRevision | null>(null);

	const saveMutation = useMutation({
		mutationFn: () => pagesApi.revisions.save(id, description.trim()),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["pages", "revisions", id] });
			setDescription("");
			toast.success("Revision saved");
		},
		onError: (err) => {
			const message =
				err instanceof ApiError && err.message ? err.message : "Could not save revision";
			toast.error(message);
		},
	});

	const deleteMutation = useMutation({
		mutationFn: (rev: PageRevision) => pagesApi.revisions.delete(id, rev.id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["pages", "revisions", id] });
			setConfirmDelete(null);
			toast.success("Revision deleted");
		},
		onError: () => {
			toast.error("Could not delete revision");
		},
	});

	const restoreMutation = useMutation({
		mutationFn: (rev: PageRevision) => pagesApi.revisions.restore(id, rev.id),
		onSuccess: () => {
			// Restore rewrites the live page and adds an auto-snapshot revision.
			queryClient.invalidateQueries({ queryKey: ["pages", "revisions", id] });
			queryClient.invalidateQueries({ queryKey: ["pages", "detail", id] });
			setConfirmRestore(null);
			toast.success("Revision restored to the live page");
		},
		onError: (err) => {
			const message =
				err instanceof ApiError && err.message ? err.message : "Could not restore revision";
			toast.error(message);
		},
	});

	if (!valid) {
		return <Navigate to="/pages" replace />;
	}

	if (pageQuery.isLoading || !pageQuery.data) {
		return (
			<div className="mx-auto max-w-screen-2xl px-6 py-4">
				<EmptyState>Loading…</EmptyState>
			</div>
		);
	}

	if (pageQuery.error) {
		return (
			<div className="mx-auto max-w-screen-2xl px-6 py-4">
				<ErrorPanel error={pageQuery.error} />
			</div>
		);
	}

	const page = pageQuery.data;
	const lineage = page.lineage ?? [];
	const revisions = revisionsQuery.data ?? [];
	const saved = revisions.filter((r) => r.saved);
	const unsaved = revisions.filter((r) => !r.saved);

	const breadcrumbs = [
		{ label: "Pages", to: "/pages" },
		...lineage.map((p) => ({ label: p.nav_title, to: `/pages/${p.id}` })),
		{ label: page.nav_title || "Page", to: `/pages/${page.id}/edit` },
		{ label: "Revisions" },
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={breadcrumbs} />

			<PageHead
				title={`Revisions for ${page.nav_title || "page"}`}
				sub={page.path}
				actions={
					<Button icon={<ChevronLeft size={13} />} to={`/pages/${page.id}/edit`}>
						Back to editor
					</Button>
				}
			/>

			<Card className="mb-4 p-4">
				<h2 className="mb-2 text-[12px] font-semibold uppercase tracking-[0.05em] text-text-3">
					Save current version as revision
				</h2>
				<div className="flex flex-wrap items-end gap-2">
					<label className="block min-w-[280px] flex-1">
						<span className="mb-1 block text-[12px] font-medium text-text-2">
							Short description{" "}
							<span className="text-text-3">
								(what's special about this version?)
							</span>
						</span>
						<TextInput
							value={description}
							onChange={(e) => setDescription(e.target.value)}
							placeholder="Optional"
						/>
					</label>
					<Button
						variant="primary"
						icon={<Save size={13} />}
						onClick={() => saveMutation.mutate()}
						disabled={saveMutation.isPending}
					>
						{saveMutation.isPending ? "Saving…" : "Save revision"}
					</Button>
				</div>
			</Card>

			<RevisionSection
				title="Saved revisions"
				empty="No saved revisions yet. Use the form above to create one."
				revisions={saved}
				showDescription
				onDelete={setConfirmDelete}
				onRestore={setConfirmRestore}
			/>

			<RevisionSection
				title="Auto-saved revisions"
				empty="No auto-saved revisions on file."
				revisions={unsaved}
				onDelete={setConfirmDelete}
				onRestore={setConfirmRestore}
			/>

			{confirmDelete && (
				<ConfirmDialog
					open={true}
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title="Delete revision?"
					description={`This will permanently remove the snapshot from ${confirmDelete.updated_at}.`}
					confirmLabel="Delete revision"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(confirmDelete)}
				/>
			)}

			{confirmRestore && (
				<ConfirmDialog
					open={true}
					onOpenChange={(open) => {
						if (!open) {
							setConfirmRestore(null);
						}
					}}
					title="Restore this revision?"
					description={`The live page will be overwritten with the version from ${confirmRestore.updated_at}. The current version is snapshotted first, so you can undo this.`}
					confirmLabel="Restore revision"
					onConfirm={() => restoreMutation.mutate(confirmRestore)}
				/>
			)}
		</div>
	);
};

interface RevisionSectionProps {
	title: string;
	empty: string;
	revisions: PageRevision[];
	showDescription?: boolean;
	onDelete: (rev: PageRevision) => void;
	onRestore: (rev: PageRevision) => void;
}

const RevisionSection = ({
	title,
	empty,
	revisions,
	showDescription,
	onDelete,
	onRestore,
}: RevisionSectionProps) => (
	<section className="mb-4">
		<h2 className="mb-2 text-[12px] font-semibold uppercase tracking-[0.05em] text-text-3">
			{title}
		</h2>
		{revisions.length === 0 ? (
			<InlineEmpty align="center">{empty}</InlineEmpty>
		) : (
			<ul className="divide-y divide-border overflow-hidden rounded-md border border-border bg-surface">
				{revisions.map((rev) => (
					<li
						key={rev.id}
						className="grid grid-cols-[minmax(0,1fr)_140px_90px] items-center gap-3 px-3 py-2 text-[12.5px]"
					>
						<div className="min-w-0">
							<div className="truncate text-text-2">
								{showDescription && rev.saved_description
									? rev.saved_description
									: rev.title || `Revision #${rev.id}`}
							</div>
							{!showDescription && rev.title && (
								<div className="truncate text-[11px] text-text-3">{rev.title}</div>
							)}
						</div>
						<div className="text-right text-[11px] tabular-nums text-text-3">
							{rev.updated_at}
						</div>
						<div className="flex justify-end gap-1">
							<button
								type="button"
								className="rounded p-1 text-text-3 hover:bg-hover hover:text-accent"
								onClick={() => onRestore(rev)}
								title="Restore revision"
								aria-label="Restore revision"
							>
								<RotateCcw size={13} />
							</button>
							<button
								type="button"
								className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
								onClick={() => onDelete(rev)}
								title="Delete revision"
								aria-label="Delete revision"
							>
								<Trash size={13} />
							</button>
						</div>
					</li>
				))}
			</ul>
		)}
	</section>
);
