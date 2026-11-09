import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { Bookmark, ChevronLeft, Pencil, RotateCcw, Save, Trash } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { pageEditPath, pagePath } from "@/lib/routes";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/TextInput";
import { Loading } from "@/components/ui/Loading";
import { Card } from "@/components/ui/Card";
import { Badge } from "@/components/ui/Badge";

import { pagesApi, type PageRevision } from "@/api/endpoints/pages";
import { pendingChangesApi } from "@/api/endpoints/dashboard";
import { CurrentDraftCard } from "@/components/pages/CurrentDraftCard";
import { RevisionDescriptionDialog } from "@/components/pages/RevisionDescriptionDialog";
import { canPublishPage } from "@/lib/permissions";

import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";
import { queryKeys } from "@/lib/queryKeys";
import { toast } from "@/lib/toast";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { Field } from "@/components/ui/Field";

/**
 * Revisions list for a single page.
 *
 *   GET    /pages/{id}/revisions                  list
 *   POST   /pages/{id}/revisions                  save a new snapshot
 *   PATCH  /pages/{id}/revisions/{revision}       keep / describe a revision
 *   DELETE /pages/{id}/revisions/{revision}       delete
 *   POST   /pages/{id}/revisions/{revision}/restore  load into the draft
 *
 * Restore loads the revision's content into the page's draft (merged onto any
 * draft already queued) and opens the editor on it, so it's reviewed and
 * published like any other change. The live page is untouched until then. A
 * queued draft shows at the top with edit, preview, publish and discard.
 */
