import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Navigate, useParams } from "react-router-dom";
import { ChevronLeft, RotateCcw, Save, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/TextInput";
import { Loading } from "@/components/ui/Loading";
import { Card } from "@/components/ui/Card";

import { pagesApi, type PageRevision } from "@/api/endpoints/pages";

import { useToastMutation } from "@/hooks/useToastMutation";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { Field } from "@/components/ui/Field";

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

	const saveMutation = useToastMutation({
		mutationFn: () => pagesApi.revisions.save(id, description.trim()),
		invalidate: [["pages", "revisions", id]],
		successMessage: "Revision saved",
		errorMessage: "Could not save revision",
		onSuccess: () => {
			setDescription("");
		},
	});

	const deleteMutation = useToastMutation({
		mutationFn: (rev: PageRevision) => pagesApi.revisions.delete(id, rev.id),
		invalidate: [["pages", "revisions", id]],
		successMessage: "Revision deleted",
		errorMessage: "Could not delete revision",
		onSuccess: () => {
			setConfirmDelete(null);
		},
	});

	const restoreMutation = useToastMutation({
		mutationFn: (rev: PageRevision) => pagesApi.revisions.restore(id, rev.id),
		// Restore rewrites the live page and adds an auto-snapshot revision.
		invalidate: [["pages", "revisions", id], ["pages", "detail", id]],
		successMessage: "Revision restored to the live page",
		errorMessage: "Could not restore revision",
		onSuccess: () => {
			setConfirmRestore(null);
		},
	});

	if (!valid) {
		return <Navigate to="/pages" replace />;
	}

	if (pageQuery.isLoading || !pageQuery.data) {
		return (
			<PageContainer width="wide">
				<Loading variant="card" />
			</PageContainer>
		);
	}

	if (pageQuery.error) {
		return (
			<PageContainer width="wide">
				<ErrorPanel error={pageQuery.error} />
			</PageContainer>
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
		<PageContainer width="wide">
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
				<SectionLabel as="h2" size="md" className="mb-2">
					Save current version as revision
				</SectionLabel>
				<div className="flex flex-wrap items-end gap-2">
					<Field
						className="min-w-[280px] flex-1"
						label="Short description"
						inlineHint="(what's special about this version?)"
					>
						<TextInput
							value={description}
							onChange={(e) => setDescription(e.target.value)}
							placeholder="Optional"
						/>
					</Field>
					<Button
						variant="primary"
						icon={<Save size={13} />}
						onClick={() => saveMutation.mutate()}
						loading={saveMutation.isPending}
						loadingLabel="Saving…"
					>
						Save revision
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
		</PageContainer>
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
		<SectionLabel as="h2" size="md" className="mb-2">
			{title}
		</SectionLabel>
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
							<IconButton
								tone="accent"
								onClick={() => onRestore(rev)}
								title="Restore revision"
								label="Restore revision"
							>
								<RotateCcw size={13} />
							</IconButton>
							<IconButton
								tone="danger"
								onClick={() => onDelete(rev)}
								title="Delete revision"
								label="Delete revision"
							>
								<Trash size={13} />
							</IconButton>
						</div>
					</li>
				))}
			</ul>
		)}
	</section>
);