export const PageRevisions = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const id = Number(idParam);
	const valid = Number.isFinite(id) && id > 0;
	const navigate = useNavigate();

	const pageQuery = useQuery({
		// With the queued draft overlaid, for the Current draft card.
		queryKey: queryKeys.pages.detail(id, { lineage: true, pending: true }),
		queryFn: () => pagesApi.get(id, { lineage: true, pending: true }),
		enabled: valid,
	});

	const revisionsQuery = useQuery({
		queryKey: queryKeys.pages.revisions(id),
		queryFn: () => pagesApi.revisions.list(id),
		enabled: valid,
	});

	const [description, setDescription] = useState("");
	const deleteDialog = useConfirmDialog<PageRevision>();
	const restoreDialog = useConfirmDialog<PageRevision>();
	const discardDialog = useConfirmDialog<true>();
	const [describing, setDescribing] = useState<PageRevision | null>(null);
	const pageKeys = [queryKeys.pages.detail(id), queryKeys.pages.lists()];

	const saveMutation = useToastMutation({
		mutationFn: () => pagesApi.revisions.save(id, description.trim()),
		invalidate: [queryKeys.pages.revisions(id)],
		successMessage: "Revision saved",
		errorMessage: "Could not save revision",
		onSuccess: () => {
			setDescription("");
		},
	});

	const deleteMutation = useToastMutation({
		mutationFn: (rev: PageRevision) => pagesApi.revisions.delete(id, rev.id),
		invalidate: [queryKeys.pages.revisions(id)],
		successMessage: "Revision deleted",
		errorMessage: "Could not delete revision",
		onSuccess: () => {
			deleteDialog.close();
		},
	});

	const describeMutation = useToastMutation({
		mutationFn: ({ rev, text }: { rev: PageRevision; text: string }) =>
			pagesApi.revisions.update(id, rev.id, text),
		invalidate: [queryKeys.pages.revisions(id)],
		successMessage: "Revision saved",
		errorMessage: "Could not save revision",
		onSuccess: () => {
			setDescribing(null);
		},
	});

	const restoreMutation = useToastMutation({
		mutationFn: (rev: PageRevision) => pagesApi.revisions.restore(id, rev.id),
		// The revision lands in the draft; the editor opens on it for review.
		invalidate: pageKeys,
		successMessage: "Revision loaded into the draft",
		errorMessage: "Could not restore revision",
		onSuccess: (result) => {
			restoreDialog.close();

			// What the draft carries that nobody would think to look for: a required
			// field the revision never had a value for, files it points at that have
			// since been deleted, a template that is gone.
			if (result?.warnings?.length) {
				toast.warning("Loaded, but check the draft", {
					description: result.warnings.join(" "),
				});
			}

			navigate(pageEditPath(id));
		},
	});

	const publishDraftMutation = useToastMutation({
		mutationFn: (pcid: number) => pendingChangesApi.approve(pcid),
		invalidate: [...pageKeys, queryKeys.pages.revisions(id)],
		successMessage: "Draft published",
		errorMessage: "Could not publish draft",
	});

	const discardDraftMutation = useToastMutation({
		mutationFn: (pcid: number) => pendingChangesApi.reject(pcid),
		invalidate: pageKeys,
		successMessage: "Draft discarded",
		errorMessage: "Could not discard draft",
		onSuccess: () => {
			discardDialog.close();
		},
	});

	if (!valid) {
		return <Navigate replace to="/pages" />;
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
	const canManage = canPublishPage(page.access);
	const draftId = page.changes_applied ? page.pending_change_id : undefined;

	const breadcrumbs = [
		{ label: "Pages", to: "/pages" },
		...lineage.map((p) => ({ label: p.nav_title, to: pagePath(p.id) })),
		{ label: page.nav_title || "Page", to: pageEditPath(page.id) },
		{ label: "Revisions" },
	];

	return (
		<PageContainer width="wide">
			<Breadcrumb items={breadcrumbs} />

			<PageHead
				actions={
					<Button icon={<ChevronLeft size={13} />} to={pageEditPath(page.id)}>
						Back to editor
					</Button>
				}
				sub={page.path}
				title={`Revisions for ${page.nav_title || "page"}`}
			/>

			{draftId !== undefined && (
				<CurrentDraftCard
					discarding={discardDraftMutation.isPending}
					page={page}
					publishing={publishDraftMutation.isPending}
					onDiscard={() => discardDialog.open(true)}
					onPublish={() => publishDraftMutation.mutate(draftId)}
				/>
			)}

			<Card className="mb-4 p-4">
				<SectionLabel as="h2" className="mb-2" size="md">
					Save current version as revision
				</SectionLabel>
				<div className="flex flex-wrap items-end gap-2">
					<Field
						className="min-w-[280px] flex-1"
						inlineHint="(what's special about this version?)"
						label="Short description"
					>
						<TextInput
							placeholder="Optional"
							value={description}
							onChange={(e) => setDescription(e.target.value)}
						/>
					</Field>
					<Button
						icon={<Save size={13} />}
						loading={saveMutation.isPending}
						loadingLabel="Saving…"
						variant="primary"
						onClick={() => saveMutation.mutate()}
					>
						Save revision
					</Button>
				</div>
			</Card>

			<RevisionSection
				showDescription
				empty="No saved revisions yet. Use the form above to create one."
				revisions={saved}
				title="Saved revisions"
				onDelete={deleteDialog.open}
				onDescribe={canManage ? setDescribing : undefined}
				onRestore={restoreDialog.open}
			/>

			<RevisionSection
				empty="No auto-saved revisions on file."
				revisions={unsaved}
				title="Auto-saved revisions"
				onDelete={deleteDialog.open}
				onDescribe={canManage ? setDescribing : undefined}
				onRestore={restoreDialog.open}
			/>

			{deleteDialog.item && (
				<ConfirmDialog
					{...deleteDialog.dialogProps}
					confirmLabel="Delete revision"
					description={`This will permanently remove the snapshot from ${deleteDialog.item.updated_at}.`}
					title="Delete revision?"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(deleteDialog.item!)}
				/>
			)}

			{restoreDialog.item && (
				<ConfirmDialog
					{...restoreDialog.dialogProps}
					confirmLabel="Load into draft"
					description={`The version from ${restoreDialog.item.updated_at} will be loaded into this page's draft${draftId !== undefined ? ", replacing what the current draft has for the same fields" : ""}. The live page doesn't change until the draft is published.`}
					title="Restore this revision?"
					onConfirm={() => restoreMutation.mutate(restoreDialog.item!)}
				/>
			)}

			{discardDialog.item && draftId !== undefined && (
				<ConfirmDialog
					{...discardDialog.dialogProps}
					confirmLabel="Discard draft"
					description="The queued changes to this page will be deleted. The live page stays as it is."
					title="Discard this draft?"
					variant="danger"
					onConfirm={() => discardDraftMutation.mutate(draftId)}
				/>
			)}

			<RevisionDescriptionDialog
				initial={describing?.saved_description ?? ""}
				keeping={describing ? !describing.saved : false}
				open={describing !== null}
				saving={describeMutation.isPending}
				updatedAt={describing?.updated_at ?? ""}
				onOpenChange={(open) => {
					if (!open) {
						setDescribing(null);
					}
				}}
				onSave={(text) => {
					if (describing) {
						describeMutation.mutate({ rev: describing, text });
					}
				}}
			/>
		</PageContainer>
	);
};

interface RevisionSectionProps {
	empty: string;
	onDelete: (rev: PageRevision) => void;
	/** Keep (auto-saved) or re-describe (saved) a revision; absent without publisher access. */
	onDescribe?: (rev: PageRevision) => void;
	onRestore: (rev: PageRevision) => void;
	revisions: PageRevision[];
	showDescription?: boolean;
	title: string;
}

const RevisionSection = ({
	title,
	empty,
	revisions,
	showDescription,
	onDelete,
	onDescribe,
	onRestore,
}: RevisionSectionProps) => (
	<section className="mb-4">
		<SectionLabel as="h2" className="mb-2" size="md">
			{title}
		</SectionLabel>
		{revisions.length === 0 ? (
			<InlineEmpty align="center">{empty}</InlineEmpty>
		) : (
			<ul className="divide-y divide-border overflow-hidden rounded-md border border-border bg-surface">
				{revisions.map((rev) => (
					<li
						className="grid grid-cols-[minmax(0,1fr)_140px_120px] items-center gap-3 px-3 py-2 text-[12.5px]"
						key={rev.id}
					>
						<div className="min-w-0">
							<div className="flex min-w-0 items-center gap-1.5">
								<span className="truncate text-text-2">
									{showDescription && rev.saved_description
										? rev.saved_description
										: rev.title || `Revision #${rev.id}`}
								</span>
								{/* What restoring this one would leave behind — the files
								    it points at are gone, or its template is. Both are
								    computed at read time from the snapshot. */}
								{rev.missing_files > 0 && (
									<Badge
										className="shrink-0"
										size="sm"
										title={`${rev.missing_files} file${rev.missing_files === 1 ? "" : "s"} this revision referenced ${rev.missing_files === 1 ? "has" : "have"} since been deleted.`}
										tone="warn"
									>
										{rev.missing_files} missing file
										{rev.missing_files === 1 ? "" : "s"}
									</Badge>
								)}
								{!rev.template_exists && (
									<Badge
										className="shrink-0"
										size="sm"
										title={`The “${rev.template}” template this revision was on no longer exists.`}
										tone="warn"
									>
										Template gone
									</Badge>
								)}
							</div>
							{!showDescription && rev.title && (
								<div className="truncate text-[11px] text-text-3">{rev.title}</div>
							)}
						</div>
						<div className="text-right text-[11px] tabular-nums text-text-3">
							{rev.updated_at}
						</div>
						<div className="flex justify-end gap-1">
							{onDescribe && (
								<IconButton
									label={rev.saved ? "Edit description" : "Keep revision"}
									title={rev.saved ? "Edit description" : "Keep revision"}
									onClick={() => onDescribe(rev)}
								>
									{rev.saved ? <Pencil size={13} /> : <Bookmark size={13} />}
								</IconButton>
							)}
							<IconButton
								label="Restore revision"
								title="Restore revision"
								tone="accent"
								onClick={() => onRestore(rev)}
							>
								<RotateCcw size={13} />
							</IconButton>
							<IconButton
								label="Delete revision"
								title="Delete revision"
								tone="danger"
								onClick={() => onDelete(rev)}
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
